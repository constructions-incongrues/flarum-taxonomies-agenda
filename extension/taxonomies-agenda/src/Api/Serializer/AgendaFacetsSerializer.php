<?php

namespace Mi\AgendaTimeline\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;

/**
 * Serializer pour les facets agenda (villes/lieux/artistes distincts).
 *
 * Expected input array keys:
 *   id (string, toujours 'global'),
 *   villes (string[]), lieux (string[]), artistes (string[])
 */
class AgendaFacetsSerializer extends AbstractSerializer
{
    protected $type = 'agenda-facets';

    public function getId($model)
    {
        return (string) $model['id'];
    }

    /**
     * @param array $model
     * @return array
     */
    protected function getDefaultAttributes($model): array
    {
        return [
            'villes' => $model['villes'],
            'lieux' => $model['lieux'],
            'artistes' => $model['artistes'],
        ];
    }
}
