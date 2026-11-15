<?php
$crudSortUrl = function (string $col) use ($baseUri, $currentSort, $currentDir): string {
    $q = $_GET;
    unset($q['edit']);
    $q['sort'] = $col;
    $q['dir']  = ($currentSort === $col && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    return $baseUri . '?' . http_build_query($q);
};

$crudArrow = function (string $col) use ($currentSort, $currentDir): string {
    if ($currentSort !== $col) return '';
    return $currentDir === 'ASC' ? ' ▲' : ' ▼';
};

$activeView = $viewSwitcher['current'] ?? null;
$resetUri   = $activeView !== null ? $baseUri . '?view=' . urlencode($activeView) : $baseUri;
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
        <div style="background: #ffe6e6; color: #cc0000; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($viewSwitcher['views'])): ?>
        <form method="get" action="<?= $baseUri ?>" style="display: flex; gap: 10px; align-items: center; max-width: 500px; padding: 12px; margin-top: 15px;">
            <label for="view_select" style="font-weight: 600; white-space: nowrap;">Представлення (VIEW):</label>
            <select name="view" id="view_select" onchange="this.form.submit()">
                <?php foreach ($viewSwitcher['views'] as $vName): ?>
                    <?php $vTitle = $viewSwitcher['meta'][$vName]['title'] ?? $vName; ?>
                    <option value="<?= htmlspecialchars($vName) ?>" <?= $activeView === $vName ? 'selected' : '' ?>>
                        <?= htmlspecialchars($vTitle) ?> (<?= htmlspecialchars($vName) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>

    <?php if (empty($readOnly)): ?>
        <h2><?= $edit_data ? 'Редагувати запис' : 'Додати новий запис' ?></h2>
        <form method="post" action="<?= $baseUri ?>/save" enctype="multipart/form-data">
            <div class="form-group">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="<?= $primaryKey ?>" value="<?= (int)$edit_data[$primaryKey] ?>">
                <?php endif; ?>

                <?php foreach ($columns as $field => $meta): ?>
                    <?php if ($meta['is_pk'] || $meta['is_auto']) continue; ?>
                    <?php if (!empty($meta['is_blob'])): ?>
                        <input
                            type="file"
                            name="<?= $field ?>"
                            accept="image/*"
                            onchange="previewPhoto(this, 'preview_<?= $field ?>')"
                        >
                        <?php
                            $hasExisting = !empty($edit_data[$field]);
                            $existingSrc = '';
                            if ($hasExisting) {
                                $info = @getimagesizefromstring($edit_data[$field]);
                                $mime = $info['mime'] ?? 'image/jpeg';
                                $existingSrc = "data:{$mime};base64," . base64_encode($edit_data[$field]);
                            }
                        ?>
                        <div style="display: flex; align-items: center; gap: 12px; margin-top: 6px;">
                            <img
                                id="preview_<?= $field ?>"
                                src="<?= $existingSrc ?>"
                                alt="Фото"
                                style="width: 72px; height: 72px; object-fit: cover; border-radius: 8px; border: 1px solid #ccc; <?= $hasExisting ? '' : 'display: none;' ?>"
                            >
                            <?php if ($hasExisting): ?>
                                <label style="font-weight: normal; cursor: pointer;">
                                    <input type="checkbox" name="delete_<?= $field ?>" value="1"> Видалити поточне фото
                                </label>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <label><?= htmlspecialchars($meta['label']) ?>:</label>
                        <input
                            type="<?= $meta['input_type'] ?>"
                            name="<?= $field ?>"
                            placeholder="<?= htmlspecialchars($meta['label']) ?>"
                            value="<?= htmlspecialchars((string)($edit_data[$field] ?? '')) ?>"
                            <?= isset($meta['min'])  ? 'min="'  . $meta['min']  . '"' : '' ?>
                            <?= isset($meta['max'])  ? 'max="'  . $meta['max']  . '"' : '' ?>
                            <?= isset($meta['step']) ? 'step="' . $meta['step'] . '"' : '' ?>
                            <?= $meta['required'] ? 'required' : '' ?>
                        >
                    <?php endif; ?>
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
    <?php endif; ?>

    <h2><?= htmlspecialchars($pageTitle) ?></h2>

    <form method="get" action="<?= $baseUri ?>" style="display: flex; gap: 10px; align-items: center; max-width: 500px; padding: 12px;">
        <?php if ($activeView !== null): ?>
            <input type="hidden" name="view" value="<?= htmlspecialchars($activeView) ?>">
        <?php endif; ?>
        <input type="text" name="q" placeholder="Пошук по всіх полях..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Знайти</button>
        <?php if ($search !== ''): ?>
            <a href="<?= htmlspecialchars($resetUri) ?>">Скинути</a>
        <?php endif; ?>
    </form>

    <table border="1" cellpadding="6" cellspacing="0">
        <thead>
            <tr>
                <?php if (empty($readOnly)): ?>
                    <th><a href="<?= htmlspecialchars($crudSortUrl($primaryKey)) ?>" style="color: inherit; text-decoration: none;">ID<?= $crudArrow($primaryKey) ?></a></th>
                <?php endif; ?>

                <?php foreach ($columns as $field => $meta): ?>
                    <?php if (empty($readOnly) && ($meta['is_pk'] || $meta['is_auto'])) continue; ?>
                    <th><a href="<?= htmlspecialchars($crudSortUrl($field)) ?>" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($meta['label']) ?><?= $crudArrow($field) ?></a></th>
                <?php endforeach; ?>

                <?php if (empty($readOnly)): ?>
                    <th>Дії</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="<?= count($columns) + (empty($readOnly) ? 1 : 0) ?>" style="text-align: center;">
                        Записів не знайдено.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $row): ?>
                <tr>
                    <?php if (empty($readOnly)): ?>
                        <td><?= (int)$row[$primaryKey] ?></td>
                    <?php endif; ?>

                    <?php foreach ($columns as $field => $meta): ?>
                        <?php if (empty($readOnly) && ($meta['is_pk'] || $meta['is_auto'])) continue; ?>
                        <?php if (!empty($meta['is_blob'])): ?>
                            <td>
                                <?php if (!empty($row[$field])): ?>
                                    <?php
                                        $info = @getimagesizefromstring($row[$field]);
                                        $mime = $info['mime'] ?? 'image/jpeg';
                                    ?>
                                    <img src="data:<?= $mime ?>;base64,<?= base64_encode($row[$field]) ?>"
                                         alt="Фото"
                                         style="width: 54px; height: 54px; object-fit: cover; border-radius: 50%; border: 1px solid #ccc;">
                                <?php else: ?>
                                    <span style="color: #888; font-size: 13px;">—</span>
                                <?php endif; ?>
                            </td>
                        <?php else: ?>
                            <td><?= htmlspecialchars((string)($row[$field] ?? '—')) ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if (empty($readOnly)): ?>
                        <td>
                            <a href="<?= $baseUri ?>?edit=<?= (int)$row[$primaryKey] ?>">Редагувати</a> |
                            <form method="post" action="<?= $baseUri ?>/delete" class="inline-form" onsubmit="return confirm('Видалити запис #<?= (int)$row[$primaryKey] ?>?')">
                                <input type="hidden" name="id" value="<?= (int)$row[$primaryKey] ?>">
                                <button type="submit" class="btn-delete">Видалити</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (empty($readOnly)): ?>
        <script>
            function previewPhoto(input, previewId) {
                const preview = document.getElementById(previewId);
                if (!preview || !input.files || !input.files[0]) return;
                preview.src = URL.createObjectURL(input.files[0]);
                preview.style.display = 'block';
            }
        </script>
    <?php endif; ?>

    <?php include 'footer.php'; ?>
</body>
</html>
