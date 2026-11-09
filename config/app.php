<?php

declare(strict_types=1);

return [
    'name' => 'Task Board',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'db_dsn' => getenv('DB_DSN') ?: 'sqlite:' . dirname(__DIR__) . '/storage/database.sqlite',
];
