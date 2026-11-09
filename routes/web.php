<?php

declare(strict_types=1);

use App\Controllers\Api\TaskApiController;
use App\Controllers\TaskController;
use Framework\Http\Response;
use Framework\Routing\Router;

return static function (Router $router): void {
    $router->get('/', static fn (): Response => Response::redirect('/tasks'));

    $router->group(['prefix' => '/tasks'], static function (Router $router): void {
        $router->get('/', [TaskController::class, 'index'])->name('tasks.index');
        $router->post('/', [TaskController::class, 'store'])->name('tasks.store');
        $router->get('/{id:\d+}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
        $router->put('/{id:\d+}', [TaskController::class, 'update'])->name('tasks.update');
        $router->post('/{id:\d+}/move', [TaskController::class, 'move'])->name('tasks.move');
        $router->delete('/{id:\d+}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    });

    $router->get('/api/tasks', [TaskApiController::class, 'index'])->name('api.tasks');
};
