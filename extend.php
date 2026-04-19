<?php

use Flamarkt\Taxonomies\Events\ModelTaxonomiesChanged;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event\Saved;
use Flarum\Extend;
use Mi\AgendaTimeline\Api\Controller\ListEventsController;
use Mi\AgendaTimeline\Api\Controller\ListFacetsController;
use Mi\AgendaTimeline\Listener\SyncEventDate;

return [
    (new Extend\Model(Discussion::class))
        ->cast('event_date', 'date'),

    (new Extend\Event())
        ->listen(Saved::class, [SyncEventDate::class, 'handleSaved'])
        ->listen(ModelTaxonomiesChanged::class, [SyncEventDate::class, 'handleTaxonomiesChanged'])
        ->listen(Flarum\Discussion\Event\Saving::class, [Mi\AgendaTimeline\Listener\SaveEventTaxonomies::class, 'handle']),

    (new Extend\Routes('api'))
        ->get('/agenda/events', 'mi.agenda.events.index', ListEventsController::class)
        ->get('/agenda/facets', 'mi.agenda.facets.show', ListFacetsController::class),

    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less')
        ->route('/agenda', 'agenda')
        ->route('/agenda/new', 'agenda.new'),

    new Extend\Locales(__DIR__ . '/locale'),
];
