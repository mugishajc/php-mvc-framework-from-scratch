<?php

declare(strict_types=1);

namespace App\Repositories;

use Framework\Database\Connection;

final class TaskRepository
{
    public const STATUSES = [
        'todo' => 'To do',
        'doing' => 'In progress',
        'done' => 'Done',
    ];

    public function __construct(private Connection $db)
    {
    }

    /**
     * Tasks grouped by status column, oldest first within each column.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function board(): array
    {
        $board = array_fill_keys(array_keys(self::STATUSES), []);

        $rows = $this->db->table('tasks')->orderBy('created_at')->orderBy('id')->get();
        foreach ($rows as $row) {
            $board[$row['status']][] = $row;
        }

        return $board;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(?string $status = null): array
    {
        $query = $this->db->table('tasks')->orderBy('id');
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->table('tasks')->find($id);
    }

    public function create(string $title, ?string $description = null): int
    {
        $now = self::now();

        return $this->db->table('tasks')->insert([
            'title' => $title,
            'description' => $description,
            'status' => 'todo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array{title?: string, description?: string|null, status?: string} $fields
     */
    public function update(int $id, array $fields): bool
    {
        $fields = array_intersect_key($fields, array_flip(['title', 'description', 'status']));
        $fields['updated_at'] = self::now();

        return $this->db->table('tasks')->where('id', $id)->update($fields) > 0;
    }

    public function move(int $id, string $status): bool
    {
        return $this->update($id, ['status' => $status]);
    }

    public function delete(int $id): bool
    {
        return $this->db->table('tasks')->where('id', $id)->delete() > 0;
    }

    private static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
