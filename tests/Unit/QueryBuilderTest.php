<?php

declare(strict_types=1);

namespace Tests\Unit;

use Framework\Database\Connection;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class QueryBuilderTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $this->db = new Connection('sqlite::memory:');
        $this->db->statement(
            'CREATE TABLE tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                status TEXT NOT NULL,
                priority INTEGER NOT NULL DEFAULT 0,
                due_on TEXT NULL
            )'
        );
    }

    private function seed(): void
    {
        $rows = [
            ['title' => 'Write README', 'status' => 'todo', 'priority' => 1],
            ['title' => 'Fix router', 'status' => 'doing', 'priority' => 3],
            ['title' => 'Ship v1', 'status' => 'todo', 'priority' => 5, 'due_on' => '2026-12-01'],
            ['title' => 'Plan sprint', 'status' => 'done', 'priority' => 2],
        ];

        foreach ($rows as $row) {
            $this->db->table('tasks')->insert($row);
        }
    }

    public function testInsertReturnsTheNewId(): void
    {
        $first = $this->db->table('tasks')->insert(['title' => 'A', 'status' => 'todo']);
        $second = $this->db->table('tasks')->insert(['title' => 'B', 'status' => 'todo']);

        $this->assertSame(1, $first);
        $this->assertSame(2, $second);
    }

    public function testCompilesSqlWithPlaceholders(): void
    {
        $query = $this->db->table('tasks')
            ->select('id', 'title')
            ->where('status', 'todo')
            ->where('priority', '>=', 2)
            ->orderBy('id', 'desc')
            ->limit(5)
            ->offset(10);

        $this->assertSame(
            'SELECT id, title FROM tasks WHERE status = ? AND priority >= ? ORDER BY id DESC LIMIT 5 OFFSET 10',
            $query->toSql(),
        );
        $this->assertSame(['todo', 2], $query->getBindings());
    }

    public function testFiltersOrdersAndLimits(): void
    {
        $this->seed();

        $rows = $this->db->table('tasks')
            ->where('status', 'todo')
            ->orderBy('priority', 'desc')
            ->limit(1)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Ship v1', $rows[0]['title']);
    }

    public function testOrWhere(): void
    {
        $this->seed();

        $titles = array_column(
            $this->db->table('tasks')->where('status', 'done')->orWhere('priority', '>', 4)->orderBy('id')->get(),
            'title',
        );

        $this->assertSame(['Ship v1', 'Plan sprint'], $titles);
    }

    public function testWhereInAndEmptyWhereIn(): void
    {
        $this->seed();

        $this->assertSame(3, $this->db->table('tasks')->whereIn('status', ['todo', 'done'])->count());
        $this->assertSame(0, $this->db->table('tasks')->whereIn('status', [])->count());
    }

    public function testNullComparisons(): void
    {
        $this->seed();

        $this->assertSame(3, $this->db->table('tasks')->whereNull('due_on')->count());
        $this->assertSame(1, $this->db->table('tasks')->where('due_on', '!=', null)->count());
    }

    public function testFirstAndFind(): void
    {
        $this->seed();

        $this->assertSame('Fix router', $this->db->table('tasks')->where('status', 'doing')->first()['title']);
        $this->assertSame('Ship v1', $this->db->table('tasks')->find(3)['title']);
        $this->assertNull($this->db->table('tasks')->find(99));
    }

    public function testOffsetWithoutLimit(): void
    {
        $this->seed();

        $rows = $this->db->table('tasks')->orderBy('id')->offset(2)->get();

        $this->assertSame(['Ship v1', 'Plan sprint'], array_column($rows, 'title'));
    }

    public function testUpdateAndDeleteReturnAffectedRows(): void
    {
        $this->seed();

        $this->assertSame(2, $this->db->table('tasks')->where('status', 'todo')->update(['status' => 'doing']));
        $this->assertSame(3, $this->db->table('tasks')->where('status', 'doing')->count());

        $this->assertSame(1, $this->db->table('tasks')->where('id', 4)->delete());
        $this->assertSame(3, $this->db->table('tasks')->count());
    }

    public function testValuesAreBoundNotInterpolated(): void
    {
        $evil = "x'); DROP TABLE tasks; --";
        $id = $this->db->table('tasks')->insert(['title' => $evil, 'status' => 'todo']);

        $this->assertSame($evil, $this->db->table('tasks')->find($id)['title']);
        $this->assertSame(0, $this->db->table('tasks')->where('title', "' OR '1'='1")->count());
        $this->assertSame(1, $this->db->table('tasks')->count());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badIdentifiers(): iterable
    {
        yield 'statement injection' => ['status; DROP TABLE tasks'];
        yield 'comment' => ['status--'];
        yield 'space' => ['status OR 1'];
        yield 'quote' => ['"status"'];
        yield 'leading digit' => ['1status'];
    }

    #[DataProvider('badIdentifiers')]
    public function testRejectsUnsafeColumnNames(string $column): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->db->table('tasks')->where($column, 'x');
    }

    public function testRejectsUnsafeTableNames(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->db->table('tasks t; DELETE FROM tasks');
    }

    public function testRejectsUnknownOperatorsAndDirections(): void
    {
        try {
            $this->db->table('tasks')->where('id', '= 1 OR', 1);
            $this->fail('Operator should have been rejected');
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->expectException(InvalidArgumentException::class);
        $this->db->table('tasks')->orderBy('id', 'sideways');
    }

    public function testTransactionRollsBackOnError(): void
    {
        try {
            $this->db->transaction(function (Connection $db): void {
                $db->table('tasks')->insert(['title' => 'Temp', 'status' => 'todo']);
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, $this->db->table('tasks')->count());
    }

    public function testTransactionCommitsAndReturnsTheResult(): void
    {
        $id = $this->db->transaction(
            static fn (Connection $db): int => $db->table('tasks')->insert(['title' => 'Kept', 'status' => 'todo']),
        );

        $this->assertSame(1, $id);
        $this->assertSame(1, $this->db->table('tasks')->count());
    }
}
