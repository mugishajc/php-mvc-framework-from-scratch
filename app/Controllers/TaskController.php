<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\TaskRepository;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Session\CsrfTokenManager;
use Framework\Session\Session;
use Framework\Validation\Validator;
use Framework\View\View;

final class TaskController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        CsrfTokenManager $csrf,
        private TaskRepository $tasks,
    ) {
        parent::__construct($view, $session, $csrf);
    }

    public function index(): Response
    {
        return $this->render('tasks/index', [
            'board' => $this->tasks->board(),
            'statuses' => TaskRepository::STATUSES,
        ]);
    }

    public function store(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:120',
            'description' => 'string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->back('/tasks', $validator->errors(), $request->only(['title', 'description']));
        }

        $data = $validator->validated();
        $this->tasks->create($data['title'], $data['description'] ?? null);

        return $this->redirect('/tasks', 'Task added.');
    }

    public function edit(int $id): Response
    {
        $task = $this->tasks->find($id);
        if ($task === null) {
            return $this->notFound();
        }

        return $this->render('tasks/edit', [
            'task' => $task,
            'statuses' => TaskRepository::STATUSES,
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        if ($this->tasks->find($id) === null) {
            return $this->notFound();
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:120',
            'description' => 'string|max:1000',
            'status' => 'required|in:' . implode(',', array_keys(TaskRepository::STATUSES)),
        ]);

        if ($validator->fails()) {
            return $this->back("/tasks/$id/edit", $validator->errors(), $request->only(['title', 'description', 'status']));
        }

        $this->tasks->update($id, $validator->validated());

        return $this->redirect('/tasks', 'Task updated.');
    }

    public function move(Request $request, int $id): Response
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:' . implode(',', array_keys(TaskRepository::STATUSES)),
        ]);

        if ($validator->fails() || !$this->tasks->move($id, $validator->validated()['status'])) {
            $this->session->flash('error', 'That task could not be moved.');

            return $this->redirect('/tasks');
        }

        return $this->redirect('/tasks');
    }

    public function destroy(int $id): Response
    {
        if (!$this->tasks->delete($id)) {
            return $this->notFound();
        }

        return $this->redirect('/tasks', 'Task deleted.');
    }
}
