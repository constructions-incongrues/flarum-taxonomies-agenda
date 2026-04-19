<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasColumn('discussions', 'event_date')) {
            return;
        }
        $schema->table('discussions', function (Blueprint $table) {
            $table->date('event_date')->nullable()->default(null);
            $table->index('event_date', 'discussions_event_date_index');
        });
    },
    'down' => function (Builder $schema) {
        if (!$schema->hasColumn('discussions', 'event_date')) {
            return;
        }
        $schema->table('discussions', function (Blueprint $table) {
            $table->dropIndex('discussions_event_date_index');
            $table->dropColumn('event_date');
        });
    },
];
