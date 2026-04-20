# Agenda Timeline

[![CI](https://github.com/constructions-incongrues/taxonomies-agenda/actions/workflows/ci.yml/badge.svg)](https://github.com/constructions-incongrues/taxonomies-agenda/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Flarum](https://img.shields.io/badge/Flarum-%5E1.8-orange.svg)](https://flarum.org)

Timeline chronologique des concerts et événements pour [musiques-incongrues.net](https://musiques-incongrues.net), basée sur [flamarkt/taxonomies](https://github.com/flamarkt/taxonomies).

Chaque discussion taguée `agenda` devient un événement daté (jour/mois/année), situé (ville/lieu) et attribué (personne). La page `/agenda` affiche la timeline avec facettes et filtres ; les contributeurs publient via un composer dédié.

## Installation

```bash
composer config repositories.taxonomies-agenda vcs https://github.com/constructions-incongrues/taxonomies-agenda
composer require constructions-incongrues/taxonomies-agenda:dev-main
php flarum migrate
php flarum cache:clear
```

Pour une version stable (après première release) :

```bash
composer require constructions-incongrues/taxonomies-agenda:^0.1
```

## Configuration

1. **Créer le tag `agenda`** dans l'admin Flarum avec le slug exact `agenda` (requis — l'extension filtre dessus).
2. **Permissions** : la migration `2026_04_19_000002_grant_agenda_tag_permissions` pose automatiquement `tag{id}.startDiscussion` pour le groupe Members (id=3). Ajustez via l'admin si vous visez un autre groupe.
3. **Taxonomies** : les 6 taxonomies (`jour`, `mois`, `annee`, `ville`, `lieu`, `personne`) sont créées au premier `migrate`. Le composer peuple les termes au fur et à mesure des événements.

## Dépendances

- [flarum/core](https://github.com/flarum/core) `^1.8`
- [flamarkt/taxonomies](https://github.com/flamarkt/taxonomies) `^0.1.9`
- [flamarkt/backoffice](https://github.com/flamarkt/backoffice) `^0.1.4`

## Fonctionnalités

- **Composer d'événement** (`/agenda/new`) — titre, date, ville, lieu, description
- **Timeline** (`/agenda`) — groupement par mois/année, filtres par ville/lieu/année
- **Facettes** — suggestions live avec cache 60 s et debounce 300 ms
- **Validation serveur** — mois/jour/année cohérents, freeform normalisé (max 120 chars)
- **Redirection post-création** — vers l'URL de la discussion créée
- **i18n** — français (par défaut) et anglais

## Développement

```bash
# Build JS
cd js && npm ci && npm run build

# Tests unitaires PHP
composer install
composer test

# Tests E2E (Playwright)
cd e2e && npm ci
AGENDA_BASE_URL=http://localhost:8888 \
AGENDA_ADMIN_USERNAME=admin \
AGENDA_ADMIN_PASSWORD=password \
  npm test
```

## Contributing

Les pull requests sont bienvenues. Merci de :

- Ajouter/mettre à jour les tests unitaires pour toute logique métier (`tests/unit/`)
- Respecter le format du `CHANGELOG.md` (Keep a Changelog)
- Vérifier `composer test` et le build JS avant de pousser

## License

MIT — voir [LICENSE](LICENSE).
