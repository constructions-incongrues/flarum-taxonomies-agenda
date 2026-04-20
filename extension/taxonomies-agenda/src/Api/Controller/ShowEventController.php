<?php

namespace Mi\AgendaTimeline\Api\Controller;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\UrlGenerator;
use Illuminate\Support\Arr;
use Mi\AgendaTimeline\Api\Serializer\AgendaEventSerializer;
use Mi\AgendaTimeline\Support\AgendaQuery;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;
use Tobscure\JsonApi\Exception\InvalidParameterException;

class ShowEventController extends AbstractShowController
{
    public $serializer = AgendaEventSerializer::class;

    public function __construct(
        private AgendaQuery $agenda,
        private UrlGenerator $url,
    ) {}

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $id = (int) Arr::get($request->getAttribute('routeParameters'), 'id');

        $row = $this->agenda->baseQuery()
            ->where('discussions.id', $id)
            ->first();

        if (!$row) {
            throw new InvalidParameterException("Event {$id} not found.");
        }

        $collection = collect([$row]);
        $attrs    = $this->agenda->loadEventAttributes($collection);
        $excerpts = $this->agenda->loadExcerpts($collection);
        $rowId = (int) $row->id;

        $eventDate = $row->event_date instanceof \DateTimeInterface
            ? $row->event_date->format('Y-m-d')
            : (string) $row->event_date;

        return [
            'id' => $rowId,
            'title' => (string) $row->title,
            'event_date' => $eventDate,
            'user_id' => (int) $row->user_id,
            'ville' => $attrs[$rowId]['ville'] ?? null,
            'lieu' => $attrs[$rowId]['lieu'] ?? null,
            'artistes' => $attrs[$rowId]['artistes'] ?? [],
            'excerpt' => $excerpts[$rowId] ?? null,
            'discussion_url' => $this->url->to('forum')->route('discussion', [
                'id' => $row->slug !== null && $row->slug !== ''
                    ? $rowId . '-' . $row->slug
                    : (string) $rowId,
            ]),
        ];
    }
}
