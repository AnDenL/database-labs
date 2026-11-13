<?php
$currentSort = $_GET['sort'] ?? $primaryKey;
$currentDir  = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

$crudSortUrl = function (string $col) use ($baseUri, $currentSort, $currentDir): string {
    $q = $_GET;
    unset($q['edit']);
    $q['sort'] = $col;
    $q['dir']  = ($currentSort === $col && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    return $baseUri . '?' . http_build_query($q);
};

$crudArrow = function (string $col) use ($currentSort, $currentDir): string {
    if ($currentSort !== $col) return ' ↕';
    return $currentDir === 'ASC' ? ' ↑' : ' ↓';
};
?>
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

    <h2><?= $edit_data ? 'Редагувати запис' : 'Додати новий запис' ?></h2>
    <form method="post" action="<?= $baseUri ?>/save">
        <div class="form-group">
            <?php if ($edit_data): ?>
                <input type="hidden" name="<?= $primaryKey ?>" value="<?= (int)$edit_data[$primaryKey] ?>">
            <?php endif; ?>

            <?php foreach ($columns as $field => $meta): ?>
                <?php if ($meta['is_pk'] || $meta['is_auto']) continue; ?>
                <label><?= htmlspecialchars($meta['label']) ?>:</label>
                <input
                    type="<?= $meta['input_type'] ?>"
                    name="<?= $field ?>"
                    placeholder="<?= htmlspecialchars($meta['label']) ?>"
                    value="<?= htmlspecialchars((string)($edit_data[$field] ?? '')) ?>"
                    <?= $meta['type'] === 'd' ? 'step="0.01"' : '' ?>
                    <?= $meta['required'] ? 'required' : '' ?>
                >
            <?php endforeach; ?>

            <div style="margin-top: 10px;">
                <button type="submit"><?= $edit_data ? 'Зберегти зміни' : 'Додати' ?></button>
                <?php if ($edit_data): ?>
                    <a href="<?= $baseUri ?>" style="margin-left: 10px;">Скасувати</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <hr>
    <h2><?= htmlspecialchars($pageTitle) ?></h2>

    <form method="get" action="<?= $baseUri ?>" style="display: flex; gap: 10px; align-items: center; max-width: 500px; padding: 12px;">
        <input type="text" name="q" placeholder="Пошук по всіх полях..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
        <button type="submit">Знайти</button>
        <?php if (!empty($_GET['q'])): ?>
            <a href="<?= $baseUri ?>">Скинути</a>
        <?php endif; ?>
    </form>

    <table border="1" cellpadding="6" cellspacing="0">
        <thead>
            <tr>
                <th><a href="<?= htmlspecialchars($crudSortUrl($primaryKey)) ?>" style="color: inherit; text-decoration: none;">ID<?= $crudArrow($primaryKey) ?></a></th>
                <?php foreach ($columns as $field => $meta): ?>
                    <?php if ($meta['is_pk'] || $meta['is_auto']) continue; ?>
                    <th><a href="<?= htmlspecialchars($crudSortUrl($field)) ?>" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($meta['label']) ?><?= $crudArrow($field) ?></a></th>
                <?php endforeach; ?>
                <th>Дії</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $row): ?>
            <tr>
                <td><?= (int)$row[$primaryKey] ?></td>
                <?php foreach ($columns as $field => $meta): ?>
                    <?php if ($meta['is_pk'] || $meta['is_auto']) continue; ?>
                    <td><?= htmlspecialchars((string)$row[$field]) ?></td>
                <?php endforeach; ?>
                <td>
                    <a href="<?= $baseUri ?>?edit=<?= (int)$row[$primaryKey] ?>">Редагувати</a> |
                    <a href="<?= $baseUri ?>/delete?id=<?= (int)$row[$primaryKey] ?>" onclick="return confirm('Видалити цей запис?')">Видалити</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php include 'footer.php'; ?>
</body>
</html>
