<?php

declare(strict_types=1);

use App\Middleware\SecurityHeaders;
use Framework\Application;
use Framework\Container\Container;
use Framework\Database\Connection;
use Framework\Middleware\CsrfMiddleware;
use Framework\Session\Session;
use Framework\View\View;

/**
 * Builds the application. Tests pass overrides (an in-memory database,
 * an array-backed session) instead of touching globals.
 *
 * @param array<string, mixed> $overrides
 */
return static function (array $overrides = []): Application {
    $config = array_replace(require dirname(__DIR__) . '/config/app.php', $overrides);

    $container = new Container();
    $container->instance('config', $config);

    $container->singleton(Connection::class, static fn (): Connection => new Connection($config['db_dsn']));
    $container->singleton(Session::class, static fn (): Session => $config['session'] ?? Session::start());
    $container->singleton(View::class, static function () use ($config): View {
        $view = new View(dirname(__DIR__) . '/views');
        $view->share('title', $config['name']);

        return $view;
    });

    $app = new Application($container, (bool) $config['debug']);
    $app->pipe(SecurityHeaders::class);
    $app->pipe(CsrfMiddleware::class);

    (require dirname(__DIR__) . '/routes/web.php')($app->router());

    return $app;
};
