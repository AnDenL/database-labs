<?php
$types = [
    'PLOWING'    => 'Оранка (PLOWING)',
    'SOWING'     => 'Посів (SOWING)',
    'HARVESTING' => 'Збирання врожаю (HARVESTING)',
    'TRANSFER'   => 'Переміщення на склад (TRANSFER)',
    'SALES'      => 'Продаж партії (SALES)',
];

$statuses = [
    'PLANNED'     => 'Заплановано (PLANNED)',
    'IN_PROGRESS' => 'Виконується (IN_PROGRESS)',
    'COMPLETED'   => 'Виконано (COMPLETED)',
    'POSTPONED'   => 'Відкладено (POSTPONED)',
    'CANCELLED'   => 'Скасовано (CANCELLED)',
];

// Допоміжна функція для посилань сортування зі збереженням поточних фільтрів
$currentSort = $_GET['sort'] ?? 'op_id';
$currentDir  = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

function sort_url(string $column, string $currentSort, string $currentDir): string {
    $query = $_GET;
    unset($query['edit']);
    $query['sort'] = $column;
    $query['dir']  = ($currentSort === $column && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    return '/operations?' . http_build_query($query);
}

function sort_arrow(string $column, string $currentSort, string $currentDir): string {
    if ($currentSort !== $column) return ' ↕';
    return $currentDir === 'ASC' ? ' ↑' : ' ↓';
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Сільськогосподарські операції</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        .type-field { display: none; }
        .filter-bar {
            background: #f1f3f5;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            margin-bottom: 20px;
            max-width: 100%;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 12px;
            align-items: end;
        }
        .sort-link {
            color: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .sort-link:hover { text-decoration: underline; }
        .btn-reset {
            display: inline-block;
            padding: 9px 14px;
            background: #6c757d;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <?php if (!empty($error)): ?>
        <div style="background: #ffe6e6; color: #cc0000; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <h2><?= $edit_data ? 'Редагувати операцію #' . (int)$edit_data['op_id'] : 'Додати нову операцію' ?></h2>

    <form method="post" action="/operations/save">
        <?php if ($edit_data): ?>
            <input type="hidden" name="op_id" value="<?= (int)$edit_data['op_id'] ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="op_type">Тип операції:</label>
                <select name="op_type" id="op_type" onchange="toggleFormFields()" required>
                    <option value="">-- Оберіть тип --</option>
                    <?php foreach ($types as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($edit_data['op_type'] ?? '') === $val ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Дата проведення:</label>
                <input type="date" name="op_date" value="<?= htmlspecialchars($edit_data['op_date'] ?? date('Y-m-d')) ?>" required>
            </div>

            <div class="form-group">
                <label>Планові години:</label>
                <input type="number" step="0.1" name="planned_hours" value="<?= htmlspecialchars((string)($edit_data['planned_hours'] ?? '')) ?>" required>
            </div>

            <div class="form-group">
                <label>Статус:</label>
                <select name="status" required>
                    <?php foreach ($statuses as $stVal => $stLabel): ?>
                        <option value="<?= $stVal ?>" <?= ($edit_data['status'] ?? 'COMPLETED') === $stVal ? 'selected' : '' ?>>
                            <?= htmlspecialchars($stLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Поле: для PLOWING, SOWING, HARVESTING -->
            <div class="form-group type-field field-plowing field-sowing field-harvesting">
                <label>Поле:</label>
                <select name="field_id">
                    <option value="">-- Оберіть поле --</option>
                    <?php foreach ($fields as $f): ?>
                        <option value="<?= (int)$f['field_id'] ?>" <?= (int)($edit_data['field_id'] ?? 0) === (int)$f['field_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Культура: для SOWING, TRANSFER, SALES -->
            <div class="form-group type-field field-sowing field-transfer field-sales">
                <label>Культура:</label>
                <select name="crop_id">
                    <option value="">-- Оберіть культуру --</option>
                    <?php foreach ($crops as $c): ?>
                        <option value="<?= (int)$c['crop_id'] ?>" <?= (int)($edit_data['crop_id'] ?? 0) === (int)$c['crop_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-grid">
            <!-- Склад: лише для TRANSFER -->
            <div class="form-group type-field field-transfer">
                <label>Склад призначення:</label>
                <select name="warehouse_id">
                    <option value="">-- Оберіть склад --</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= (int)$w['warehouse_id'] ?>" <?= (int)($edit_data['warehouse_id'] ?? 0) === (int)$w['warehouse_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($w['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Зібраний врожай: HARVESTING -->
            <div class="form-group type-field field-harvesting">
                <label>Зібрано (тонн):</label>
                <input type="number" step="0.01" name="harvested_tons" value="<?= htmlspecialchars((string)($edit_data['harvested_tons'] ?? '')) ?>" placeholder="0.00">
            </div>

            <!-- Переміщено на склад: TRANSFER -->
            <div class="form-group type-field field-transfer">
                <label>Переміщено (тонн):</label>
                <input type="number" step="0.01" name="transferred_tons" value="<?= htmlspecialchars((string)($edit_data['transferred_tons'] ?? '')) ?>" placeholder="0.00">
            </div>

            <!-- Продаж: SALES -->
            <div class="form-group type-field field-sales">
                <label>Продано (тонн):</label>
                <input type="number" step="0.01" name="sold_tons" value="<?= htmlspecialchars((string)($edit_data['sold_tons'] ?? '')) ?>" placeholder="0.00">
            </div>

            <div class="form-group type-field field-sales">
                <label>Покупець:</label>
                <select name="buyer_id">
                    <option value="">-- Оберіть покупця --</option>
                    <?php foreach ($buyers as $b): ?>
                        <option value="<?= (int)$b['buyer_id'] ?>" <?= (int)($edit_data['buyer_id'] ?? 0) === (int)$b['buyer_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group type-field field-sales">
                <label>Ціна за тонну (грн):</label>
                <input type="number" step="0.01" name="price_per_ton" value="<?= htmlspecialchars((string)($edit_data['price_per_ton'] ?? '')) ?>" placeholder="0.00">
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
            <label>Виконавці (утримуйте Ctrl для вибору кількох):</label>
            <select name="workers[]" multiple size="5">
                <?php foreach ($workers as $w): ?>
                    <?php $sel = in_array((int)$w['tab_number'], $edit_data['workers'] ?? [], true) ? 'selected' : ''; ?>
                    <option value="<?= (int)$w['tab_number'] ?>" <?= $sel ?>><?= htmlspecialchars($w['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit"><?= $edit_data ? 'Зберегти зміни' : 'Додати операцію' ?></button>
        <?php if ($edit_data): ?>
            <a href="/operations" style="margin-left: 10px;">Скасувати</a>
        <?php endif; ?>
    </form>

    <hr style="margin: 30px 0;">

    <h2>Журнал операцій (знайдено: <?= count($items) ?>)</h2>

    <!-- ПАНЕЛЬ ФІЛЬТРАЦІЇ ТА СОРТУВАННЯ -->
    <form method="get" action="/operations" class="filter-bar">
        <div class="filter-grid">
            <div class="form-group">
                <label>Тип операції:</label>
                <select name="filter_type">
                    <option value="">Усі типи</option>
                    <?php foreach ($types as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($_GET['filter_type'] ?? '') === $val ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Статус:</label>
                <select name="filter_status">
                    <option value="">Усі статуси</option>
                    <?php foreach ($statuses as $stVal => $stLabel): ?>
                        <option value="<?= $stVal ?>" <?= ($_GET['filter_status'] ?? '') === $stVal ? 'selected' : '' ?>>
                            <?= htmlspecialchars($stLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Виконавець:</label>
                <select name="filter_worker">
                    <option value="0">Усі працівники</option>
                    <?php foreach ($workers as $w): ?>
                        <option value="<?= (int)$w['tab_number'] ?>" <?= (int)($_GET['filter_worker'] ?? 0) === (int)$w['tab_number'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($w['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Дата від:</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Дата до:</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Сортувати за:</label>
                <select name="sort">
                    <option value="op_id" <?= $currentSort === 'op_id' ? 'selected' : '' ?>>ID операції</option>
                    <option value="op_date" <?= $currentSort === 'op_date' ? 'selected' : '' ?>>Дата</option>
                    <option value="op_type" <?= $currentSort === 'op_type' ? 'selected' : '' ?>>Тип операції</option>
                    <option value="planned_hours" <?= $currentSort === 'planned_hours' ? 'selected' : '' ?>>Планові години</option>
                    <option value="status" <?= $currentSort === 'status' ? 'selected' : '' ?>>Статус</option>
                </select>
            </div>

            <div class="form-group">
                <label>Порядок:</label>
                <select name="dir">
                    <option value="DESC" <?= $currentDir === 'DESC' ? 'selected' : '' ?>>За спаданням (DESC)</option>
                    <option value="ASC" <?= $currentDir === 'ASC' ? 'selected' : '' ?>>За зростанням (ASC)</option>
                </select>
            </div>

            <div class="form-group" style="flex-direction: row; gap: 8px;">
                <button type="submit">Застосувати</button>
                <a href="/operations" class="btn-reset">Скинути</a>
            </div>
        </div>
    </form>

    <table border="1" cellpadding="6" cellspacing="0" id="operationsTable">
        <thead>
            <tr>
                <th><a class="sort-link" href="<?= htmlspecialchars(sort_url('op_id', $currentSort, $currentDir)) ?>">ID<?= sort_arrow('op_id', $currentSort, $currentDir) ?></a></th>
                <th><a class="sort-link" href="<?= htmlspecialchars(sort_url('op_type', $currentSort, $currentDir)) ?>">Тип операції<?= sort_arrow('op_type', $currentSort, $currentDir) ?></a></th>
                <th><a class="sort-link" href="<?= htmlspecialchars(sort_url('op_date', $currentSort, $currentDir)) ?>">Дата<?= sort_arrow('op_date', $currentSort, $currentDir) ?></a></th>
                <th><a class="sort-link" href="<?= htmlspecialchars(sort_url('planned_hours', $currentSort, $currentDir)) ?>">Години<?= sort_arrow('planned_hours', $currentSort, $currentDir) ?></a></th>
                <th><a class="sort-link" href="<?= htmlspecialchars(sort_url('status', $currentSort, $currentDir)) ?>">Статус<?= sort_arrow('status', $currentSort, $currentDir) ?></a></th>
                <th>Деталі та виконавці</th>
                <th>Дії</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr><td colspan="7" style="text-align: center;">Записів за обраними фільтрами не знайдено.</td></tr>
            <?php else: ?>
                <?php foreach ($items as $row): ?>
                <tr>
                    <td><?= (int)$row['op_id'] ?></td>
                    <td><?= htmlspecialchars($types[$row['op_type']] ?? $row['op_type']) ?></td>
                    <td><?= htmlspecialchars($row['op_date']) ?></td>
                    <td><?= htmlspecialchars((string)$row['planned_hours']) ?></td>
                    <td><?= htmlspecialchars($row['status']) ?></td>
                    <td>
                        <?php if ($row['op_type'] === 'PLOWING'): ?>
                            <b>Поле:</b> <?= htmlspecialchars($row['field_name'] ?? 'Не вказано') ?>
                        <?php elseif ($row['op_type'] === 'SOWING'): ?>
                            <b>Поле:</b> <?= htmlspecialchars($row['field_name'] ?? 'Не вказано') ?><br>
                            <b>Культура:</b> <?= htmlspecialchars($row['crop_name'] ?? 'Не вказано') ?>
                        <?php elseif ($row['op_type'] === 'HARVESTING'): ?>
                            <b>Поле:</b> <?= htmlspecialchars($row['field_name'] ?? 'Не вказано') ?><br>
                            <b>Зібрано:</b> <?= number_format((float)($row['harvested_tons'] ?? 0), 2, '.', ' ') ?> т
                        <?php elseif ($row['op_type'] === 'TRANSFER'): ?>
                            <b>Культура:</b> <?= htmlspecialchars($row['crop_name'] ?? 'Не вказано') ?><br>
                            <b>Склад:</b> <?= htmlspecialchars($row['warehouse_name'] ?? 'Не вказано') ?><br>
                            <b>Переміщено:</b> <?= number_format((float)($row['transferred_tons'] ?? 0), 2, '.', ' ') ?> т
                        <?php elseif ($row['op_type'] === 'SALES'): ?>
                            <b>Культура:</b> <?= htmlspecialchars($row['crop_name'] ?? 'Не вказано') ?><br>
                            <b>Покупець:</b> <?= htmlspecialchars($row['buyer_name'] ?? 'Не вказано') ?><br>
                            <b>Обсяг:</b> <?= number_format((float)($row['sold_tons'] ?? 0), 2, '.', ' ') ?> т
                            (<b>Сума:</b> <?= number_format((float)($row['sold_tons'] ?? 0) * (float)($row['price_per_ton'] ?? 0), 2, '.', ' ') ?> грн)
                        <?php else: ?>
                            —
                        <?php endif; ?>
                        <br><small><b>Виконавці:</b> <?= htmlspecialchars($row['workers_list'] ?? 'Не призначено') ?></small>
                    </td>
                    <td>
                        <a href="/operations?edit=<?= (int)$row['op_id'] ?>">Редагувати</a> |
                        <a href="/operations/delete?id=<?= (int)$row['op_id'] ?>" onclick="return confirm('Видалити цю операцію?')">Видалити</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
    function toggleFormFields() {
        const select = document.getElementById('op_type');
        if (!select) return;
        const selectedType = select.value.toLowerCase();

        document.querySelectorAll('.type-field').forEach(el => {
            el.style.display = 'none';
        });

        if (!selectedType) return;

        document.querySelectorAll(`.field-${selectedType}`).forEach(el => {
            el.style.display = 'flex';
        });
    }

    document.addEventListener('DOMContentLoaded', toggleFormFields);
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>
