<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Repositories\TaskRepository;
use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Read-only JSON view of the board, mostly to show that handlers can
 * return arrays and get a JSON response.
 */
final class TaskApiController
{
    public function __construct(private TaskRepository $tasks)
    {
    }

    /**
     * @return list<array<string, mixed>>|Response
     */
    public function index(Request $request): array|Response
    {
        $status = $request->query('status');

        if ($status !== null && (!is_string($status) || !array_key_exists($status, TaskRepository::STATUSES))) {
            return Response::json(['error' => 'Unknown status.'], 422);
        }

        return $this->tasks->all($status);
    }
}
