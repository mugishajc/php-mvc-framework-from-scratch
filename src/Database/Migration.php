<?php

declare(strict_types=1);

namespace Framework\Database;

interface Migration
{
    public function up(Connection $db): void;

    public function down(Connection $db): void;
}
