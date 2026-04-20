<?php

namespace Mi\AgendaTimeline\Api\Controller;

use Flarum\Http\UrlGenerator;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response;
use Mi\AgendaTimeline\Support\AgendaQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class IcalController implements RequestHandlerInterface
{
    public function __construct(
        private AgendaQuery $agenda,
        private UrlGenerator $url,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params  = $request->getQueryParams();
        $filters = (array) Arr::get($params, 'filter', []);

        $rows = $this->agenda->baseQuery()
            ->orderBy('discussions.event_date', 'asc')
            ->orderBy('discussions.id', 'asc')
            ->limit(500)
            ->get();

        $attrs   = $this->agenda->loadEventAttributes($rows);
        $forumUrl = rtrim($this->url->to('forum')->base(), '/');
        $prodId  = '-//Musiques Incongrues//Agenda Timeline//FR';
        $dtstamp = gmdate('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . $prodId,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Agenda Musiques Incongrues',
            'X-WR-TIMEZONE:Europe/Paris',
        ];

        foreach ($rows as $row) {
            $id       = (int) $row->id;
            $eventDate = $row->event_date instanceof \DateTimeInterface
                ? $row->event_date->format('Y-m-d')
                : (string) $row->event_date;

            if (!$eventDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
                continue;
            }

            $dateStamp = str_replace('-', '', $eventDate); // YYYYMMDD
            $rowAttrs  = $attrs[$id] ?? [];
            $ville     = $rowAttrs['ville'] ?? null;
            $lieu      = $rowAttrs['lieu'] ?? null;
            $artistes  = $rowAttrs['artistes'] ?? [];

            $location  = implode(' — ', array_filter([$ville, $lieu])) ?: null;

            $slug = $row->slug !== null && $row->slug !== ''
                ? $id . '-' . $row->slug
                : (string) $id;
            $discussionUrl = $this->url->to('forum')->route('discussion', ['id' => $slug]);

            $description = '';
            if ($artistes) {
                $description = implode(', ', $artistes);
            }
            if ($ville) {
                $description .= ($description ? ' — ' : '') . $ville;
            }

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:discussion-' . $id . '@' . parse_url($forumUrl, PHP_URL_HOST);
            $lines[] = 'DTSTAMP:' . $dtstamp;
            $lines[] = 'DTSTART;VALUE=DATE:' . $dateStamp;
            $lines[] = 'DTEND;VALUE=DATE:' . $dateStamp;
            $lines[] = $this->fold('SUMMARY:' . $this->escapeText((string) $row->title));
            if ($location) {
                $lines[] = $this->fold('LOCATION:' . $this->escapeText($location));
            }
            if ($description) {
                $lines[] = $this->fold('DESCRIPTION:' . $this->escapeText($description));
            }
            $lines[] = $this->fold('URL:' . $discussionUrl);
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $body = implode("\r\n", $lines) . "\r\n";

        $response = new Response();
        $response->getBody()->write($body);

        return $response
            ->withHeader('Content-Type', 'text/calendar; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="agenda-musiques-incongrues.ics"')
            ->withHeader('Cache-Control', 'no-cache, must-revalidate');
    }

    /** RFC 5545 §3.1 — fold lines longer than 75 octets. */
    private function fold(string $line): string
    {
        $out   = '';
        $bytes = 0;
        $len   = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $char = $line[$i];
            if ($bytes + strlen($char) > 75) {
                $out  .= "\r\n ";
                $bytes = 1; // leading space counts
            }
            $out   .= $char;
            $bytes += strlen($char);
        }

        return $out;
    }

    /** RFC 5545 §3.3.11 — escape TEXT values. */
    private function escapeText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace(["\r\n", "\n", "\r"], '\n', $text);
        return $text;
    }
}
