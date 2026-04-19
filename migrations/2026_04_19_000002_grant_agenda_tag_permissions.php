<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();
        $tagId = $db->table('tags')->where('slug', 'agenda')->value('id');
        if (!$tagId) {
            return;
        }

        // Members group (id=3) can start discussions in the agenda tag.
        $permission = 'tag' . $tagId . '.startDiscussion';
        $exists = $db->table('group_permission')
            ->where('group_id', 3)
            ->where('permission', $permission)
            ->exists();
        if (!$exists) {
            $db->table('group_permission')->insert([
                'group_id' => 3,
                'permission' => $permission,
            ]);
        }
    },
    'down' => function (Builder $schema) {
        $db = $schema->getConnection();
        $tagId = $db->table('tags')->where('slug', 'agenda')->value('id');
        if (!$tagId) {
            return;
        }
        $db->table('group_permission')
            ->where('group_id', 3)
            ->where('permission', 'tag' . $tagId . '.startDiscussion')
            ->delete();
    },
];
