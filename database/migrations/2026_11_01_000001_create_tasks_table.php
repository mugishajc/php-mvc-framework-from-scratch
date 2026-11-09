<?php

declare(strict_types=1);

use Framework\Database\Connection;
use Framework\Database\Migration;

return new class implements Migration {
    public function up(Connection $db): void
    {
        $db->statement(
            "CREATE TABLE tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(120) NOT NULL,
                description TEXT NULL,
                status VARCHAR(10) NOT NULL DEFAULT 'todo',
                created_at VARCHAR(19) NOT NULL,
                updated_at VARCHAR(19) NOT NULL
            )"
        );
        $db->statement('CREATE INDEX tasks_status_index ON tasks (status)');
    }

    public function down(Connection $db): void
    {
        $db->statement('DROP TABLE tasks');
    }
};
