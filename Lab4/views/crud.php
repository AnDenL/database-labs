<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
    <?php include 'menu.php'; ?>

    <?php if (!empty($error)): ?>
        <div style="background: #ffe6e6; color: #cc0000; padding: 10px; margin-bottom: 15px; border: 1px solid #cc0000;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <h2><?= $edit_data ? 'Редагувати запис' : 'Додати запис' ?></h2>

    <form method="post" action="<?= $baseUri ?>/save">
        <div class="form-group">
        <?php if ($edit_data): ?>
            <input type="hidden" name="<?= $primaryKey ?>" value="<?= $edit_data[$primaryKey] ?>">
        <?php endif; ?>

        <?php foreach ($columns as $field =>$meta): ?>
            <?php if ($meta['is_pk'] || $meta['is_auto']) continue; ?>
            <input
                type="<?= $meta['input_type'] ?>"
                name="<?= $field ?>"
                placeholder="<?= htmlspecialchars($meta['label']) ?>"
                value="<?= htmlspecialchars((string)($edit_data[$field] ?? '')) ?>"
                <?= $meta['type'] === 'd' ? 'step="0.01"' : '' ?>
                <?= $meta['required'] ? 'required' : '' ?>
            >
        <?php endforeach; ?>

        <button type="submit"><?= $edit_data ? 'Оновити' : 'Створити' ?></button>
        <?php if ($edit_data): ?>
            <a href="<?= $baseUri ?>">Скасувати</a>
        <?php endif; ?>
        </div>
    </form>

    <hr>

    <h2><?= htmlspecialchars($pageTitle) ?></h2>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr>
            <th>ID</th>
            <?php foreach ($columns as $field =>$meta): ?>
                <?php if ($meta['is_pk'] || $meta['is_auto']) continue; ?>
                <th><?= htmlspecialchars($meta['label']) ?></th>
            <?php endforeach; ?>
            <th>Дії</th>
        </tr>
        <?php foreach ($items as$row): ?>
        <tr>
            <td><?= $row[$primaryKey] ?></td>
            <?php foreach ($columns as $field =>$meta): ?>
                <?php if ($meta['is_pk'] || $meta['is_auto']) continue; ?>
                <td><?= htmlspecialchars((string)$row[$field]) ?></td>
            <?php endforeach; ?>
            <td>
                <a href="<?= $baseUri ?>?edit=<?= $row[$primaryKey] ?>">Редагувати</a> |
                <a href="<?= $baseUri ?>/delete?id=<?= $row[$primaryKey] ?>" onclick="return confirm('Видалити?')">Видалити</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <?php include 'footer.php'; ?>
</body>
</html>
