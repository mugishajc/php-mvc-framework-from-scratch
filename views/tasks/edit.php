<section class="panel">
    <h1>Edit task</h1>
    <form method="post" action="/tasks/<?= $task['id'] ?>" class="task-form">
        <input type="hidden" name="_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="_method" value="PUT">

        <label for="title">Title</label>
        <input id="title" name="title" maxlength="120" value="<?= $old['title'] ?? $task['title'] ?>" required>
        <?php if (isset($errors['title'])): ?>
            <p class="field-error"><?= $errors['title'][0] ?></p>
        <?php endif; ?>

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4" maxlength="1000"><?= $old['description'] ?? $task['description'] ?? '' ?></textarea>
        <?php if (isset($errors['description'])): ?>
            <p class="field-error"><?= $errors['description'][0] ?></p>
        <?php endif; ?>

        <label for="status">Status</label>
        <?php $current = $old['status'] ?? $task['status']; ?>
        <select id="status" name="status">
            <?php foreach ($statuses as $value => $label): ?>
                <option value="<?= $value ?>"<?= $value === $current ? ' selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['status'])): ?>
            <p class="field-error"><?= $errors['status'][0] ?></p>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit">Save</button>
            <a href="/tasks">Cancel</a>
        </div>
    </form>
</section>
