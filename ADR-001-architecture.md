# ADR-001 — Extension Flarum `constructions-incongrues/taxonomies-agenda`

**Statut :** proposé, en attente de validation de Tristan
**Date :** 2026-04-19
**Auteur :** Claude (Cowork)

---

## Contexte

Le site [musiques-incongrues.net](https://www.musiques-incongrues.net) tourne sur Flarum avec le plugin `flamarkt/taxonomies`. Les événements (concerts) de l'agenda sont publiés comme des discussions portant le tag `686` ("Agenda"), auxquelles sont attachés les termes de 6 taxonomies : Personne (1), Année (3), Ville (4), Lieu (6), Mois (7), Jour (8).

Actuellement, `/t/agenda` affiche ces discussions comme n'importe quelle catégorie de forum : tri par activité récente, pas de structure temporelle, pas de filtres exploitant les taxonomies.

**Objectif** : offrir une vue timeline chronologique des événements à venir, avec filtres par ville, lieu, artiste et période, accessible publiquement, cohabitant avec la vue forum existante.

---

## Décisions

### D1. Stockage de la date : colonne dénormalisée `event_date`

**Décision.** Ajouter une colonne `event_date` (type `DATE`, nullable, indexée) sur la table `discussions`, peuplée par un hook à partir des 3 taxonomies Jour/Mois/Année.

**Alternative rejetée.** Reconstitution à la volée à chaque requête en joignant 3 fois la table des termes. Rejetée parce que :
- 50 à 200 événements à venir × filtrage/tri date = requête non-indexable, tri en mémoire PHP
- Impossible de paginer proprement côté SQL
- Code du endpoint API beaucoup plus lourd (3 sous-queries + post-processing)

**Conséquences.**
- Migration `add_event_date_to_discussions` (réversible : `down` drop colonne + index).
- Backfill initial pour les discussions Agenda existantes (script idempotent).
- Coût d'écriture : 1 query supplémentaire à chaque update taxonomies d'une discussion Agenda.
- Gain en lecture : `WHERE event_date >= CURDATE() ORDER BY event_date` indexable, O(log n).

### D2. Hook de synchronisation : double listener

**Décision.** Pour recalculer `event_date`, brancher deux listeners complémentaires :

1. `Flarum\Discussion\Event\Saved` — fire au CREATE et UPDATE du modèle Discussion
2. `Flamarkt\Taxonomies\Events\ModelTaxonomiesChanged` — fire au UPDATE des taxonomies d'un modèle existant

**Pourquoi les deux.** D'après la lecture du code flamarkt (cf. `src/Extenders/TaxonomizeModel.php:314`), `ModelTaxonomiesChanged` **ne fire PAS au CREATE**, seulement quand une discussion déjà existante voit ses taxonomies modifiées. Or le workflow n8n `agenda.post` crée la discussion + assigne les taxonomies en une seule requête API → pas d'event ModelTaxonomiesChanged → `event_date` resterait NULL si on ne se fiait qu'à ce signal.

Le listener `Discussion\Saved` attrape le cas création. `ModelTaxonomiesChanged` attrape les éditions ultérieures (changement de date, correction de lieu).

**Idempotence.** Les deux listeners exécutent la même fonction `SyncEventDate::run(Discussion $d)` — safe si les deux firent pour la même opération. La fonction :
1. Vérifie que la discussion porte le tag `686`. Sinon, `event_date = NULL` (évite que la colonne traîne sur des discussions hors agenda).
2. Charge les taxonomies 3, 7, 8 via la relation `taxonomyTerms`.
3. Parse le mois français (map statique `Janvier`→01, `Février`→02, …).
4. Compose `Y-m-d`, valide avec `DateTimeImmutable::createFromFormat`. En cas d'échec (mois absent, jour invalide), log warning et `event_date = NULL` — ne bloque pas l'écriture.
5. Compare avec la valeur actuelle et n'écrit que si changement (évite cascade d'events inutiles).

**Question ouverte.** Chronologie exacte : est-ce que `Discussion\Saved` fire *après* que les taxonomies sont persistées en DB ? À vérifier empiriquement sur le docker local avant de livrer. Si non, on décalera sur `Discussion\Renamed` ou un event plus tardif.

### D3. API custom : endpoint JSON:API dédié

**Décision.** Créer un endpoint `GET /api/agenda/events`, distinct de `/api/discussions`, retournant un format taillé pour la timeline.

