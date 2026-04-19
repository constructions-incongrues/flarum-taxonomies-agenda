<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $conn = $schema->getConnection();
        $existing = $conn->select("SHOW INDEX FROM discussions WHERE Key_name = 'discussions_event_date_id_index'");
        if (empty($existing)) {
            $schema->table('discussions', function (Blueprint $table) {
                $table->index(['event_date', 'id'], 'discussions_event_date_id_index');
            });
        }
    },
    'down' => function (Builder $schema) {
        $conn = $schema->getConnection();
        $existing = $conn->select("SHOW INDEX FROM discussions WHERE Key_name = 'discussions_event_date_id_index'");
        if (!empty($existing)) {
            $schema->table('discussions', function (Blueprint $table) {
                $table->dropIndex('discussions_event_date_id_index');
            });
        }
    },
];
