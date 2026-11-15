<?php
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
                    <?php foreach (OP_TYPES as $val => $label): ?>
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
                <input min="0" max="9999.99" type="number" step="0.1" name="planned_hours" value="<?= htmlspecialchars((string)($edit_data['planned_hours'] ?? '')) ?>" required>
            </div>

            <div class="form-group">
                <label>Статус:</label>
                <select name="status" required>
                    <?php foreach (OP_STATUSES as $stVal => $stLabel): ?>
                        <option value="<?= $stVal ?>" <?= ($edit_data['status'] ?? 'COMPLETED') === $stVal ? 'selected' : '' ?>>
                            <?= htmlspecialchars($stLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

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

            <div class="form-group type-field field-harvesting">
                <label>Зібрано (тонн):</label>
                <input min="0" max="9999.99" type="number" step="0.01" name="harvested_tons" value="<?= htmlspecialchars((string)($edit_data['harvested_tons'] ?? '')) ?>" placeholder="0.00">
            </div>

            <div class="form-group type-field field-transfer">
                <label>Переміщено (тонн):</label>
                <input min="0" max="9999.99" type="number" step="0.01" name="transferred_tons" value="<?= htmlspecialchars((string)($edit_data['transferred_tons'] ?? '')) ?>" placeholder="0.00">
            </div>

            <div class="form-group type-field field-sales">
                <label>Продано (тонн):</label>
                <input min="0" max="9999.99" type="number" step="0.01" name="sold_tons" value="<?= htmlspecialchars((string)($edit_data['sold_tons'] ?? '')) ?>" placeholder="0.00">
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
                <input min="0" max="9999.99" type="number" step="0.01" name="price_per_ton" value="<?= htmlspecialchars((string)($edit_data['price_per_ton'] ?? '')) ?>" placeholder="0.00">
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

    <form method="get" action="/operations" class="filter-bar">
        <div class="filter-grid">
            <div class="form-group">
                <label>Тип операції:</label>
                <select name="filter_type">
                    <option value="">Усі типи</option>
                    <?php foreach (OP_TYPES as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $filterType === $val ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Статус:</label>
                <select name="filter_status">
                    <option value="">Усі статуси</option>
                    <?php foreach (OP_STATUSES as $stVal => $stLabel): ?>
                        <option value="<?= $stVal ?>" <?= $filterStatus === $stVal ? 'selected' : '' ?>>
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
                        <option value="<?= (int)$w['tab_number'] ?>" <?= $filterWorker === (int)$w['tab_number'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($w['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Дата від:</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>

            <div class="form-group">
                <label>Дата до:</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
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
                <?php foreach (['op_id' => 'ID', 'op_type' => 'Тип операції', 'op_date' => 'Дата', 'planned_hours' => 'Години', 'status' => 'Статус'] as $col => $title): ?>
                    <th><a class="sort-link" href="<?= htmlspecialchars(sort_url($col, $currentSort, $currentDir)) ?>"><?= $title ?><?= sort_arrow($col, $currentSort, $currentDir) ?></a></th>
                <?php endforeach; ?>
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
                    <td><?= htmlspecialchars(OP_TYPES[$row['op_type']] ?? $row['op_type']) ?></td>
                    <td><?= htmlspecialchars($row['op_date']) ?></td>
                    <td><?= htmlspecialchars((string)$row['planned_hours']) ?></td>
                    <td><?= htmlspecialchars($row['status']) ?></td>
                    <td>
                        <?php if ($row['op_type'] === 'PLOWING'): ?>
                            <b>Поле:</b> <?= render_links($row['field_name'] ?? null, '/fields') ?>
                        <?php elseif ($row['op_type'] === 'SOWING'): ?>
                            <b>Поле:</b> <?= render_links($row['field_name'] ?? null, '/fields') ?><br>
                            <b>Культура:</b> <?= render_links($row['crop_name'] ?? null, '/crops') ?>
                        <?php elseif ($row['op_type'] === 'HARVESTING'): ?>
                            <b>Поле:</b> <?= render_links($row['field_name'] ?? null, '/fields') ?><br>
                            <b>Зібрано:</b> <?= number_format((float)($row['harvested_tons'] ?? 0), 2, '.', ' ') ?> т
                        <?php elseif ($row['op_type'] === 'TRANSFER'): ?>
                            <b>Культура:</b> <?= render_links($row['crop_name'] ?? null, '/crops') ?><br>
                            <b>Склад:</b> <?= render_links($row['warehouse_name'] ?? null, '/warehouses') ?><br>
                            <b>Переміщено:</b> <?= number_format((float)($row['transferred_tons'] ?? 0), 2, '.', ' ') ?> т
                        <?php elseif ($row['op_type'] === 'SALES'): ?>
                            <b>Культура:</b> <?= render_links($row['crop_name'] ?? null, '/crops') ?><br>
                            <b>Покупець:</b> <?= render_links($row['buyer_name'] ?? null, '/buyers') ?><br>
                            <b>Обсяг:</b> <?= number_format((float)($row['sold_tons'] ?? 0), 2, '.', ' ') ?> т
                            (<b>Сума:</b> <?= number_format((float)($row['sold_tons'] ?? 0) * (float)($row['price_per_ton'] ?? 0), 2, '.', ' ') ?> грн)
                        <?php else: ?>
                            —
                        <?php endif; ?>
                        <br><small><b>Виконавці:</b> <?= render_links($row['workers_list'] ?? null, '/workers', 'Не призначено', true) ?></small>
                    </td>
                    <td>
                        <a href="/operations?edit=<?= (int)$row['op_id'] ?>">Редагувати</a> |
                        <form method="post" action="/operations/delete" class="inline-form" onsubmit="return confirm('Видалити операцію #<?= (int)$row['op_id'] ?>?')">
                            <input type="hidden" name="id" value="<?= (int)$row['op_id'] ?>">
                            <button type="submit" class="btn-delete">Видалити</button>
                        </form>
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
