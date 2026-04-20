<?php

namespace Mi\AgendaTimeline\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Builder central pour les discussions Agenda.
 *
 * Why: évite la duplication des constantes de slug et des joins taxonomies entre
 * SyncEventDate (compute event_date) et ListEventsController (filter/serialize).
 * How to apply: utiliser baseQuery() comme point de départ, applyFilters() pour les
 * query params, loadEventAttributes() pour hydrater ville/lieu/artistes en 1 query.
 */
class AgendaQuery
{
    public const TAG_SLUG = 'agenda';

    public const TX_YEAR = 'annee';
    public const TX_MONTH = 'mois';
    public const TX_DAY = 'jour';
    public const TX_VILLE = 'ville';
    public const TX_LIEU = 'lieu';
    public const TX_PERSONNE = 'personne';

    public function __construct(private ConnectionInterface $db) {}

    /**
     * Discussions taggées "agenda" avec event_date non nul.
     */
    public function baseQuery(): Builder
    {
        return $this->db->table('discussions')
            ->join('discussion_tag', 'discussion_tag.discussion_id', '=', 'discussions.id')
            ->join('tags', 'tags.id', '=', 'discussion_tag.tag_id')
            ->where('tags.slug', self::TAG_SLUG)
            ->whereNotNull('discussions.event_date')
            ->select('discussions.*');
    }

    /**
     * Applique filter[from]/filter[to]/filter[ville]/filter[lieu]/filter[artiste].
     *
     * @param array<string,mixed> $filters
     */
    public function applyFilters(Builder $query, array $filters): void
    {
        $from = $filters['from'] ?? date('Y-m-d');
        if ($from !== null && $from !== '') {
            $query->where('discussions.event_date', '>=', $from);
        }

        if (!empty($filters['to'])) {
            $query->where('discussions.event_date', '<=', $filters['to']);
        }

        foreach ([
            'ville' => self::TX_VILLE,
            'lieu' => self::TX_LIEU,
            'artiste' => self::TX_PERSONNE,
        ] as $paramKey => $txSlug) {
            $raw = $filters[$paramKey] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            $values = array_values(array_filter(array_map('trim', explode(',', (string) $raw)), fn($v) => $v !== ''));
            if (empty($values)) {
                continue;
            }
            $query->whereExists(function ($q) use ($values, $txSlug) {
                $q->from('flamarkt_discussion_taxonomy_term as dt2')
                    ->join('flamarkt_taxonomy_terms as t2', 't2.id', '=', 'dt2.term_id')
                    ->join('flamarkt_taxonomies as tx2', 'tx2.id', '=', 't2.taxonomy_id')
                    ->whereColumn('dt2.discussion_id', 'discussions.id')
                    ->where('tx2.slug', $txSlug)
                    ->whereIn('t2.name', $values);
            });
        }
    }

    /**
     * Hydrate ville/lieu/artistes sur une collection de discussions en 1 query.
     *
     * @param Collection<int,\stdClass> $rows discussions (objets bruts)
     * @return array<int,array{ville:?string,lieu:?string,artistes:array<int,string>}>
     *   indexé par discussion_id
     */
    public function loadEventAttributes(Collection $rows): array
    {
        $ids = $rows->pluck('id')->all();
        if (empty($ids)) {
            return [];
        }

        $terms = $this->db->table('flamarkt_discussion_taxonomy_term as dt')
            ->join('flamarkt_taxonomy_terms as t', 't.id', '=', 'dt.term_id')
            ->join('flamarkt_taxonomies as tx', 'tx.id', '=', 't.taxonomy_id')
            ->whereIn('dt.discussion_id', $ids)
            ->whereIn('tx.slug', [self::TX_VILLE, self::TX_LIEU, self::TX_PERSONNE])
            ->select(['dt.discussion_id', 'tx.slug as tx_slug', 't.name as term_name'])
            ->get();

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['ville' => null, 'lieu' => null, 'artistes' => []];
        }
        foreach ($terms as $row) {
            $discId = (int) $row->discussion_id;
            $slug = $row->tx_slug;
            $name = (string) $row->term_name;
            if ($slug === self::TX_VILLE && $out[$discId]['ville'] === null) {
                $out[$discId]['ville'] = $name;
            } elseif ($slug === self::TX_LIEU && $out[$discId]['lieu'] === null) {
                $out[$discId]['lieu'] = $name;
            } elseif ($slug === self::TX_PERSONNE) {
                $out[$discId]['artistes'][] = $name;
            }
        }
        foreach ($out as &$attrs) {
            sort($attrs['artistes']);
        }
        return $out;
    }

    /**
     * Hydrate le texte du premier post (extrait) pour une collection de discussions.
     *
     * Retourne un array indexé par discussion_id → string|null.
     * Le contenu Flarum est du XML TextFormatter ; strip_tags() en extrait le texte brut.
     *
     * @param Collection<int,\stdClass> $rows
     * @return array<int,string|null>
     */
    public function loadExcerpts(Collection $rows, int $maxLength = 400): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->id] = (int) ($row->first_post_id ?? 0);
        }

        $postIds = array_values(array_filter(array_unique(array_values($map))));
        if (empty($postIds)) {
            return array_fill_keys(array_keys($map), null);
        }

        $posts = $this->db->table('posts')
            ->whereIn('id', $postIds)
            ->select(['id', 'content'])
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($map as $discId => $postId) {
            if ($postId && isset($posts[$postId])) {
                $text = trim(strip_tags((string) $posts[$postId]->content));
                $out[$discId] = $maxLength > 0 && mb_strlen($text) > $maxLength
                    ? mb_substr($text, 0, $maxLength) . '…'
                    : ($text ?: null);
            } else {
                $out[$discId] = null;
            }
        }

        return $out;
    }

    /**
     * Liste des valeurs distinctes pour une taxonomie, restreinte aux discussions
     * Agenda avec event_date >= fromDate (si fourni), triée alpha.
     *
     * @return array<int,string>
     */
    public function distinctTerms(string $taxonomySlug, ?string $fromDate = null, ?string $q = null): array
    {
        $query = $this->db->table('flamarkt_discussion_taxonomy_term as dt')
            ->join('flamarkt_taxonomy_terms as t', 't.id', '=', 'dt.term_id')
            ->join('flamarkt_taxonomies as tx', 'tx.id', '=', 't.taxonomy_id')
            ->join('discussions as d', 'd.id', '=', 'dt.discussion_id')
            ->join('discussion_tag as dtag', 'dtag.discussion_id', '=', 'd.id')
            ->join('tags', 'tags.id', '=', 'dtag.tag_id')
            ->where('tx.slug', $taxonomySlug)
            ->where('tags.slug', self::TAG_SLUG)
            ->whereNotNull('d.event_date');

        if ($fromDate !== null) {
            $query->where('d.event_date', '>=', $fromDate);
        }

        if ($q !== null && $q !== '') {
            $query->where('t.name', 'like', $q . '%');
        }

        return $query->distinct()
            ->orderBy('t.name')
            ->limit(20)
            ->pluck('t.name')
            ->map(fn($v) => (string) $v)
            ->values()
            ->all();
    }
}
