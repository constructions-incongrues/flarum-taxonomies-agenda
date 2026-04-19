<?php

namespace Mi\AgendaTimeline\Api\Controller;

use Flarum\Api\Controller\AbstractShowController;
use Illuminate\Support\Arr;
use Mi\AgendaTimeline\Api\Serializer\AgendaFacetsSerializer;
use Mi\AgendaTimeline\Support\AgendaQuery;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListFacetsController extends AbstractShowController
{
    public $serializer = AgendaFacetsSerializer::class;

    public function __construct(private AgendaQuery $agenda) {}

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $params = $request->getQueryParams();
        $filters = (array) Arr::get($params, 'filter', []);
        $from = $filters['from'] ?? null;
        $q = $filters['q'] ?? null;

        return [
            'id' => 'global',
            'villes' => $this->agenda->distinctTerms(AgendaQuery::TX_VILLE, $from, $q),
            'lieux' => $this->agenda->distinctTerms(AgendaQuery::TX_LIEU, $from, $q),
            'artistes' => $this->agenda->distinctTerms(AgendaQuery::TX_PERSONNE, $from, $q),
        ];
    }
}
