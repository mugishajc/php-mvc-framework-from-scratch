# PHP MVC framework from scratch

A small MVC framework in plain PHP 8.2, plus a task board app built on top of it.
No framework packages. The only dependency is PHPUnit for the tests.

I wrote it to show what Laravel or Symfony do under the hood: routing, a middleware
pipeline, a DI container, a safe database layer, template escaping and CSRF checks.
Each piece is small enough to read in one sitting.

![CI](https://github.com/mugishajc/php-mvc-framework-from-scratch/actions/workflows/ci.yml/badge.svg)

## What's in it

| Part | File | Notes |
| --- | --- | --- |
| Front controller | `public/index.php` | Every request goes through here |
| Request / Response | `src/Http` | Immutable request, `_method` override for HTML forms |
| Router | `src/Routing` | Route params, regex constraints (`{id:\d+}`), groups with prefix and middleware, named routes, 404/405 |
| Middleware | `src/Middleware` | Onion-style pipeline, CSRF middleware |
| Container | `src/Container` | Constructor autowiring, singletons, factories, method injection, circular dependency detection |
| Database | `src/Database` | PDO wrapper, query builder with prepared statements, migrations with batches and rollback |
| Views | `src/View` | Plain PHP templates, data is escaped before the template sees it |
| Validation | `src/Validation` | `required`, `string`, `integer`, `numeric`, `email`, `min`, `max`, `in` |
| Session | `src/Session` | Flash messages, CSRF token manager |
| Kernel | `src/Application.php` | Matches, runs middleware, calls the handler, converts the return value |

The demo app lives in `app/`, `views/` and `routes/web.php`.

## Requirements

- PHP 8.2 or newer with `pdo_sqlite` and `mbstring`
- Composer

## Running it

```bash
composer install
php bin/migrate          # creates storage/database.sqlite
php -S localhost:8000 -t public
```

Open http://localhost:8000. You can add tasks, move them between To do, In progress
and Done, edit them and delete them.

Set `DB_DSN` to use another database and `APP_DEBUG=1` to see stack traces.

Migration commands:

```bash
php bin/migrate          # run pending migrations
php bin/migrate status   # list migrations and whether they ran
php bin/migrate down     # roll back the last batch
```

## Routes

| Method | Path | Action |
| --- | --- | --- |
| GET | `/` | Redirect to `/tasks` |
| GET | `/tasks` | Board with the new task form |
| POST | `/tasks` | Create a task |
| GET | `/tasks/{id}/edit` | Edit form |
| PUT | `/tasks/{id}` | Update a task |
| POST | `/tasks/{id}/move` | Change the status |
| DELETE | `/tasks/{id}` | Delete a task |
| GET | `/api/tasks?status=todo` | Tasks as JSON |

PUT and DELETE come from HTML forms with a hidden `_method` field.

## How the pieces fit

```php
// routes/web.php
$router->group(['prefix' => '/tasks'], function (Router $router) {
    $router->get('/{id:\d+}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
});

// app/Controllers/TaskController.php
public function edit(int $id): Response   // "42" from the URL arrives as int 42
{
    $task = $this->tasks->find($id);      // TaskRepository injected by the container
    ...
}
```

A handler can return a `Response`, a string (sent as HTML), an array (sent as JSON)
or `null` (204).

Query builder:

```php
$db->table('tasks')
    ->where('status', 'todo')
    ->where('priority', '>=', 2)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
// SELECT * FROM tasks WHERE status = ? AND priority >= ? ORDER BY created_at DESC LIMIT 10
```

Values always go through placeholders. Table and column names can't be bound, so
they are checked against `^[A-Za-z_][A-Za-z0-9_]*$` and anything else throws.

## Security notes

- Output escaping is on by default. Strings and arrays passed to `View::render()` are
  run through `htmlspecialchars` first. Wrap trusted HTML in `HtmlString` to skip it.
- POST, PUT, PATCH and DELETE need a CSRF token (form field `_token` or header
  `X-CSRF-Token`), compared with `hash_equals`.
- Sessions use `HttpOnly`, `SameSite=Lax`, strict mode, and `Secure` over HTTPS.
- Responses carry `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` and a
  basic CSP.

## Tests

```bash
vendor/bin/phpunit
```

- `tests/Unit`: router, container, validator, query builder, migrator, view escaping,
  CSRF middleware, pipeline
- `tests/Feature`: the task board end to end through the kernel, on in-memory SQLite

CI runs the suite on PHP 8.2 and 8.3, then starts the built-in server and checks a few
pages with curl.

## Limits

This is a learning project, not a replacement for a real framework. There is no ORM,
no caching, no event system, and the query builder handles single-table queries only.

## License

MIT
