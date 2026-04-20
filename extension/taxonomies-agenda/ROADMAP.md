# Roadmap

Format Now / Next / Later. Les échéances sont indicatives ; seul le contenu de **Now** est engagé.

## Now — 0.2.0 (cible fin mai 2026)

Consolidation : rendre l'extension utilisable en production par un tiers.

| Initiative | Effort |
|---|---|
| [Tests d'intégration Flarum](#1-tests-dintégration-flarum) | ~4 j |
| [Édition d'événement](#2-édition-dévénement) | ~3 j |
| [Export iCal](#3-export-ical) | ~2 j |
| [Guide de déploiement production](#4-guide-de-déploiement-production) | ~1 j |

### 1. Tests d'intégration Flarum
Le dossier `tests/integration/` est vide. Adopter `flarum/testing`, ajouter 3 tests initiaux (`SaveEventTaxonomies`, `ListEventsController`, `AgendaQuery` facets), étendre le CI avec une job `integration-tests` exécutant MariaDB.

### 2. Édition d'événement
Aujourd'hui seule la création fonctionne via le composer. Ajouter un mode édition : diff des taxonomies dans le listener `Saving`, bouton « Éditer l'événement » dans le `DiscussionHero`, appel systématique à `SyncEventDate::run()` après mutation.

### 3. Export iCal
Routes `GET /agenda.ics` (flux complet) et `GET /d/{id}.ics` (événement seul). Dépendance : `eluceo/ical`. Bouton « Ajouter à mon calendrier » sur la page discussion.

### 4. Guide de déploiement production
`docs/DEPLOYMENT.md` : pré-requis hébergement, procédure step-by-step, configuration du tag, gestion timezone, troubleshooting. Relu par un admin externe avant merge.

---

## Next — 0.3.0 (2–3 mois)

Admin UX & opérations.

| Initiative | Effort |
|---|---|
| Panneau admin (auto-création tag, timezone par défaut) | M |
| Archive / événements passés | S |
| Notifications e-mail (J-7, J-1) | L |
| Flux RSS | S |
| Image / visuel par événement | M |

---

## Later — 6+ mois

Paris stratégiques, scope flexible.

- **Carte + géocodage** des lieux, recherche par proximité, vue carte alternative à la timeline
- **Raffinement mobile** — composer + timeline aujourd'hui desktop-first
- **Import externe** — ICS import, scraping de sources (SongKick, sites de salles)
- **Dashboard statistiques** — volume de concerts par mois/ville/artiste
- **Multi-agenda** — plusieurs tags → plusieurs timelines (concerts vs ateliers vs conférences)

---

## Risques & décisions ouvertes

- **Édition d'événement / stratégie taxonomies** : continuer en SQL brut (cohérent avec la création) ou basculer sur l'API flamarkt ? Décision à prendre avant #2.
- **Notifications e-mail** : dépend de l'infra SMTP de l'hôte ; peut basculer en Later si indisponible.

## Changelog de la roadmap

- **2026-04-19** — v0.1.0 publiée. Axes Stabilisation / Qualité & tests / Packaging marqués ✓. Ouverture de la roadmap 0.2.0.
