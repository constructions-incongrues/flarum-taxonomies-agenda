<?php

namespace Mi\AgendaTimeline\Listener;

use Flamarkt\Taxonomies\Events\ModelTaxonomiesChanged;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event\Saved;
use Illuminate\Database\ConnectionInterface;
use Mi\AgendaTimeline\Support\AgendaQuery;
use Mi\AgendaTimeline\Support\AgendaTag;
use Psr\Log\LoggerInterface;

/**
 * Recalcule discussions.event_date depuis les taxonomies Année/Mois/Jour.
 *
 * Branché sur deux events complémentaires :
 *   - Flarum\Discussion\Event\Saved              : CREATE + UPDATE de la discussion
 *   - Flamarkt\Taxonomies\Events\ModelTaxonomiesChanged : UPDATE ultérieur des termes
 *
 * Idempotent : n'écrit que si la valeur calculée diffère de la colonne actuelle.
 */
class SyncEventDate
{
    /** Slugs des taxonomies attendues — source of truth : AgendaQuery. */
    public const TAXONOMY_YEAR = AgendaQuery::TX_YEAR;
    public const TAXONOMY_MONTH = AgendaQuery::TX_MONTH;
    public const TAXONOMY_DAY = AgendaQuery::TX_DAY;

    private const MONTH_MAP = [
        'janvier' => 1,
        'fevrier' => 2,
        'mars' => 3,
        'avril' => 4,
        'mai' => 5,
        'juin' => 6,
        'juillet' => 7,
        'aout' => 8,
        'septembre' => 9,
        'octobre' => 10,
        'novembre' => 11,
        'decembre' => 12,
    ];

    public function __construct(
        private ConnectionInterface $db,
        private LoggerInterface $log,
    ) {}

    public function handleSaved(Saved $event): void
    {
        $this->run($event->discussion);
    }

    public function handleTaxonomiesChanged(ModelTaxonomiesChanged $event): void
    {
        if ($event->model instanceof Discussion) {
            $this->run($event->model);
        }
    }

    public function run(Discussion $discussion): void
    {
        $current = $discussion->event_date;
        $new = $this->compute($discussion);

        $currentNormalized = $current instanceof \DateTimeInterface
            ? $current->format('Y-m-d')
            : ($current ?: null);

        if ($currentNormalized === $new) {
            return;
        }

        $this->db->table('discussions')
            ->where('id', $discussion->id)
            ->update(['event_date' => $new]);

        $discussion->event_date = $new;
    }

    private function compute(Discussion $discussion): ?string
    {
        $agendaTagId = AgendaTag::id($this->db);
        if ($agendaTagId === null) {
            return null;
        }

        $hasAgendaTag = $this->db->table('discussion_tag')
            ->where('discussion_id', $discussion->id)
            ->where('tag_id', $agendaTagId)
            ->exists();

        if (!$hasAgendaTag) {
            return null;
        }

        $terms = $this->loadTermsBySlug($discussion->id);

        $year = $this->parseYear($terms[self::TAXONOMY_YEAR] ?? null);
        $month = $this->parseMonth($terms[self::TAXONOMY_MONTH] ?? null);
        $day = $this->parseDay($terms[self::TAXONOMY_DAY] ?? null);

        if ($year === null || $month === null || $day === null) {
            $this->log->info('taxonomies-agenda: date partielle/invalide, event_date=NULL', [
                'discussion_id' => $discussion->id,
                'year' => $terms[self::TAXONOMY_YEAR] ?? null,
                'month' => $terms[self::TAXONOMY_MONTH] ?? null,
                'day' => $terms[self::TAXONOMY_DAY] ?? null,
            ]);
            return null;
        }

        if (!checkdate($month, $day, $year)) {
            $this->log->info('taxonomies-agenda: date invalide (checkdate), event_date=NULL', [
                'discussion_id' => $discussion->id,
                'ymd' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            ]);
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Retourne ['annee' => '2026', 'mois' => 'Mai', 'jour' => '18'] — premier terme
     * rencontré par taxonomie (si plusieurs termes par taxonomie, on prend le premier).
     *
     * @return array<string,string>
     */
    private function loadTermsBySlug(int $discussionId): array
    {
        $rows = $this->db->table('flamarkt_discussion_taxonomy_term as dt')
            ->join('flamarkt_taxonomy_terms as t', 't.id', '=', 'dt.term_id')
            ->join('flamarkt_taxonomies as tx', 'tx.id', '=', 't.taxonomy_id')
            ->where('dt.discussion_id', $discussionId)
            ->whereIn('tx.slug', [self::TAXONOMY_YEAR, self::TAXONOMY_MONTH, self::TAXONOMY_DAY])
            ->select(['tx.slug as tx_slug', 't.name as term_name'])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            if (!isset($result[$row->tx_slug])) {
                $result[$row->tx_slug] = (string) $row->term_name;
            }
        }
        return $result;
    }

    private function parseYear(?string $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        if (!preg_match('/^\d{4}$/', trim($raw))) {
            return null;
        }
        $y = (int) $raw;
        return ($y >= 1900 && $y <= 2100) ? $y : null;
    }

    private function parseMonth(?string $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        $key = $this->normalize($raw);
        return self::MONTH_MAP[$key] ?? null;
    }

    private function parseDay(?string $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim($raw);
        if (!preg_match('/^\d{1,2}$/', $raw)) {
            return null;
        }
        $d = (int) $raw;
        return ($d >= 1 && $d <= 31) ? $d : null;
    }

    private function normalize(string $s): string
    {
        $s = trim($s);
        if (function_exists('mb_strtolower')) {
            $s = mb_strtolower($s, 'UTF-8');
        } else {
            $s = strtolower($s);
        }
        $stripped = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return $stripped !== false ? $stripped : $s;
    }
}
