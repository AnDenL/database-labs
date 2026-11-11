<?php
declare(strict_types=1);

function get_table_schema(mysqli $dbc, string $tableName, array $labels = []): array {
    $res    = mysqli_query($dbc, "SELECT * FROM `{$tableName}` LIMIT 0");
    $fields = mysqli_fetch_fields($res);

    $schema = [];
    foreach ($fields as $field) {
        $isPk   = (bool)($field->flags & MYSQLI_PRI_KEY_FLAG);
        $isAuto = (bool)($field->flags & MYSQLI_AUTO_INCREMENT_FLAG);

        $bindType = match ($field->type) {
            MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_LONG, MYSQLI_TYPE_LONGLONG => 'i',
            MYSQLI_TYPE_FLOAT, MYSQLI_TYPE_DOUBLE, MYSQLI_TYPE_DECIMAL, MYSQLI_TYPE_NEWDECIMAL => 'd',
            default => 's',
        };

        $inputType = match ($bindType) {
            'i' => 'number',
            'd' => 'number',
            default => 'text',
        };

        $schema[$field->name] = [
            'type'       => $bindType,
            'input_type' => $inputType,
            'is_pk'      => $isPk,
            'is_auto'    => $isAuto,
            'required'   => (bool)($field->flags & MYSQLI_NOT_NULL_FLAG) && !$isAuto && !$isPk,
            'label'      => $labels[$field->name] ?? ucfirst(str_replace('_', ' ', $field->name)),
        ];
    }

    return $schema;
}

function render_page(
    mysqli $dbc,
    string $tableName,
    string $primaryKey,
    string $pageTitle,
    string $baseUri,
    array $labels = []
): void {
    $edit_id   = (int)($_GET['edit'] ?? 0);
    $edit_data = null;
    $items     = [];
    $error     = $_GET['error'] ?? null;

    try {
        $columns = get_table_schema($dbc, $tableName, $labels);

        if ($edit_id > 0) {
            $stmt = mysqli_prepare($dbc, "SELECT * FROM `{$tableName}` WHERE `{$primaryKey}` = ?");
            mysqli_stmt_bind_param($stmt, "i", $edit_id);
            mysqli_stmt_execute($stmt);
            $edit_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
            mysqli_stmt_close($stmt);

            if ($edit_data === null) {
                $error = "Запис з ID {$edit_id} не знайдено.";
            }
        }

        $result = mysqli_query($dbc, "SELECT * FROM `{$tableName}` ORDER BY `{$primaryKey}` DESC");
        $items  = mysqli_fetch_all($result, MYSQLI_ASSOC);

    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        $error = "Помилка бази даних: " . $e->getMessage();
    }

    require 'views/crud.php';
}


function auto_save(mysqli $dbc, string $tableName, string $primaryKey, string $redirectUri): void {
    try {
        $schema = get_table_schema($dbc, $tableName);
        $id     = (int)($_POST[$primaryKey] ?? 0);
        $cols   = [];
        $values = [];
        $types  = "";

        foreach ($schema as $colName => $meta) {
            if ($meta['is_pk'] || $meta['is_auto']) continue;

            $raw = $_POST[$colName] ?? null;
            if ($meta['required'] && ($raw === null || trim((string)$raw) === '')) {
                header("Location: {$redirectUri}?error=" . urlencode("Поле '{$meta['label']}' обов'язкове"));
                exit;
            }

            $cols[]  = "`{$colName}`";
            $types  .= $meta['type'];
            $str     = trim((string)($raw ?? ''));

            $values[] = match ($meta['type']) {
                'i' => (int)$str,
                'd' => (float)str_replace(',', '.', $str),
                default => $str,
            };
        }

        if ($id > 0) {
            $setClause = implode(', ', array_map(fn($c) => "{$c} = ?", $cols));
            $sql       = "UPDATE `{$tableName}` SET {$setClause} WHERE `{$primaryKey}` = ?";
            $types    .= 'i';
            $values[]  = $id;
        } else {
            $colList      = implode(', ', $cols);
            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $sql          = "INSERT INTO `{$tableName}` ({$colList}) VALUES ({$placeholders})";
        }

        $stmt = mysqli_prepare($dbc, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$values);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        header("Location: " . $redirectUri);
        exit;

    } catch (mysqli_sql_exception $e) {
        $msg = ($e->getCode() === 1062) ? "Запис із такими даними вже існує!" : $e->getMessage();
        header("Location: {$redirectUri}?error=" . urlencode("Помилка збереження: " . $msg));
        exit;
    }
}

function handle_delete(mysqli $dbc, string $tableName, string $primaryKey, string $redirectUri): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = mysqli_prepare($dbc, "DELETE FROM `{$tableName}` WHERE `{$primaryKey}` = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } catch (mysqli_sql_exception $e) {
            $error = ($e->getCode() === 1451)
                ? "Неможливо видалити запис, оскільки він прив'язаний до інших операцій!"
                : $e->getMessage();
            header("Location: {$redirectUri}?error=" . urlencode($error));
            exit;
        }
    }
    header("Location: " . $redirectUri);
    exit;
}

