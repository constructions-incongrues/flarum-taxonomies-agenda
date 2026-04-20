# Changelog

Toutes les modifications notables sont documentées ici. Ce projet suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.3] - 2026-04-20

### Corrigé
- Workflow release : sécurise l'extraction des notes de release quand
  `CHANGELOG.md` est absent.

## [0.1.0] - 2026-04-19

### Ajouté
- Composer d'événement (`/agenda/new`) avec prefill via query string (`title`, `date`, `ville`, `lieu`)
- Timeline `/agenda` groupée par mois/année avec filtres (ville, lieu, année) et facettes
- 6 taxonomies : `jour`, `mois` (français), `annee`, `ville`, `lieu`, `personne`
- Colonne dénormalisée `discussions.event_date` synchronisée par `SyncEventDate`
- Validation serveur (`EventTaxonomyValidator`) — mois FR, jour 1–31, année ±5/+20, freeform max 120 chars
- Index composite `(event_date, id)` pour requêtes paginées
- Migration granting `tag.startDiscussion` au groupe Members pour le tag `agenda`
- Cache facettes (60 s) + debounce (300 ms) côté composer
- Redirection automatique vers l'URL de la discussion après création
- Traductions françaises et anglaises (errors, labels, boutons)
- Suite de tests unitaires PHPUnit 10 (`tests/unit/EventTaxonomyValidatorTest.php`)
- Scénarios E2E Playwright (`e2e/tests/agenda.spec.ts`)

[Unreleased]: https://github.com/constructions-incongrues/taxonomies-agenda/compare/v0.2.3...HEAD
[0.2.3]: https://github.com/constructions-incongrues/taxonomies-agenda/releases/tag/v0.2.3
[0.1.0]: https://github.com/constructions-incongrues/taxonomies-agenda/releases/tag/v0.1.0
