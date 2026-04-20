<?php

namespace Mi\AgendaTimeline\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Discussion\Discussion;
use Illuminate\Database\ConnectionInterface;
use Mi\AgendaTimeline\Listener\SyncEventDate;
use Mi\AgendaTimeline\Support\AgendaTag;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Input\InputOption;

class BackfillEventDatesCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('mi:agenda:backfill-dates')
            ->setDescription('Recalcule event_date sur les discussions agenda dont le champ est NULL.')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Recalcule aussi les discussions qui ont déjà une event_date.');
    }

    public function __construct(
        private ConnectionInterface $db,
        private SyncEventDate $sync,
    ) {
        parent::__construct();
    }

    protected function fire(): void
    {
        AgendaTag::reset();
        $agendaTagId = AgendaTag::id($this->db);

        if ($agendaTagId === null) {
            $this->error('Tag "agenda" introuvable — vérifiez que le tag existe (slug: agenda).');
            return;
        }

        $all = (bool) $this->input->getOption('all');

        $query = $this->db->table('discussions')
            ->join('discussion_tag', 'discussion_tag.discussion_id', '=', 'discussions.id')
            ->where('discussion_tag.tag_id', $agendaTagId)
            ->whereNull('discussions.deleted_at')
            ->select('discussions.id');

        if (!$all) {
            $query->whereNull('discussions.event_date');
        }

        $ids = $query->pluck('discussions.id')->all();
        $total = count($ids);

        if ($total === 0) {
            $this->info('Aucune discussion à traiter.');
            return;
        }

        $this->info("Traitement de {$total} discussion(s)…");

        $updated = 0;
        $skipped = 0;
        $errors  = 0;

        foreach ($ids as $id) {
            try {
                $discussion = Discussion::find((int) $id);
                if (!$discussion) {
                    $skipped++;
                    continue;
                }

                $before = $discussion->event_date;
                $this->sync->run($discussion);
                $discussion->refresh();
                $after = $discussion->event_date;

                if ($after !== null && $after !== $before) {
                    $dateStr = $after instanceof \DateTimeInterface ? $after->format('Y-m-d') : (string) $after;
                    $this->line("  ✓ #{$id} → {$dateStr}");
                    $updated++;
                } else {
                    $this->line("  – #{$id} ignorée (taxonomies manquantes ou date inchangée)");
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ #{$id} erreur : " . $e->getMessage());
                $errors++;
            }
        }

        $this->info('');
        $this->info("Terminé : {$updated} mise(s) à jour, {$skipped} ignorée(s), {$errors} erreur(s).");
    }
}
