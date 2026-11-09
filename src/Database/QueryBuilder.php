<?php

declare(strict_types=1);

namespace Framework\Database;

use InvalidArgumentException;

/**
 * Fluent builder for simple single-table queries.
 *
 * Identifiers (table and column names) can't be bound as parameters, so
 * they're checked against a strict pattern instead. Values always go
 * through placeholders.
 */
final class QueryBuilder
{
    private const OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];

    /** @var list<string> */
    private array $columns = ['*'];

    /** @var list<array{boolean: string, sql: string}> */
    private array $wheres = [];

    /** @var list<mixed> */
    private array $bindings = [];

    /** @var list<string> */
    private array $orders = [];

    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(private Connection $connection, private string $table)
    {
        self::assertIdentifier($table);
    }

    public static function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $identifier) !== 1) {
            throw new InvalidArgumentException("Invalid SQL identifier [$identifier].");
        }
    }

    public function select(string ...$columns): self
    {
        foreach ($columns as $column) {
            self::assertIdentifier($column);
        }
        $this->columns = $columns === [] ? ['*'] : array_values($columns);

        return $this;
    }

    /**
     * where('status', 'done') or where('priority', '>', 2)
     */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->addWhere('AND', $column, (string) $operator, $value);
    }

    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->addWhere('OR', $column, (string) $operator, $value);
    }

    /**
     * @param list<mixed> $values
     */
    public function whereIn(string $column, array $values): self
    {
        self::assertIdentifier($column);

        if ($values === []) {
            // An empty IN () is a syntax error; this keeps the meaning "matches nothing".
            $this->wheres[] = ['boolean' => 'AND', 'sql' => '1 = 0'];

            return $this;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = ['boolean' => 'AND', 'sql' => "$column IN ($placeholders)"];
        array_push($this->bindings, ...array_values($values));

        return $this;
    }

    public function whereNull(string $column): self
    {
        self::assertIdentifier($column);
        $this->wheres[] = ['boolean' => 'AND', 'sql' => "$column IS NULL"];

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        self::assertIdentifier($column);
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException("Invalid sort direction [$direction].");
        }
        $this->orders[] = "$column $direction";

        return $this;
    }

    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit must not be negative.');
        }
        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must not be negative.');
        }
        $this->offset = $offset;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        return $this->connection->select($this->toSql(), $this->bindings);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $clone = clone $this;
        $clone->limit = 1;

        return $this->connection->selectOne($clone->toSql(), $clone->bindings);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return (clone $this)->where('id', $id)->first();
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) AS aggregate FROM {$this->table}" . $this->compileWheres();
        $row = $this->connection->selectOne($sql, $this->bindings);

        return (int) ($row['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insert(array $values): int
    {
        if ($values === []) {
            throw new InvalidArgumentException('Cannot insert an empty row.');
        }

        $columns = array_keys($values);
        foreach ($columns as $column) {
            self::assertIdentifier((string) $column);
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', array_fill(0, count($values), '?')),
        );

        $this->connection->statement($sql, array_values($values));

        return $this->connection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $values
     */
    public function update(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $sets = [];
        foreach (array_keys($values) as $column) {
            self::assertIdentifier($column);
            $sets[] = "$column = ?";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . $this->compileWheres();

        return $this->connection->statement($sql, [...array_values($values), ...$this->bindings]);
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}" . $this->compileWheres();

        return $this->connection->statement($sql, $this->bindings);
    }

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . " FROM {$this->table}" . $this->compileWheres();

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            // SQLite needs a LIMIT before OFFSET; -1 means "no limit".
            $sql .= ($this->limit === null ? ' LIMIT -1' : '') . ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * @return list<mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    private function addWhere(string $boolean, string $column, string $operator, mixed $value): self
    {
        self::assertIdentifier($column);
        $operator = strtoupper($operator);

        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Invalid operator [$operator].");
        }

        if ($value === null) {
            $sql = $operator === '=' ? "$column IS NULL" : "$column IS NOT NULL";
        } else {
            $sql = "$column $operator ?";
            $this->bindings[] = $value;
        }

        $this->wheres[] = ['boolean' => $boolean, 'sql' => $sql];

        return $this;
    }

    private function compileWheres(): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $sql = '';
        foreach ($this->wheres as $index => $where) {
            $sql .= ($index === 0 ? ' WHERE ' : " {$where['boolean']} ") . $where['sql'];
        }

        return $sql;
    }
}
