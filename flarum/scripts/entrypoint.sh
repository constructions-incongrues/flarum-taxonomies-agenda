#!/bin/bash
# Entrypoint du container flarum.
# Au premier démarrage (volume /app vide), il :
#   1. crée un projet Flarum via composer
#   2. installe flamarkt/taxonomies
#   3. déclare l'extension constructions-incongrues/taxonomies-agenda comme repo local (path)
#   4. require constructions-incongrues/taxonomies-agenda:*@dev si le dossier extension/ contient un composer.json
#   5. lance `php flarum install` avec la conf générée à partir des variables d'env
#
# Aux démarrages suivants, si /app contient déjà une install, il skippe tout ça
# et démarre juste Apache. Permet de redémarrer le container sans tout reconstruire.
#
# Pour tout réinitialiser : `docker compose down -v` (détruit le volume flarum_app + db_data).

set -euo pipefail

# Augmente le memory_limit PHP pour lessphp (qui peut Allocate ~100MB+ pour Font Awesome)
php -d memory_limit=256M -r "ini_alter('memory_limit', '256M');"

APP_DIR=/app
FLARUM_MARKER="$APP_DIR/.flarum_installed"

install_flarum() {
    echo ">>> [mi-agenda] installation initiale de Flarum dans $APP_DIR"
    cd /
    # composer create-project ne veut pas s'exécuter dans un dossier non vide.
    # On passe par un dossier temporaire puis on rsync dans /app (qui peut contenir
    # des métadonnées Docker du volume sur certains systèmes).
    rm -rf /tmp/flarum-install
    composer create-project flarum/flarum /tmp/flarum-install --no-install --stability=stable
    cp -a /tmp/flarum-install/. "$APP_DIR/"
    rm -rf /tmp/flarum-install

    cd "$APP_DIR"

    # --- flamarkt/backoffice (fournit le migrator augmenté requis par taxonomies) ---
    echo ">>> [mi-agenda] ajout de flamarkt/backoffice"
    composer require flamarkt/backoffice:"*" --no-interaction --no-scripts || {
        echo "!!! [mi-agenda] impossible d'installer flamarkt/backoffice."
        exit 1
    }

    # --- flamarkt/taxonomies ---
    # Note : la dernière version Packagist est 0.1.9 (2023-03-09). Le wildcard résout vers elle.
    # Si cette version devient incompatible avec un futur flarum/core, épingler explicitement.
    echo ">>> [mi-agenda] ajout de flamarkt/taxonomies"
    composer require flamarkt/taxonomies:"*" --no-interaction --no-scripts || {
        echo "!!! [mi-agenda] impossible d'installer flamarkt/taxonomies avec wildcard."
        echo "!!! [mi-agenda] essaie une version précise (ex : ^0.1) dans entrypoint.sh."
        exit 1
    }

    # --- flarum/tags (requis par taxonomies-agenda pour le tag "agenda") ---
    echo ">>> [mi-agenda] ajout de flarum/tags"
    composer require flarum/tags:"*" --no-interaction --no-scripts || {
        echo "!!! [mi-agenda] impossible d'installer flarum/tags."
        exit 1
    }

    # --- constructions-incongrues/taxonomies-agenda en mode path (symlink vers le dossier monté) ---
    # On ne require que si un composer.json a déjà été posé côté hôte.
    # Sinon on configure juste le repo path, et `composer require` se fera plus tard
    # (`docker compose exec flarum composer require constructions-incongrues/taxonomies-agenda:*@dev`).
    composer config repositories.taxonomies-agenda path /srv/extensions/taxonomies-agenda
    if [ -f /srv/extensions/taxonomies-agenda/composer.json ]; then
        echo ">>> [mi-agenda] require constructions-incongrues/taxonomies-agenda (path)"
        composer require constructions-incongrues/taxonomies-agenda:"*@dev" --no-interaction --no-scripts || true
    else
        echo ">>> [mi-agenda] /srv/extensions/taxonomies-agenda/composer.json absent, on skip le require pour l'instant."
    fi

    composer install --no-interaction

    # --- Installation Flarum via fichier YAML ---
    cat > /tmp/flarum-install.yml <<EOF
debug: true
baseUrl: ${FLARUM_URL}
databaseConfiguration:
  driver: mysql
  host: ${FLARUM_DB_HOST}
  database: ${FLARUM_DB_DATABASE}
  username: ${FLARUM_DB_USER}
  password: ${FLARUM_DB_PASSWORD}
  prefix: ''
adminUser:
  username: ${FLARUM_ADMIN_USER}
  password: ${FLARUM_ADMIN_PASSWORD}
  email: ${FLARUM_ADMIN_EMAIL}
settings:
  forum_title: ${FLARUM_TITLE}
EOF

    echo ">>> [mi-agenda] php flarum install"
    php flarum install --file=/tmp/flarum-install.yml
    rm -f /tmp/flarum-install.yml

    # --- Activation des extensions ---
    # Ordre important : backoffice AVANT taxonomies (il fournit l'AugmentedMigrator qui
    # permet aux migrations conditionnelles de taxonomies d'être skippées proprement).
    echo ">>> [mi-agenda] activation de flamarkt-backoffice"
    php flarum extension:enable flamarkt-backoffice || true

    # Le mécanisme `when` de backoffice ne s'active pas toujours via la CLI extension:enable
    # (il est conçu pour passer par ToggleExtensionHandler côté API). Pour garder le setup
    # idempotent en CLI, on marque manuellement la migration qui dépend de flamarkt-shop
    # (flamarkt_products, absent ici) comme déjà exécutée — elle sera donc skippée.
    echo ">>> [mi-agenda] skip migration taxonomies/product_term (flamarkt-shop non installé)"
    mariadb --skip-ssl -h"$FLARUM_DB_HOST" -u"$FLARUM_DB_USER" -p"$FLARUM_DB_PASSWORD" "$FLARUM_DB_DATABASE" <<'SQL' || true
INSERT IGNORE INTO migrations (migration, extension)
VALUES ('20210401_000400_create_product_term_table', 'flamarkt-taxonomies');
SQL

    echo ">>> [mi-agenda] activation de flamarkt-taxonomies"
    php flarum extension:enable flamarkt-taxonomies || true

    echo ">>> [mi-agenda] activation de flarum-tags"
    php flarum extension:enable flarum-tags || true

    # Nettoie le cache less au démarrage (sinon lessphp recompile tout à chaque requête,
    # et les fichiers .lesscache peuvent atteindre 200+MB avec Font Awesome → OOM)
    echo ">>> [mi-agenda] nettoyage du cache less"
    rm -rf /app/storage/less/*.lesscache

    # --- Seed baseline : tag "agenda" + 3 taxonomies (annee, mois, jour) ---
    # Idempotent via INSERT IGNORE sur les colonnes unique (slug).
    # L'extension taxonomies-agenda se repose sur ces slugs pour peupler discussions.event_date.
    # Les termes (valeurs d'année, mois, jour) restent à créer via l'admin Flarum.
    echo ">>> [mi-agenda] seed tag agenda + taxonomies annee/mois/jour"
    mariadb --skip-ssl -h"$FLARUM_DB_HOST" -u"$FLARUM_DB_USER" -p"$FLARUM_DB_PASSWORD" "$FLARUM_DB_DATABASE" <<'SQL' || true
INSERT IGNORE INTO tags (name, slug, position) VALUES ('Agenda', 'agenda', 0);
INSERT IGNORE INTO flamarkt_taxonomies (type, name, slug, `order`, created_at, updated_at) VALUES
 ('discussions', 'Année',    'annee',    1, NOW(), NOW()),
 ('discussions', 'Mois',     'mois',     2, NOW(), NOW()),
 ('discussions', 'Jour',     'jour',     3, NOW(), NOW()),
 ('discussions', 'Ville',    'ville',    4, NOW(), NOW()),
 ('discussions', 'Lieu',     'lieu',     5, NOW(), NOW()),
 ('discussions', 'Personne', 'personne', 6, NOW(), NOW());
SQL

    if [ -f /srv/extensions/taxonomies-agenda/composer.json ]; then
        echo ">>> [mi-agenda] activation de taxonomies-agenda"
        php flarum extension:enable taxonomies-agenda || true
    fi

    # --- Perms ---
    chown -R www-data:www-data "$APP_DIR"
    chmod -R 755 "$APP_DIR/public/assets" "$APP_DIR/storage" 2>/dev/null || true

    touch "$FLARUM_MARKER"
    echo ">>> [mi-agenda] installation terminée — marker posé à $FLARUM_MARKER"
}

if [ ! -f "$FLARUM_MARKER" ]; then
    install_flarum
else
    echo ">>> [mi-agenda] Flarum déjà installé, démarrage direct."
fi

exec "$@"
