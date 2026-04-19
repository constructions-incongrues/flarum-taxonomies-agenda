<?php

namespace Mi\AgendaTimeline\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\UrlGenerator;
use Illuminate\Support\Arr;
use Mi\AgendaTimeline\Api\Serializer\AgendaEventSerializer;
use Mi\AgendaTimeline\Support\AgendaQuery;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListEventsController extends AbstractListController
{
    public $serializer = AgendaEventSerializer::class;

    public function __construct(
        private AgendaQuery $agenda,
        private UrlGenerator $url,
    ) {}

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $params = $request->getQueryParams();
        $filters = (array) Arr::get($params, 'filter', []);

        $limitRaw = (int) Arr::get($params, 'page.limit', 50);
        $limit = max(1, min(200, $limitRaw ?: 50));
        $offsetRaw = (int) Arr::get($params, 'page.offset', 0);
        $offset = max(0, $offsetRaw);

        $from = $filters['from'] ?? date('Y-m-d');
        $to = $filters['to'] ?? null;

        $countQuery = $this->agenda->baseQuery();
        $this->agenda->applyFilters($countQuery, $filters);
        $total = (int) $countQuery->count();

        $listQuery = $this->agenda->baseQuery();
        $this->agenda->applyFilters($listQuery, $filters);
        $rows = $listQuery
            ->orderBy('discussions.event_date', 'asc')
            ->orderBy('discussions.id', 'asc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        $attrs = $this->agenda->loadEventAttributes($rows);

        $items = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $eventDate = $row->event_date instanceof \DateTimeInterface
                ? $row->event_date->format('Y-m-d')
                : (string) $row->event_date;

            $items[] = [
                'id' => $id,
                'title' => (string) $row->title,
                'event_date' => $eventDate,
                'ville' => $attrs[$id]['ville'] ?? null,
                'lieu' => $attrs[$id]['lieu'] ?? null,
                'artistes' => $attrs[$id]['artistes'] ?? [],
                'discussion_url' => $this->url->to('forum')->route('discussion', [
                    'id' => $row->slug !== null && $row->slug !== ''
                        ? $id.'-'.$row->slug
                        : (string) $id,
                ]),
            ];
        }

        $document->setMeta([
            'total' => $total,
            'from' => $from,
            'to' => $to,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return $items;
    }
}
