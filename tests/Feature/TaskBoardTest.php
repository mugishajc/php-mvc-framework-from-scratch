<?php

declare(strict_types=1);

namespace Tests\Feature;

use Framework\Application;
use Framework\Database\Connection;
use Framework\Database\Migrator;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Session\CsrfTokenManager;
use Framework\Session\Session;
use PHPUnit\Framework\TestCase;

/**
 * Drives the real application (routes, middleware, controllers, views)
 * against an in-memory SQLite database.
 */
final class TaskBoardTest extends TestCase
{
    private Application $app;
    private Connection $db;
    private string $token;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);

        $this->app = (require $root . '/bootstrap/app.php')([
            'db_dsn' => 'sqlite::memory:',
            'session' => new Session(),
            'debug' => true,
        ]);

        $this->db = $this->app->container()->get(Connection::class);
        (new Migrator($this->db, $root . '/database/migrations'))->migrate();

        $this->token = $this->app->container()->get(CsrfTokenManager::class)->token();
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private function send(string $method, string $uri, array $body = [], array $query = []): Response
    {
        if ($method !== 'GET' && !array_key_exists('_token', $body)) {
            $body['_token'] = $this->token;
        }

        return $this->app->handle(new Request($method, $uri, $query, $body));
    }

    private function createTask(string $title, string $description = ''): int
    {
        $this->send('POST', '/tasks', ['title' => $title, 'description' => $description]);

        return (int) $this->db->table('tasks')->orderBy('id', 'desc')->first()['id'];
    }

    public function testRootRedirectsToTheBoard(): void
    {
        $response = $this->send('GET', '/');

        $this->assertSame(302, $response->status());
        $this->assertSame('/tasks', $response->header('Location'));
    }

    public function testBoardShowsAllColumnsWithSecurityHeaders(): void
    {
        $response = $this->send('GET', '/tasks');

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('To do', $response->content());
        $this->assertStringContainsString('In progress', $response->content());
        $this->assertStringContainsString('Done', $response->content());
        $this->assertSame('DENY', $response->header('X-Frame-Options'));
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
    }

    public function testCreatingATaskWithoutCsrfTokenIsRejected(): void
    {
        $response = $this->send('POST', '/tasks', ['title' => 'Sneaky', '_token' => '']);

        $this->assertSame(419, $response->status());
        $this->assertSame(0, $this->db->table('tasks')->count());
    }

    public function testCreatingATaskFlashesASuccessMessage(): void
    {
        $response = $this->send('POST', '/tasks', ['title' => 'Write the README', 'description' => 'Short and useful']);

        $this->assertSame(302, $response->status());
        $this->assertSame('/tasks', $response->header('Location'));

        $board = $this->send('GET', '/tasks')->content();
        $this->assertStringContainsString('Task added.', $board);
        $this->assertStringContainsString('Write the README', $board);

        // Flash messages only survive one request.
        $this->assertStringNotContainsString('Task added.', $this->send('GET', '/tasks')->content());
    }

    public function testValidationErrorsAndOldInputAreShownAfterRedirect(): void
    {
        $this->send('POST', '/tasks', ['title' => '', 'description' => 'kept text']);

        $page = $this->send('GET', '/tasks')->content();

        $this->assertStringContainsString('The title field is required.', $page);
        $this->assertStringContainsString('kept text', $page);
        $this->assertSame(0, $this->db->table('tasks')->count());
    }

    public function testTaskTitlesAreEscapedOnTheBoard(): void
    {
        $this->createTask('<script>alert(1)</script>');

        $page = $this->send('GET', '/tasks')->content();

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $page);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $page);
    }

    public function testMovingATaskChangesItsStatus(): void
    {
        $id = $this->createTask('Fix router');

        $response = $this->send('POST', "/tasks/$id/move", ['status' => 'doing']);

        $this->assertSame(302, $response->status());
        $this->assertSame('doing', $this->db->table('tasks')->find($id)['status']);
    }

    public function testMovingToAnUnknownStatusIsRefused(): void
    {
        $id = $this->createTask('Fix router');

        $this->send('POST', "/tasks/$id/move", ['status' => 'archived']);

        $this->assertSame('todo', $this->db->table('tasks')->find($id)['status']);
        $this->assertStringContainsString('could not be moved', $this->send('GET', '/tasks')->content());
    }

    public function testEditingATask(): void
    {
        $id = $this->createTask('Old title');

        $form = $this->send('GET', "/tasks/$id/edit");
        $this->assertSame(200, $form->status());
        $this->assertStringContainsString('value="Old title"', $form->content());

        $response = $this->send('PUT', "/tasks/$id", ['title' => 'New title', 'description' => '', 'status' => 'done']);

        $this->assertSame(302, $response->status());
        $task = $this->db->table('tasks')->find($id);
        $this->assertSame('New title', $task['title']);
        $this->assertSame('done', $task['status']);
        $this->assertNull($task['description']);
    }

    public function testInvalidEditRedirectsBackToTheForm(): void
    {
        $id = $this->createTask('Keep me');

        $response = $this->send('PUT', "/tasks/$id", ['title' => str_repeat('x', 121), 'status' => 'done']);

        $this->assertSame("/tasks/$id/edit", $response->header('Location'));
        $this->assertSame('Keep me', $this->db->table('tasks')->find($id)['title']);
        $this->assertStringContainsString('must not exceed 120 characters', $this->send('GET', "/tasks/$id/edit")->content());
    }

    public function testDeletingATask(): void
    {
        $id = $this->createTask('Temporary');

        $this->assertSame(302, $this->send('DELETE', "/tasks/$id")->status());
        $this->assertNull($this->db->table('tasks')->find($id));
        $this->assertSame(404, $this->send('DELETE', "/tasks/$id")->status());
    }

    public function testUnknownAndMalformedIdsReturn404(): void
    {
        $this->assertSame(404, $this->send('GET', '/tasks/999/edit')->status());
        $this->assertSame(404, $this->send('GET', '/tasks/abc/edit')->status());
    }

    public function testWrongMethodReturns405WithAllowHeader(): void
    {
        $response = $this->send('PATCH', '/tasks');

        $this->assertSame(405, $response->status());
        $this->assertSame('GET, POST', $response->header('Allow'));
    }

    public function testJsonEndpointFiltersByStatus(): void
    {
        $this->createTask('One');
        $done = $this->createTask('Two');
        $this->send('POST', "/tasks/$done/move", ['status' => 'done']);

        $response = $this->send('GET', '/api/tasks', [], ['status' => 'done']);

        $this->assertSame(200, $response->status());
        $this->assertSame('application/json', $response->header('Content-Type'));
        $this->assertSame(['Two'], array_column(json_decode($response->content(), true), 'title'));

        $this->assertSame(422, $this->send('GET', '/api/tasks', [], ['status' => 'nope'])->status());
    }
}
