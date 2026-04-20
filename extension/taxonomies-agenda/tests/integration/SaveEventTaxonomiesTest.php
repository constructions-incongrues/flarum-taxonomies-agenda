<?php

namespace Mi\AgendaTimeline\Tests\integration;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event\Saving;
use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Mi\AgendaTimeline\Listener\SaveEventTaxonomies;
use Mi\AgendaTimeline\Listener\SyncEventDate;
use Mi\AgendaTimeline\Support\AgendaQuery;
use Psr\Log\NullLogger;

class SaveEventTaxonomiesTest extends IntegrationTestCase
{
    /** @test */
    public function rejects_invalid_month_via_validator(): void
    {
        $listener = $this->makeListener();
        $event = $this->makeSavingEvent(20, [
            ['slug' => AgendaQuery::TX_YEAR, 'term' => '2026'],
            ['slug' => AgendaQuery::TX_MONTH, 'term' => 'NotAMonth'],
            ['slug' => AgendaQuery::TX_DAY, 'term' => '12'],
        ]);

        $this->expectException(ValidationException::class);
        $listener->handle($event);
    }

    /** @test */
    public function persists_taxonomies_and_syncs_event_date_on_save(): void
    {
        $agendaTagId = (int) $this->db->table('tags')->where('slug', 'agenda')->value('id');
        $this->assertGreaterThan(0, $agendaTagId);

        // Insert discussion manually (simulating what Flarum core would do)
        $discussionId = (int) $this->db->table('discussions')->insertGetId([
            'title' => 'Concert test',
            'slug' => 'concert-test',
            'user_id' => 1,
            'comment_count' => 0,
            'participant_count' => 0,
            'post_number_index' => 0,
            'created_at' => Carbon::now(),
        ]);
        $this->db->table('discussion_tag')->insert([
            'discussion_id' => $discussionId,
            'tag_id' => $agendaTagId,
        ]);

        $discussion = Discussion::find($discussionId);
        $this->assertNotNull($discussion);

        $listener = $this->makeListener();
        $event = new Saving($discussion, $this->fakeUser(), [
            'attributes' => [
                'taxonomies' => [
                    ['slug' => AgendaQuery::TX_YEAR, 'term' => '2026'],
                    ['slug' => AgendaQuery::TX_MONTH, 'term' => 'Juin'],
                    ['slug' => AgendaQuery::TX_DAY, 'term' => '12'],
                    ['slug' => AgendaQuery::TX_VILLE, 'term' => 'TestCity'],
                ],
            ],
        ]);

        // handle() schedules afterSave callbacks on the model. Drain and invoke
        // them directly — calling $discussion->save() would require a full Flarum
        // container (SettingsRepositoryInterface, etc.).
        $listener->handle($event);
        $this->fireAfterSaveCallbacks($discussion);

        $eventDate = $this->db->table('discussions')->where('id', $discussionId)->value('event_date');
        $this->assertSame('2026-06-12', $eventDate instanceof \DateTimeInterface ? $eventDate->format('Y-m-d') : (string) $eventDate);

        $termNames = $this->db->table('flamarkt_discussion_taxonomy_term as dt')
            ->join('flamarkt_taxonomy_terms as t', 't.id', '=', 'dt.term_id')
            ->join('flamarkt_taxonomies as tx', 'tx.id', '=', 't.taxonomy_id')
            ->where('dt.discussion_id', $discussionId)
            ->pluck('t.name', 'tx.slug')
            ->all();

        $this->assertSame('2026', $termNames[AgendaQuery::TX_YEAR] ?? null);
        $this->assertSame('Juin', $termNames[AgendaQuery::TX_MONTH] ?? null);
        $this->assertSame('12', $termNames[AgendaQuery::TX_DAY] ?? null);
        $this->assertSame('TestCity', $termNames[AgendaQuery::TX_VILLE] ?? null);
    }

    private function fireAfterSaveCallbacks(Discussion $discussion): void
    {
        $ref = new \ReflectionProperty($discussion, 'afterSaveCallbacks');
        $ref->setAccessible(true);
        $callbacks = $ref->getValue($discussion);
        $ref->setValue($discussion, []);
        foreach ($callbacks as $cb) {
            $cb($discussion);
        }
    }

    private function makeListener(): SaveEventTaxonomies
    {
        $sync = new SyncEventDate($this->db, new NullLogger());
        return new SaveEventTaxonomies($this->db, $sync);
    }

    private function makeSavingEvent(int $discussionId, array $taxonomies): Saving
    {
        $discussion = new Discussion();
        $discussion->id = $discussionId;
        return new Saving($discussion, $this->fakeUser(), [
            'attributes' => ['taxonomies' => $taxonomies],
        ]);
    }

    private function fakeUser(): User
    {
        $user = User::find(1) ?? new User();
        return $user;
    }
}
