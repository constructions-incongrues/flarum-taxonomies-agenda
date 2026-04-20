# Extension Flarum `constructions-incongrues/taxonomies-agenda` — setup de dev

Environnement local Dockerisé pour développer l'extension qui affiche une timeline chronologique d'événements à venir sur musiques-incongrues.net, à partir des taxonomies `flamarkt/taxonomies`.

Voir [`ADR-001-architecture.md`](./ADR-001-architecture.md) pour la conception technique.

## Prérequis

- Docker Desktop (ou Docker Engine + Docker Compose v2) installé
- 3 GB libres sur le disque pour les images et volumes

## Démarrage

```bash
cd taxonomies-agenda
cp .env.example .env   # ajuste si tu veux
docker compose up -d --build
```

Le premier démarrage prend **~3 à 5 minutes** : il télécharge les images, construit l'image PHP, installe Flarum via composer, installe `flamarkt/taxonomies`, lance la migration initiale, et crée le compte admin.

Suis la progression :

```bash
docker compose logs -f flarum
```

Tu sais que c'est prêt quand tu vois `>>> [mi-agenda] installation terminée`.

Puis ouvre [http://localhost:8888](http://localhost:8888) dans ton navigateur.
Login admin par défaut : `admin` / `admin1234` (change ça dans `.env` avant le premier démarrage si tu tiens à la discrétion).

## Ce qui est installé

- **Flarum** dernière version stable (via `composer create-project`)
- **flamarkt/taxonomies** dernière version disponible sur Packagist — activé automatiquement
- **constructions-incongrues/taxonomies-agenda** en mode `path` : le dossier `./extension/taxonomies-agenda/` de l'hôte est monté dans `/srv/extensions/taxonomies-agenda` du container. Toute modification est immédiatement visible, pas besoin de rebuild l'image. L'activation dépend de la présence d'un `composer.json` valide dans ce dossier.

## Importer un dump de prod

Dépose un dump SQL anonymisé dans `./sql/`. Les fichiers `.sql`, `.sql.gz` ou `.sh` qui s'y trouvent sont exécutés par MariaDB au **premier démarrage du volume** `db_data` (alphabétique).

Pour forcer un ré-import :

```bash
docker compose down -v     # détruit les volumes (DB + app Flarum)
docker compose up -d --build
```

> Attention : `down -v` détruit aussi le volume `flarum_app`, donc `config.php`, les uploads et les extensions installées. Le container se réinstalle from scratch.

### Astuce : récupérer un dump anonymisé depuis la prod

Sur le serveur de prod, quelque chose comme (adapte au contexte) :

```bash
mysqldump --single-transaction --no-tablespaces \
  -u <user> -p <db> \
| gzip > mi-dump-$(date +%Y%m%d).sql.gz
```

Puis rapatrier et déposer dans `./sql/`. Avant import, **anonymiser** : mots de passe, emails, IPs dans `users` → recommandation : faire une première passe SQL qui hash les champs sensibles à des valeurs dummy, ou extraire uniquement les tables nécessaires (`discussions`, `posts`, `tags`, `flamarkt_*`, `discussion_tag`).

## Commandes utiles

```bash
# Shell dans le container Flarum
docker compose exec flarum bash

# Shell mysql
docker compose exec db mariadb -uroot -proot flarum

# Lancer une commande flarum (migrate, cache:clear, etc.)
docker compose exec flarum php /app/flarum cache:clear
docker compose exec flarum php /app/flarum migrate
docker compose exec flarum php /app/flarum extension:list

# Rebuild le JS de l'extension (après modifs dans extension/taxonomies-agenda/js/)
docker compose exec flarum /srv/scripts/rebuild-js.sh

# Reset complet (drop DB, replay install)
docker compose exec flarum /srv/scripts/reset.sh
docker compose restart flarum

# Logs en direct
docker compose logs -f

# Stop propre
docker compose down

# Stop + destruction des volumes (reset total)
docker compose down -v
```

## Vérifier que flamarkt/taxonomies tourne

Une fois Flarum démarré et flamarkt-taxonomies activé, l'API suivante doit répondre en JSON :

```bash
curl -s http://localhost:8888/api/flamarkt/taxonomies | jq
```

Devrait renvoyer `{"data":[], "included":[]}` (aucune taxonomie créée dans un Flarum vierge) — mais pas de 404. Si 404, l'extension n'est pas activée : `docker compose exec flarum php /app/flarum extension:enable flamarkt-taxonomies`.

## Arborescence

```
taxonomies-agenda/
├── docker-compose.yml       # services flarum + db
├── .env.example             # copier en .env
├── flarum/
│   ├── Dockerfile           # image PHP 8.2 + Apache + extensions requises
│   ├── apache-flarum.conf   # vhost Apache
│   └── scripts/
│       ├── entrypoint.sh    # installe Flarum au premier démarrage
│       ├── rebuild-js.sh    # rebuild frontend Mithril
│       └── reset.sh         # reset install sans détruire le volume
├── extension/
│   └── taxonomies-agenda/  # code de l'extension, monté dans le container
├── sql/                     # dumps SQL à importer au premier démarrage MariaDB
├── ADR-001-architecture.md  # décisions de design
└── README.md                # ce fichier
```

## Dépannage

**`composer create-project` échoue au premier démarrage** → vraisemblablement un souci réseau/Packagist. Regarder les logs : `docker compose logs flarum`. Retenter avec `docker compose exec flarum /srv/scripts/reset.sh && docker compose restart flarum`.

**`flamarkt/taxonomies` ne s'installe pas avec `*`** → certaines combinaisons Flarum/flamarkt exigent une version précise. Éditer `flarum/scripts/entrypoint.sh` ligne `composer require flamarkt/taxonomies:"*"` pour épingler (ex. `^0.5`, `^0.6`). La version exacte utilisée en prod est à confirmer — `composer show flamarkt/taxonomies` sur le serveur de prod donnera la réponse.

**L'extension `taxonomies-agenda` n'apparaît pas dans l'admin** → normal tant que `extension/taxonomies-agenda/composer.json` n'existe pas. Le créer (phase 1), puis : `docker compose exec flarum bash -c 'cd /app && composer require constructions-incongrues/taxonomies-agenda:*@dev && php flarum extension:enable taxonomies-agenda'`.

**Port 8888 ou 3307 déjà pris** → changer `FLARUM_PORT` et/ou `DB_PORT` dans `.env`.

**Besoin de tout reconstruire** → `docker compose down -v && docker compose up -d --build`. Perd la base et l'install Flarum, pas le code dans `extension/` (monté).