**Pourquoi pas réutiliser `/api/discussions` avec des filtres.** Techniquement possible (gambits flamarkt + filtre tag), mais :
- Force à livrer tout le payload Flarum (posts, participants, lastPosted…) dont on n'a pas besoin
- Filtrage par date range pas exprimable via le gambit de tag
- Un endpoint dédié est plus facile à documenter, à cacher, et à évoluer

**Schéma de l'endpoint.**

```
GET /api/agenda/events
  ?filter[from]=2026-04-19          (date ISO, défaut = aujourd'hui)
  &filter[to]=2026-12-31             (date ISO, optionnel)
  &filter[ville]=Lyon,Paris          (noms séparés par virgule)
  &filter[lieu]=Le Périscope
  &filter[artiste]=Mopcut
  &page[limit]=50                    (défaut 50, max 200)
  &page[offset]=0
```

Réponse JSON:API avec type `agenda-events`, attributs :
```
{
  "data": [{
    "type": "agenda-events",
    "id": "<discussion_id>",
    "attributes": {
      "title": "…",
      "event_date": "2026-05-18",
      "date_display": { "jour": "18", "mois": "Mai", "annee": "2026" },
      "ville": "Lyon",
      "lieu": "Le Périscope",
      "artistes": ["Artiste 1", "Artiste 2"],
      "image_url": "https://…",          // premier ![]() du post, ou null
      "excerpt": "…",                      // 280 premiers chars, sans markdown
      "discussion_url": "https://.../d/12345-slug"
    }
  }],
  "meta": { "total": 127, "from": "2026-04-19", "to": null }
}
```

**Endpoint auxiliaire.** `GET /api/agenda/facets` renvoie la liste distincte des villes, lieux, artistes actuellement référencés sur des événements à venir — pour peupler les dropdowns de filtre côté frontend. Évite de charger 3 taxonomies complètes côté client.

**Permissions.** Guest (non connecté) peut lire les deux endpoints. Ajouter la permission `agenda.view` au rôle Guest via la config d'install, désactivable en admin pour passer l'agenda en mode privé si besoin.

### D4. Routing frontend : `/agenda` en cohabitation avec `/t/agenda`

**Décision.** Enregistrer une nouvelle route Mithril `/agenda` (ou `/agenda/:filters?`) en frontend Flarum. `/t/agenda` reste inchangé — c'est la vue forum pour les modérateurs et l'historique.

Ajouter un lien "📅 Agenda" dans le header Flarum, visible par tous, pointant vers `/agenda`. Le tag Agenda de la sidebar gauche continue de pointer vers `/t/agenda`.

**Structure de la route `/agenda`.**
- `components/AgendaPage.js` — orchestrateur, gère les filtres et la requête
- `components/AgendaFilters.js` — barre de filtres (ville, lieu, artiste, dates)
- `components/AgendaTimeline.js` — liste groupée par mois
- `components/AgendaEventCard.js` — carte événement individuelle, cliquable vers la discussion

État des filtres synchronisé dans l'URL querystring (ex : `/agenda?ville=Lyon&from=2026-05-01`) pour permettre le partage d'un lien filtré.

**Pagination.** Infinite scroll simple (IntersectionObserver sur la dernière carte), 50 événements par page.

### D5. Extraction de l'image : parsing du premier `![]()` du post

**Décision.** Pas de stockage dénormalisé de l'image. Extraction au moment de la sérialisation de la réponse API : regex `/!\[[^\]]*\]\(([^)]+)\)/` sur le contenu du premier post de la discussion, capture le premier match.

**Pourquoi pas stocker.** Ajouterait encore une colonne et un hook. Le coût de parsing est négligeable (un regex sur quelques KB de markdown au moment de la réponse API, avec cache HTTP en amont). Si ça devient un problème perf plus tard, on dénormalisera à ce moment-là.

**Fallback.** Si aucune image n'est trouvée dans le post, `image_url = null` côté API. Le frontend affiche une vignette placeholder sobre.

### D6. Package Composer

**Décision.**

