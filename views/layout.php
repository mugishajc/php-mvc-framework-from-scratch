<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $title ?? 'Task Board' ?></title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="topbar">
    <a href="/tasks" class="brand">Task Board</a>
    <a href="/api/tasks" class="muted">JSON</a>
</header>
<main class="container">
    <?php $flash = $flash ?? []; ?>
    <?php if (!empty($flash['success'])): ?>
        <div class="alert alert-success" role="status"><?= $flash['success'] ?></div>
    <?php endif; ?>
    <?php if (!empty($flash['error'])): ?>
        <div class="alert alert-error" role="alert"><?= $flash['error'] ?></div>
    <?php endif; ?>

    <?= $content ?>
</main>
</body>
</html>
