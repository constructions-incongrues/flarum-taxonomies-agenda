#!/bin/bash
# Rebuild le JS frontend de l'extension taxonomies-agenda.
# Lance ça après chaque modification du code Mithril.
#
# Usage (depuis l'hôte) :
#   docker compose exec flarum /srv/scripts/rebuild-js.sh
#
# Alternativement, en mode watch pendant le développement :
#   docker compose exec flarum bash -c "cd /srv/extensions/taxonomies-agenda/js && npm install && npm run dev"

set -euo pipefail

EXT_JS=/srv/extensions/taxonomies-agenda/js

if [ ! -d "$EXT_JS" ]; then
    echo "!!! [mi-agenda] $EXT_JS n'existe pas encore — pose un package.json côté hôte avant de rebuild."
    exit 1
fi

cd "$EXT_JS"

if [ ! -d node_modules ]; then
    echo ">>> [mi-agenda] npm install"
    npm install
fi

echo ">>> [mi-agenda] npm run build"
npm run build

echo ">>> [mi-agenda] php flarum cache:clear"
cd /app
php flarum cache:clear

echo ">>> [mi-agenda] OK — recharge le navigateur en vidant le cache (Ctrl+Shift+R)."
