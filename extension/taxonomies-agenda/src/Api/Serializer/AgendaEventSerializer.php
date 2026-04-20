<?php

namespace Mi\AgendaTimeline\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;

/**
 * Serializer pour un event agenda. Consomme un array associatif préparé par
 * ListEventsController (pas un modèle Eloquent) — l'hydration ville/lieu/artistes
 * se fait en batch côté controller pour éviter le N+1.
 *
 * Expected input array keys:
 *   id (int), title (string), event_date (string Y-m-d),
 *   ville (?string), lieu (?string), artistes (string[]),
 *   discussion_url (string)
 */
class AgendaEventSerializer extends AbstractSerializer
{
    protected $type = 'agenda-events';

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
        $date = $model['event_date'];
        [$year, $month, $day] = explode('-', $date);

        return [
            'title' => $model['title'],
            'event_date' => $date,
            'date_display' => [
                'jour' => (int) $day,
                'mois' => (int) $month,
                'annee' => (int) $year,
            ],
            'ville' => $model['ville'],
            'lieu' => $model['lieu'],
            'artistes' => $model['artistes'],
            'image_url' => null,
            'excerpt' => $model['excerpt'] ?? null,
            'discussion_url' => $model['discussion_url'],
            'user_id' => $model['user_id'] ?? null,
        ];
    }
}
