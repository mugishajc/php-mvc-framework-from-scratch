<?php

declare(strict_types=1);

namespace Tests\Unit;

use Framework\Database\Connection;
use Framework\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    private string $dir;
    private Connection $db;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/migrations_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->db = new Connection('sqlite::memory:');

        $this->write('001_create_projects', 'CREATE TABLE projects (id INTEGER PRIMARY KEY)', 'DROP TABLE projects');
        $this->write('002_create_notes', 'CREATE TABLE notes (id INTEGER PRIMARY KEY)', 'DROP TABLE notes');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*.php') ?: []);
        rmdir($this->dir);
    }

    private function write(string $name, string $up, string $down): void
    {
        $code = <<<PHP
        <?php
        return new class implements Framework\Database\Migration {
            public function up(Framework\Database\Connection \$db): void { \$db->statement('$up'); }
            public function down(Framework\Database\Connection \$db): void { \$db->statement('$down'); }
        };
        PHP;

        file_put_contents("{$this->dir}/$name.php", $code);
    }

    private function tables(): array
    {
        $rows = $this->db->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

        return array_column($rows, 'name');
    }

    public function testRunsPendingMigrationsInOrderOnce(): void
    {
        $migrator = new Migrator($this->db, $this->dir);

        $this->assertSame(['001_create_projects', '002_create_notes'], $migrator->migrate());
        $this->assertSame([], $migrator->migrate());
        $this->assertSame(['migrations', 'notes', 'projects'], $this->tables());
    }

    public function testNewMigrationsGoIntoANewBatch(): void
    {
        $migrator = new Migrator($this->db, $this->dir);
        $migrator->migrate();

        $this->write('003_create_tags', 'CREATE TABLE tags (id INTEGER PRIMARY KEY)', 'DROP TABLE tags');
        $this->assertSame(['003_create_tags'], $migrator->migrate());

        // Rolling back only undoes the latest batch.
        $this->assertSame(['003_create_tags'], $migrator->rollback());
        $this->assertSame(['migrations', 'notes', 'projects'], $this->tables());

        $this->assertSame(['002_create_notes', '001_create_projects'], $migrator->rollback());
        $this->assertSame(['migrations'], $this->tables());
        $this->assertSame([], $migrator->rollback());
    }

    public function testStatusReportsWhatHasRun(): void
    {
        $migrator = new Migrator($this->db, $this->dir);

        $this->assertSame(['001_create_projects' => false, '002_create_notes' => false], $migrator->status());

        $migrator->migrate();

        $this->assertSame(['001_create_projects' => true, '002_create_notes' => true], $migrator->status());
    }
}