function save_operation(mysqli $dbc, array $post): void {
    $opId         = (int)($post['op_id'] ?? 0);
    $opType       = $post['op_type'] ?? '';
    $opDate       = $post['op_date'] ?? date('Y-m-d');
    $plannedHours = (float)str_replace(',', '.', (string)($post['planned_hours'] ?? 0));
    $status       = $post['status'] ?? 'COMPLETED';
    $workerIds    = array_filter(array_map('intval', $post['workers'] ?? []));

    mysqli_begin_transaction($dbc);

    try {
        if ($opId > 0) {
            $stmt = mysqli_prepare($dbc, "UPDATE operations SET op_type = ?, op_date = ?, planned_hours = ?, status = ? WHERE op_id = ?");
            mysqli_stmt_bind_param($stmt, "ssdsi", $opType, $opDate, $plannedHours, $status, $opId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            foreach (['operation_workers', 'plowing_ops', 'sowing_ops', 'harvesting_ops', 'transfer_ops', 'sales_ops'] as $t) {
                $del = mysqli_prepare($dbc, "DELETE FROM `{$t}` WHERE op_id = ?");
                mysqli_stmt_bind_param($del, "i", $opId);
                mysqli_stmt_execute($del);
                mysqli_stmt_close($del);
            }
        } else {
            $stmt = mysqli_prepare($dbc, "INSERT INTO operations (op_type, op_date, planned_hours, status) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssds", $opType, $opDate, $plannedHours, $status);
            mysqli_stmt_execute($stmt);
            $opId = mysqli_insert_id($dbc);
            mysqli_stmt_close($stmt);
        }

        $fieldId     = (int)($post['field_id'] ?? 0);
        $cropId      = (int)($post['crop_id'] ?? 0);
        $warehouseId = (int)($post['warehouse_id'] ?? 0);
        $buyerId     = (int)($post['buyer_id'] ?? 0);

        switch ($opType) {
            case 'PLOWING':
                $stmt = mysqli_prepare($dbc, "INSERT INTO plowing_ops (op_id, field_id) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, "ii", $opId, $fieldId);
                break;

            case 'SOWING':
                $stmt = mysqli_prepare($dbc, "INSERT INTO sowing_ops (op_id, field_id, crop_id) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "iii", $opId, $fieldId, $cropId);
                break;

            case 'HARVESTING':
                $tons = (float)str_replace(',', '.', (string)($post['harvested_tons'] ?? 0));
                $stmt = mysqli_prepare($dbc, "INSERT INTO harvesting_ops (op_id, field_id, harvested_tons) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "iid", $opId, $fieldId, $tons);
                break;

            case 'TRANSFER':
                $tons = (float)str_replace(',', '.', (string)($post['transferred_tons'] ?? 0));
                $stmt = mysqli_prepare($dbc, "INSERT INTO transfer_ops (op_id, crop_id, warehouse_id, transferred_tons) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "iiid", $opId, $cropId, $warehouseId, $tons);
                break;

            case 'SALES':
                $tons  = (float)str_replace(',', '.', (string)($post['sold_tons'] ?? 0));
                $price = (float)str_replace(',', '.', (string)($post['price_per_ton'] ?? 0));
                $stmt  = mysqli_prepare($dbc, "INSERT INTO sales_ops (op_id, crop_id, buyer_id, sold_tons, price_per_ton) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "iiidd", $opId, $cropId, $buyerId, $tons, $price);
                break;
        }

        if (isset($stmt)) {
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        if (!empty($workerIds)) {
            $stmt = mysqli_prepare($dbc, "INSERT INTO operation_workers (op_id, tab_number) VALUES (?, ?)");
            foreach ($workerIds as $tabNum) {
                mysqli_stmt_bind_param($stmt, "ii", $opId, $tabNum);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($dbc);

        header("Location: /operations");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($dbc);
        header("Location: /operations?error=" . urlencode("Помилка збереження: " . $e->getMessage()));
        exit;
    }
}

function delete_operation(mysqli $dbc, int $id): void {
    if ($id <= 0) {
        header("Location: /operations");
        exit;
    }

    mysqli_begin_transaction($dbc);

    try {
        $childTables = ['operation_workers', 'plowing_ops', 'sowing_ops', 'harvesting_ops', 'transfer_ops', 'sales_ops'];
        foreach ($childTables as $table) {
            $stmt = mysqli_prepare($dbc, "DELETE FROM `{$table}` WHERE op_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $stmt = mysqli_prepare($dbc, "DELETE FROM operations WHERE op_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($dbc);
        header("Location: /operations");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($dbc);
        header("Location: /operations?error=" . urlencode("Помилка видалення: " . $e->getMessage()));
        exit;
    }
}
?>
