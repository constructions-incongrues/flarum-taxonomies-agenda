<?php

namespace Mi\AgendaTimeline\Tests\integration;

use Carbon\Carbon;
use Mi\AgendaTimeline\Support\AgendaQuery;

class AgendaQueryFacetsTest extends IntegrationTestCase
{
    /** @test */
    public function base_query_returns_only_agenda_tagged_with_event_date(): void
    {
        $this->seedFixtures();
        $agenda = new AgendaQuery($this->db);

        $ids = $agenda->baseQuery()->orderBy('discussions.id')->pluck('discussions.id')->all();
        $ids = array_map('intval', $ids);

        $this->assertSame([10, 11], $ids, 'should exclude both non-agenda discussion #12 and agenda-without-date #13');
    }

    /** @test */
    public function apply_filters_restricts_by_date_range_and_ville(): void
    {
        $this->seedFixtures();
        $agenda = new AgendaQuery($this->db);

        $q = $agenda->baseQuery();
        $agenda->applyFilters($q, ['from' => '2026-06-01', 'to' => '2026-06-30', 'ville' => 'Nantes']);

        $ids = array_map('intval', $q->pluck('discussions.id')->all());
        $this->assertSame([10], $ids);
    }

    /** @test */
    public function distinct_terms_lists_unique_villes_alpha_sorted(): void
    {
        $this->seedFixtures();
        $agenda = new AgendaQuery($this->db);

        $villes = $agenda->distinctTerms(AgendaQuery::TX_VILLE, '2026-01-01');

        // Tolerate pre-existing ville terms from dev DB clone; assert our seed is present and sorted.
        $this->assertContains('Nantes', $villes);
        $this->assertContains('Rennes', $villes);
        $sorted = $villes;
        sort($sorted);
        $this->assertSame($sorted, $villes, 'distinct terms must be alpha-sorted');
    }

    private function seedFixtures(): void
    {
        $agendaTagId = (int) $this->db->table('tags')->where('slug', 'agenda')->value('id');
        $this->assertGreaterThan(0, $agendaTagId, 'agenda tag must exist in test DB');

        // Ensure an "Off" tag for the negative case
        $offTagId = (int) $this->db->table('tags')->where('slug', 'test-off')->value('id');
        if ($offTagId === 0) {
            $offTagId = (int) $this->db->table('tags')->insertGetId([
                'name' => 'TestOff', 'slug' => 'test-off', 'position' => 99,
            ]);
        }

        $villeTaxId = (int) $this->db->table('flamarkt_taxonomies')->where('slug', AgendaQuery::TX_VILLE)->value('id');
        $this->assertGreaterThan(0, $villeTaxId, 'ville taxonomy must exist');

        $nantesTermId = $this->findOrCreateTerm($villeTaxId, 'Nantes');
        $rennesTermId = $this->findOrCreateTerm($villeTaxId, 'Rennes');

        $base = [
            'user_id' => 1,
            'comment_count' => 0,
            'participant_count' => 0,
            'post_number_index' => 0,
            'created_at' => Carbon::now(),
        ];

        $this->db->table('discussions')->insert([
            array_merge($base, ['id' => 10, 'title' => 'Concert A', 'slug' => 'concert-a', 'event_date' => '2026-06-12']),
            array_merge($base, ['id' => 11, 'title' => 'Concert B', 'slug' => 'concert-b', 'event_date' => '2026-07-05']),
            array_merge($base, ['id' => 12, 'title' => 'Off topic', 'slug' => 'off-topic', 'event_date' => '2026-06-15']),
            array_merge($base, ['id' => 13, 'title' => 'No date', 'slug' => 'no-date', 'event_date' => null]),
        ]);

        $this->db->table('discussion_tag')->insert([
            ['discussion_id' => 10, 'tag_id' => $agendaTagId],
            ['discussion_id' => 11, 'tag_id' => $agendaTagId],
            ['discussion_id' => 12, 'tag_id' => $offTagId],
            ['discussion_id' => 13, 'tag_id' => $agendaTagId],
        ]);

        $this->db->table('flamarkt_discussion_taxonomy_term')->insert([
            ['discussion_id' => 10, 'term_id' => $nantesTermId],
            ['discussion_id' => 11, 'term_id' => $rennesTermId],
        ]);
    }

    private function findOrCreateTerm(int $taxonomyId, string $name): int
    {
        $id = (int) $this->db->table('flamarkt_taxonomy_terms')
            ->where('taxonomy_id', $taxonomyId)
            ->where('name', $name)
            ->value('id');
        if ($id > 0) {
            return $id;
        }
        return (int) $this->db->table('flamarkt_taxonomy_terms')->insertGetId([
            'taxonomy_id' => $taxonomyId,
            'name' => $name,
            'slug' => strtolower($name),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
