<?php

declare(strict_types=1);

namespace Framework\Database;

use RuntimeException;

/**
 * Runs migration files from a directory in filename order and records
 * them in a `migrations` table so each one runs only once.
 *
 * A migration file must return an object implementing Migration.
 */
final class Migrator
{
    public function __construct(private Connection $db, private string $directory)
    {
    }

    /**
     * Runs every pending migration as one batch.
     *
     * @return list<string> names of the migrations that ran
     */
    public function migrate(): array
    {
        $this->ensureRepository();

        $ran = array_column($this->db->select('SELECT name FROM migrations'), 'name');
        $pending = array_diff(array_keys($this->files()), $ran);

        if ($pending === []) {
            return [];
        }

        $batch = $this->lastBatch() + 1;
        $files = $this->files();
        $executed = [];

        foreach ($pending as $name) {
            $migration = $this->load($files[$name]);

            $this->db->transaction(function (Connection $db) use ($migration, $name, $batch): void {
                $migration->up($db);
                $db->table('migrations')->insert([
                    'name' => $name,
                    'batch' => $batch,
                    'ran_at' => gmdate('Y-m-d H:i:s'),
                ]);
            });

            $executed[] = $name;
        }

        return $executed;
    }

    /**
     * Reverts the most recent batch.
     *
     * @return list<string> names of the migrations that were rolled back
     */
    public function rollback(): array
    {
        $this->ensureRepository();

        $batch = $this->lastBatch();
        if ($batch === 0) {
            return [];
        }

        $rows = $this->db->table('migrations')->where('batch', $batch)->orderBy('name', 'desc')->get();
        $files = $this->files();
        $reverted = [];

        foreach ($rows as $row) {
            $name = (string) $row['name'];
            if (!isset($files[$name])) {
                throw new RuntimeException("Migration file for [$name] is missing.");
            }

            $migration = $this->load($files[$name]);

            $this->db->transaction(function (Connection $db) use ($migration, $name): void {
                $migration->down($db);
                $db->table('migrations')->where('name', $name)->delete();
            });

            $reverted[] = $name;
        }

        return $reverted;
    }

    /**
     * @return array<string, bool> migration name => whether it has run
     */
    public function status(): array
    {
        $this->ensureRepository();
        $ran = array_column($this->db->select('SELECT name FROM migrations'), 'name');

        $status = [];
        foreach (array_keys($this->files()) as $name) {
            $status[$name] = in_array($name, $ran, true);
        }

        return $status;
    }

    private function ensureRepository(): void
    {
        $this->db->statement(
            'CREATE TABLE IF NOT EXISTS migrations (
                name VARCHAR(255) PRIMARY KEY,
                batch INTEGER NOT NULL,
                ran_at VARCHAR(19) NOT NULL
            )'
        );
    }

    private function lastBatch(): int
    {
        $row = $this->db->selectOne('SELECT MAX(batch) AS batch FROM migrations');

        return (int) ($row['batch'] ?? 0);
    }

    /**
     * @return array<string, string> name => path, sorted by name
     */
    private function files(): array
    {
        $files = [];
        foreach (glob(rtrim($this->directory, '/') . '/*.php') ?: [] as $path) {
            $files[basename($path, '.php')] = $path;
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    private function load(string $path): Migration
    {
        $migration = require $path;

        if (!$migration instanceof Migration) {
            throw new RuntimeException("Migration [$path] must return an instance of " . Migration::class);
        }

        return $migration;
    }
}
