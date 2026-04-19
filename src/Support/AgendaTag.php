<?php

namespace Mi\AgendaTimeline\Support;

use Illuminate\Database\ConnectionInterface;

/**
 * Résout l'id du tag "agenda" (slug = 'agenda') une fois par process PHP.
 *
 * Why: le tag id peut différer entre dev et prod; le slug est stable par convention.
 * How to apply: le listener interroge ::id() pour filtrer les discussions concernées.
 */
class AgendaTag
{
    public const SLUG = 'agenda';

    private static ?int $cachedId = null;
    private static bool $resolved = false;

    public static function id(ConnectionInterface $db): ?int
    {
        if (self::$resolved) {
            return self::$cachedId;
        }

        $id = $db->table('tags')->where('slug', self::SLUG)->value('id');
        self::$cachedId = $id !== null ? (int) $id : null;
        self::$resolved = true;

        return self::$cachedId;
    }

    public static function reset(): void
    {
        self::$cachedId = null;
        self::$resolved = false;
    }
}
