# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Dockerised dev environment for the Flarum extension `constructions-incongrues/taxonomies-agenda`, which renders a chronological timeline of upcoming concerts on musiques-incongrues.net. Built on top of `flamarkt/taxonomies`. See `ADR-001-architecture.md` for the design rationale.

## Environment

- Flarum runs inside the `flarum` container (Apache + PHP 8.2). The extension source at `extension/taxonomies-agenda/` is bind-mounted into `/srv/extensions/taxonomies-agenda` inside the container. PHP edits are picked up at request time; JS edits require a rebuild.
- MariaDB runs in the `db` container. Credentials: app uses `flarum:flarum`; `root:root` works too. SQL dumps placed in `./sql/` are auto-imported only on the **first** DB boot (fresh volume).
- Default admin login: `admin` / `admin1234` (override via `.env`).
- Forum is served on `http://localhost:8888` (`FLARUM_PORT` in `.env`).

## Common commands

All commands below are run from the repo root.

```bash
# Start / stop
docker compose up -d --build
docker compose down          # keep volumes
docker compose down -v       # wipe DB + Flarum install (rebuilds from scratch)

# Rebuild extension JS (webpack in prod mode) + republish assets
docker compose exec -T flarum bash -c \
  "cd /srv/extensions/taxonomies-agenda/js && npm run build && \
   cd /app && php flarum cache:clear && php flarum assets:publish"

# Run the extension's migrations (safe, idempotent)
docker compose exec flarum php /app/flarum migrate

# PHPUnit unit tests for the extension
docker compose exec -T flarum bash -c \
  "cd /srv/extensions/taxonomies-agenda && vendor/bin/phpunit --testsuite unit"

# Run a single PHPUnit test / filter
docker compose exec -T flarum bash -c \
  "cd /srv/extensions/taxonomies-agenda && vendor/bin/phpunit --filter test_rejects_invalid_month"

# Playwright E2E (requires admin creds; run from host, not container)
cd extension/taxonomies-agenda/e2e
npm install && npx playwright install chromium
AGENDA_ADMIN_USERNAME=admin AGENDA_ADMIN_PASSWORD=admin1234 npm test

# Shell inside containers
docker compose exec flarum bash
docker compose exec db mariadb -uflarum -pflarum flarum
```

When installing PHP deps (new migration deps, PHPUnit packages), run `composer install` inside the container at `/srv/extensions/taxonomies-agenda/`. The forum `composer.json` at `/app/composer.json` is separate and should not be touched for extension-local work.

## Architecture

### Data model

Events are Flarum discussions tagged `agenda`. Each event carries three categories of structured data:

1. **Date parts** stored as `flamarkt/taxonomies` terms: `jour`, `mois` (French full names, e.g. `Juin`), `annee`. `discussions.event_date` is a denormalised `DATE` column computed from those three terms by `Listener\SyncEventDate`.
2. **Location** as taxonomies `ville` and `lieu`.
3. **Artists** as the `personne` taxonomy (repeatable).

The `event_date` column is the single source of truth for filtering/ordering in queries — the taxonomy terms exist for faceting and display. Keep the two in sync: any code that creates or edits taxonomy terms on a discussion must also trigger a recompute (see below).

### Create path (composer → discussion)

`AgendaPage.onPostEvent` → `app.composer.load(EventComposer, { prefill*, tags:[agendaTag] })` → user fills form → `EventComposer.onsubmit` calls `store.createRecord('discussions').save(data)` where `data.taxonomies` is a flat array of `{slug, term}` pairs plus `relationships.tags` populated from `composer.fields.tags`.

On the server:
- Flarum creates the Discussion row, `SaveEventTaxonomies::handle` (listening to `Discussion\Event\Saving`) reads `attributes.taxonomies`, validates via `Support\EventTaxonomyValidator::validate`, and schedules an `afterSave` callback.
- The callback performs **raw** inserts into `flamarkt_taxonomy_terms` and `flamarkt_discussion_taxonomy_term`, which **bypasses** flamarkt's `ModelTaxonomiesChanged` event. This is why the callback also explicitly calls `SyncEventDate::run($discussion)` to recompute `event_date`.
- After this, the DB holds: a tagged discussion, its taxonomy rows, and a non-null `event_date`.

The UI redirects to `app.route.discussion(discussion)` after the save resolves.

### Read path (agenda page)

`GET /api/agenda/events?filter[from]=…&filter[to]=…&filter[ville]=…` → `ListEventsController` delegates query construction to `Support\AgendaQuery`:
- `baseQuery()` joins `discussions` ↔ `discussion_tag` ↔ `tags` on slug `agenda` and requires `event_date IS NOT NULL`.
- `applyFilters()` adds range filters on `event_date` and `whereExists` subqueries for ville/lieu/artiste.
- `loadEventAttributes()` hydrates ville/lieu/artistes for the result set in a single query.

`GET /api/agenda/facets` returns distinct ville/lieu/artiste lists (via `AgendaQuery::distinctTerms`) for the autocomplete datalists and filter dropdowns. The frontend caches results in a `Map` keyed by query with a 60 s TTL.

### Frontend structure

The extension bundles its own Mithril app (via webpack) into `js/dist/forum.js`:
- `forum/index.ts` registers the `/agenda` and `/agenda/new` routes and adds the nav item.
- `AgendaPage` is the top-level container. `/agenda/new` re-uses `AgendaPage` and, once the initial load resolves, auto-opens the composer with query-string prefill (`?title=&date=&ville=&lieu=&personne=`).
- `EventComposer` extends Flarum's `DiscussionComposer` and injects the agenda tag into `composer.fields.tags` during `oninit` — this is what makes `flarum/tags` emit `relationships.tags` on the JSON:API POST. Do **not** rely on `app.composer.load(…, { tags: [...] })` alone: attrs passed to `load()` are not auto-copied into `composer.fields`.

### Key invariants

- **Raw SQL inserts skip flamarkt events.** Any new code path that writes to `flamarkt_discussion_taxonomy_term` directly must also call `SyncEventDate::run($discussion)` (or go through flamarkt's model layer) or the timeline will silently drop the event.
- **The `agenda` tag must be attached** at create time, not after. `AgendaQuery::baseQuery` filters on the tag, so an untagged event disappears from the listing even if `event_date` is set.
- **Months are stored as French full names** (`Janvier`..`Décembre`). `SyncEventDate` and `EventTaxonomyValidator::VALID_MONTHS` both depend on this — keep them in sync.
- **Validation lives in `EventTaxonomyValidator`** (pure, unit-tested). `SaveEventTaxonomies` delegates to it; don't reintroduce inline validation in the listener.
