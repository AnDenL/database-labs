<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Управління операціями</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .type-field {
            display: none;
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

    <h2><?= $edit_data ? 'Редагувати операцію' : 'Додати операцію' ?></h2>

    <form method="post" action="/operations/save">
        <?php if ($edit_data): ?>
            <input type="hidden" name="op_id" value="<?= $edit_data['op_id'] ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="op_type">Тип операції:</label>
                <select name="op_type" id="op_type" onchange="toggleFormFields()" required>
                    <option value="">-- Оберіть тип --</option>
                    <?php
                    $types = [
                        'PLOWING'    => 'Оранка (PLOWING)',
                        'SOWING'     => 'Посів (SOWING)',
                        'HARVESTING' => 'Збір врожаю (HARVESTING)',
                        'TRANSFER'   => 'Переміщення на склад (TRANSFER)',
                        'SALES'      => 'Продаж (SALES)',
                    ];
                    foreach ($types as $val => $label):
                        $selected = ($edit_data['op_type'] ?? '') === $val ? 'selected' : '';
                    ?>
                        <option value="<?= $val ?>" <?= $selected ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Дата:</label>
                <input type="date" name="op_date" value="<?= htmlspecialchars($edit_data['op_date'] ?? date('Y-m-d')) ?>" required>
            </div>

            <div class="form-group">
                <label>Заплановано годин:</label>
                <input type="number" step="0.1" name="planned_hours" value="<?= htmlspecialchars((string)($edit_data['planned_hours'] ?? '')) ?>" required>
            </div>

            <div class="form-group">
                <label>Статус:</label>
                <select name="status" required>
                    <?php
                    $statuses = ['PLANNED', 'IN_PROGRESS', 'COMPLETED', 'POSTPONED', 'CANCELLED'];
                    foreach ($statuses as $st):
                    ?>
                        <option value="<?= $st ?>" <?= ($edit_data['status'] ?? 'COMPLETED') === $st ? 'selected' : '' ?>>
                            <?= $st ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group type-field field-plowing field-sowing field-harvesting">
                <label>Поле:</label>
                <select name="field_id">
                    <option value="">-- Не вибрано --</option>
                    <?php foreach ($fields as $f): ?>
                        <option value="<?= $f['field_id'] ?>" <?= ($edit_data['field_id'] ?? '') == $f['field_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group type-field field-sowing field-harvesting field-transfer field-sales">
                <label>Культура:</label>
                <select name="crop_id">
                    <option value="">-- Не вибрано --</option>
                    <?php foreach ($crops as $c): ?>
                        <option value="<?= $c['crop_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group type-field field-transfer field-sales">
                <label>Склад:</label>
                <select name="warehouse_id">
                    <option value="">-- Не вибрано --</option>
                    <?php foreach ($warehouses ?? [] as $w): ?>
                        <option value="<?= $w['warehouse_id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group type-field field-harvesting">
                <label>Зібрано (тон):</label>
                <input type="number" step="0.01" name="harvested_tons" value="<?= htmlspecialchars((string)($edit_data['harvested_tons'] ?? '')) ?>" placeholder="0.00">
            </div>

            <div class="form-group type-field field-transfer">
                <label>Переміщено (тон):</label>
                <input type="number" step="0.01" name="transferred_tons" placeholder="0.00">
            </div>

            <div class="form-group type-field field-sales">
                <label>Продано (тон):</label>
                <input type="number" step="0.01" name="sold_tons" value="<?= htmlspecialchars((string)($edit_data['sold_tons'] ?? '')) ?>" placeholder="0.00">
            </div>

            <div class="form-group type-field field-sales">
                <label>Покупець:</label>
                <select name="buyer_id">
                    <option value="">-- Не вибрано --</option>
                    <?php foreach ($buyers ?? [] as $b): ?>
                        <option value="<?= $b['buyer_id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group type-field field-sales">
                <label>Ціна за тонну:</label>
                <input type="number" step="0.01" name="price_per_ton" value="<?= htmlspecialchars((string)($edit_data['price_per_ton'] ?? '')) ?>" placeholder="0.00">
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
            <label>Робітники (Ctrl для вибору кількох):</label>
            <select name="workers[]" multiple size="4">
                <?php foreach ($workers as $w): ?>
                    <?php $sel = in_array($w['tab_number'], $edit_data['workers'] ?? [], true) ? 'selected' : ''; ?>
                    <option value="<?= $w['tab_number'] ?>" <?= $sel ?>><?= htmlspecialchars($w['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit"><?= $edit_data ? 'Оновити' : 'Створити' ?></button>
        <?php if ($edit_data): ?>
            <a href="/operations" style="margin-left: 10px;">Скасувати</a>
        <?php endif; ?>
    </form>

    <hr style="margin: 30px 0;">

    <h2>Список операцій</h2>
    <div class="table-filter" style="margin-bottom: 15px;">
      <label>Фільтр за типом: </label>
      <select id="filter_type" onchange="filterTable()">
        <option value="all">Усі операції</option>
        <option value="PLOWING">Оранка</option>
        <option value="SOWING">Посів</option>
        <option value="HARVESTING">Збирання</option>
        <option value="TRANSFER">Переміщення</option>
        <option value="SALES">Продаж</option>
      </select>
    </div>

    <table border="1" cellpadding="6" cellspacing="0" id="operationsTable" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f1f3f5;">
                <th>ID</th>
                <th>Тип</th>
                <th>Дата</th>
                <th>Години</th>
                <th>Статус</th>
                <th>Деталі</th>
                <th>Дії</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $row): ?>
            <tr class="op-row" data-type="<?= htmlspecialchars($row['op_type']) ?>">
                <td><?= $row['op_id'] ?></td>
                <td><?= htmlspecialchars($types[$row['op_type']] ?? $row['op_type']) ?></td>
                <td><?= htmlspecialchars($row['op_date']) ?></td>
                <td><?= htmlspecialchars((string)$row['planned_hours']) ?></td>
                <td><?= htmlspecialchars($row['status']) ?></td>
                <td>
                  <?php if ($row['op_type'] === 'PLOWING'): ?>
                    <b>Поле:</b> <?= htmlspecialchars($row['field_name'] ?? '—') ?>

                  <?php elseif ($row['op_type'] === 'SOWING'): ?>
                    <b>Поле:</b> <?= htmlspecialchars($row['field_name'] ?? '—') ?><br>
                    <b>Культура:</b> <?= htmlspecialchars($row['crop_name'] ?? '—') ?>

                  <?php elseif ($row['op_type'] === 'HARVESTING'): ?>
                    <b>Поле:</b> <?= htmlspecialchars($row['field_name'] ?? '—') ?><br>
                    <b>Культура:</b> <?= htmlspecialchars($row['crop_name'] ?? '—') ?><br>
                    <b>Обсяг:</b> <?= htmlspecialchars((string)($row['harvested_tons'] ?? 0)) ?> т

                  <?php elseif ($row['op_type'] === 'TRANSFER'): ?>
                    <b>Склад:</b> <?= htmlspecialchars($row['warehouse_name'] ?? '—') ?><br>
                    <b>Обсяг:</b> <?= htmlspecialchars((string)($row['moved_tons'] ?? 0)) ?> т

                  <?php elseif ($row['op_type'] === 'SALES'): ?>
                    <b>Покупець:</b> <?= htmlspecialchars($row['buyer_name'] ?? '—') ?><br>
                    <b>Сума:</b> <?= (float)($row['sold_tons'] ?? 0) * (float)($row['price_per_ton'] ?? 0) ?> грн

                  <?php else: ?>
                    —
                  <?php endif; ?>

                  <br><small><b>Робітники:</b> <?= htmlspecialchars($row['workers_list'] ?? 'Не призначено') ?></small>
                </td>
                <td>
                    <a href="/operations?edit=<?= $row['op_id'] ?>">Редагувати</a> |
                    <a href="/operations/delete?id=<?= $row['op_id'] ?>" onclick="return confirm('Видалити?')">Видалити</a>
                </td>
            </tr>
            <?php endforeach; ?>
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

    function filterTable() {
        const filterVal = document.getElementById('filter_type').value;
        const rows = document.querySelectorAll('.op-row');

        rows.forEach(row => {
            const rowType = row.getAttribute('data-type');
            if (filterVal === 'all' || rowType === filterVal) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', toggleFormFields);
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>