- **Nom du package** : `constructions-incongrues/taxonomies-agenda`
- **Namespace PHP** : `Mi\AgendaTimeline\`
- **ID de l'extension Flarum** : `taxonomies-agenda`
- **Arborescence :**
  ```
  taxonomies-agenda/
    composer.json
    extend.php
    migrations/
      2026_04_19_000000_add_event_date_to_discussions.php
      2026_04_19_000001_backfill_event_date.php
    src/
      Listeners/
        SyncEventDate.php
      Api/
        Controller/
          ListEventsController.php
          ListFacetsController.php
        Serializer/
          AgendaEventSerializer.php
      Extension/
        ExtractImageFromPost.php
    js/
      src/forum/
        index.js
        components/
          AgendaPage.js
          AgendaFilters.js
          AgendaTimeline.js
          AgendaEventCard.js
        routes.js
      webpack.config.js
      package.json
    less/
      forum.less
    locale/
      fr.yml
      en.yml
    README.md
    LICENSE
  ```

**Dépendances.**
- `flarum/core: ^1.8` (testé en local avec `v1.8.16`)
- `flamarkt/taxonomies: ^0.1.9` (Packagist plafonne à `0.1.9` — les `^0.5` mentionnés initialement n'existent pas ; version à reconfirmer sur la prod)
- `flamarkt/backoffice: ^0.1.4` (dépendance runtime obligatoire : fournit l'`AugmentedMigrator` que taxonomies requiert ; à activer *avant* taxonomies)

**Piège connu (documenté pour la prod).** La migration `20210401_000400_create_product_term_table` de `flamarkt/taxonomies` référence la table `flamarkt_products` (de `flamarkt/shop`, non utilisée ici). Le mécanisme `when` de `flamarkt/backoffice` devrait la skipper via l'API, mais pas systématiquement en CLI. L'entrypoint docker marque manuellement cette migration comme exécutée avant `extension:enable flamarkt-taxonomies`. Même logique à prévoir en prod si l'install y passe par la CLI.

**Mode d'installation local.** En développement, le docker-compose monte l'extension via un volume symlink + `composer config repositories.taxonomies-agenda path /srv/extensions/taxonomies-agenda` + `composer require constructions-incongrues/taxonomies-agenda:*@dev`. En prod, même mécanisme ou push sur Packagist si on veut rendre le package installable ailleurs (pas prioritaire).

---

## Conséquences

**Positives.**
- Tri et filtres date SQL-indexables, scalable au-delà de 200 événements si besoin.
- Vue forum classique `/t/agenda` préservée (modération inchangée, historique accessible).
- Endpoint API dédié → évolutions futures faciles (RSS, iCal, widgets) sans casser le frontend forum.
- État des filtres dans l'URL → liens partageables, friendly to bookmarks.

**Coûts à assumer.**
- Une migration DB à gérer (avec backfill) — mitigation : migration réversible + feature flag côté admin Flarum.
- Listener `Discussion\Saved` s'exécute sur *toutes* les discussions, pas juste celles Agenda — filtrage par tag dans le listener, early-return si pas de tag 686. Coût négligeable.
- Couplage à `flamarkt/taxonomies` comme dépendance dure. Si le plugin est désinstallé, l'extension crashe. Acceptable vu que l'agenda repose entièrement dessus.

**Risques résiduels.**
- Si un admin modifie manuellement un terme via l'admin Flarum (ex : renommer "Mai" en "mai"), le hook ne fire pas automatiquement sur toutes les discussions liées. Remède : commande Artisan `agenda:rebuild-event-dates` de backfill, lançable à la main.
- Bug silencieux possible si le mois est stocké en minuscules ou sans accent par le workflow n8n. La map de conversion sera tolérante (lowercase + strip-accents avant lookup).

---

## Questions ouvertes à résoudre en phase docker

1. Chronologie exacte du firing de `Discussion\Saved` par rapport à la persistance des taxonomies lors d'un POST `/api/discussions` avec relationships (notre cas workflow n8n).
2. Version réelle de `flamarkt/taxonomies` installée sur la prod (détermine la version à épingler dans `composer.json`).
3. Version réelle de Flarum core installée sur la prod.
4. Permissions Guest actuelles : est-ce que Guest peut déjà voir les discussions Agenda et leurs taxonomies ? Si oui, notre endpoint public hérite naturellement du comportement. Sinon il faudra un endpoint "admin" avec bypass.

Ces points ne bloquent pas le démarrage ; ils seront confirmés par lecture de la conf + un test direct dès que le docker tourne avec un dump.

---

## Prochaine étape

Si cet ADR est validé, phase 0 continue avec le docker-compose local (task #3) : Flarum + MariaDB + flamarkt/taxonomies installés, avec un symlink prêt à accueillir l'extension. Puis phase 1 (MVP sans filtres).
