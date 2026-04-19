<?php

namespace Mi\AgendaTimeline\Listener;

use Flarum\Discussion\Event\Saving;
use Flarum\Foundation\ValidationException;
use Illuminate\Support\Arr;
use Illuminate\Database\ConnectionInterface;
use Carbon\Carbon;
use Mi\AgendaTimeline\Support\EventTaxonomyValidator;

class SaveEventTaxonomies
{
    public function __construct(protected ConnectionInterface $db, protected SyncEventDate $syncEventDate) {}

    public function handle(Saving $event): void
    {
        $attributes = Arr::get($event->data, 'attributes', []);

        if (!isset($attributes['taxonomies'])) {
            return;
        }

        $taxonomies = $attributes['taxonomies'];
        EventTaxonomyValidator::validate($taxonomies);
        $discussion = $event->discussion;

        // We'll use a post-save callback to ensure the discussion has an ID
        $event->discussion->afterSave(function ($discussion) use ($taxonomies) {
            $this->syncTaxonomies($discussion, $taxonomies);
            // Raw inserts skip flamarkt's ModelTaxonomiesChanged event, so recompute explicitly.
            $this->syncEventDate->run($discussion);
        });
    }

    protected function syncTaxonomies($discussion, array $taxonomies): void
    {
        foreach ($taxonomies as $item) {
            $txSlug = Arr::get($item, 'slug');
            $termName = Arr::get($item, 'term');

            if (!$txSlug || !$termName) {
                continue;
            }

            // Find taxonomy ID
            $taxonomyId = $this->db->table('flamarkt_taxonomies')
                ->where('slug', $txSlug)
                ->value('id');

            if (!$taxonomyId) {
                continue;
            }

            // Find or create term
            $termId = $this->db->table('flamarkt_taxonomy_terms')
                ->where('taxonomy_id', $taxonomyId)
                ->where('name', $termName)
                ->value('id');

            if (!$termId) {
                // We might want to restrict term creation, but for now we'll assume it's allowed
                // or that terms are pre-existing. Since the user said Ville/Lieu are optional,
                // and for Date we have fixed months, it should be fine.
                $termId = $this->db->table('flamarkt_taxonomy_terms')->insertGetId([
                    'taxonomy_id' => $taxonomyId,
                    'name' => $termName,
                    'slug' => $this->slugify($termName),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            // Link term to discussion
            // We first clear existing terms for this taxonomy to allow updates
            $this->db->table('flamarkt_discussion_taxonomy_term as dt')
                ->join('flamarkt_taxonomy_terms as t', 't.id', '=', 'dt.term_id')
                ->where('dt.discussion_id', $discussion->id)
                ->where('t.taxonomy_id', $taxonomyId)
                ->delete();

            $this->db->table('flamarkt_discussion_taxonomy_term')->insert([
                'discussion_id' => $discussion->id,
                'term_id' => $termId,
            ]);
        }
    }

    protected function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        if (empty($text)) {
            return 'n-a';
        }
        return $text;
    }
}
