#!/bin/bash
# Relance une installation propre de Flarum (détruit la DB existante).
# Usage (depuis l'hôte) :
#   docker compose exec flarum /srv/scripts/reset.sh
#
# À utiliser si l'install initiale a planté ou si tu veux repartir de zéro
# sans détruire le volume /app (rapide).

set -euo pipefail

echo ">>> [mi-agenda] reset de l'installation Flarum"

# On drop le marker pour forcer entrypoint.sh à retenter l'install au prochain démarrage.
rm -f /app/.flarum_installed

# On vide config.php pour que `php flarum install` ne rechigne pas.
rm -f /app/config.php

# On drop et recrée la base (via le client mysql embarqué).
mysql -h "$FLARUM_DB_HOST" -u root -p"${DB_ROOT_PASSWORD:-root}" <<SQL
DROP DATABASE IF EXISTS \`$FLARUM_DB_DATABASE\`;
CREATE DATABASE \`$FLARUM_DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL ON \`$FLARUM_DB_DATABASE\`.* TO '$FLARUM_DB_USER'@'%';
FLUSH PRIVILEGES;
SQL

echo ">>> [mi-agenda] fait. Redémarre le container : docker compose restart flarum"
