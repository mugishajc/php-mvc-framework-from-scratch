<section class="panel">
    <h1>New task</h1>
    <form method="post" action="/tasks" class="task-form">
        <input type="hidden" name="_token" value="<?= $csrfToken ?>">

        <label for="title">Title</label>
        <input id="title" name="title" maxlength="120" value="<?= $old['title'] ?? '' ?>" required>
        <?php if (isset($errors['title'])): ?>
            <p class="field-error"><?= $errors['title'][0] ?></p>
        <?php endif; ?>

        <label for="description">Description <span class="muted">(optional)</span></label>
        <textarea id="description" name="description" rows="2" maxlength="1000"><?= $old['description'] ?? '' ?></textarea>
        <?php if (isset($errors['description'])): ?>
            <p class="field-error"><?= $errors['description'][0] ?></p>
        <?php endif; ?>

        <button type="submit">Add task</button>
    </form>
</section>

<section class="board">
    <?php foreach ($statuses as $status => $label): ?>
        <div class="column column-<?= $status ?>">
            <h2><?= $label ?> <span class="count"><?= count($board[$status]) ?></span></h2>

            <?php if ($board[$status] === []): ?>
                <p class="empty">Nothing here.</p>
            <?php endif; ?>

            <?php foreach ($board[$status] as $task): ?>
                <article class="card">
                    <h3><?= $task['title'] ?></h3>
                    <?php if (!empty($task['description'])): ?>
                        <p><?= nl2br($task['description']) ?></p>
                    <?php endif; ?>

                    <div class="card-actions">
                        <?php foreach ($statuses as $target => $targetLabel): ?>
                            <?php if ($target === $status) continue; ?>
                            <form method="post" action="/tasks/<?= $task['id'] ?>/move">
                                <input type="hidden" name="_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="status" value="<?= $target ?>">
                                <button type="submit" class="link">&rarr; <?= $targetLabel ?></button>
                            </form>
                        <?php endforeach; ?>

                        <a href="/tasks/<?= $task['id'] ?>/edit">Edit</a>

                        <form method="post" action="/tasks/<?= $task['id'] ?>">
                            <input type="hidden" name="_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit" class="link danger">Delete</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</section>
