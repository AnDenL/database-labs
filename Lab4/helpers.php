<?php
declare(strict_types=1);

function get_table_schema(mysqli $dbc, string $tableName, array $labels = []): array {
    $res    = mysqli_query($dbc, "SELECT * FROM `{$tableName}` LIMIT 0");
    $fields = mysqli_fetch_fields($res);

    $schema = [];

    $blobTypes = [
        MYSQLI_TYPE_TINY_BLOB,
        MYSQLI_TYPE_BLOB,
        MYSQLI_TYPE_MEDIUM_BLOB,
        MYSQLI_TYPE_LONG_BLOB,
    ];

    foreach ($fields as $field) {
        $isPk       = (bool)($field->flags & MYSQLI_PRI_KEY_FLAG);
        $isAuto     = (bool)($field->flags & MYSQLI_AUTO_INCREMENT_FLAG);
        $isUnsigned = (bool)($field->flags & MYSQLI_UNSIGNED_FLAG);
        $isBlob     = in_array($field->type, $blobTypes, true) && $field->charsetnr === 63;

        $bindType = match ($field->type) {
            MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_INT24, MYSQLI_TYPE_LONG, MYSQLI_TYPE_LONGLONG => 'i',
            MYSQLI_TYPE_FLOAT, MYSQLI_TYPE_DOUBLE, MYSQLI_TYPE_DECIMAL, MYSQLI_TYPE_NEWDECIMAL => 'd',
            default => 's',
        };

        $inputType = match (true) {
            $isBlob => 'file',
            $field->type === MYSQLI_TYPE_DATE => 'date',
            in_array($bindType, ['i', 'd'], true) => 'number',
            default => 'text',
        };

        $min  = null;
        $max  = null;
        $step = null;

        if ($bindType === 'i') {
            $step = '1';
            [$min, $max] = match ($field->type) {
                MYSQLI_TYPE_TINY     => $isUnsigned ? ['0', '255'] : ['-128', '127'],
                MYSQLI_TYPE_SHORT    => $isUnsigned ? ['0', '65535'] : ['-32768', '32767'],
                MYSQLI_TYPE_INT24    => $isUnsigned ? ['0', '16777215'] : ['-8388608', '8388607'],
                MYSQLI_TYPE_LONG     => $isUnsigned ? ['0', '4294967295'] : ['-2147483648', '2147483647'],
                MYSQLI_TYPE_LONGLONG => $isUnsigned ? ['0', '18446744073709551615'] : ['-9223372036854775808', '9223372036854775807'],
                default              => [null, null],
            };
        } elseif ($field->type === MYSQLI_TYPE_DECIMAL || $field->type === MYSQLI_TYPE_NEWDECIMAL) {
            $scale     = (int)$field->decimals;
            $intDigits = $field->length - ($scale > 0 ? $scale + 1 : 0) - ($isUnsigned ? 0 : 1);
            $intPart   = $intDigits > 0 ? str_repeat('9', $intDigits) : '0';
            $maxVal    = $scale > 0 ? $intPart . '.' . str_repeat('9', $scale) : $intPart;

            $step = $scale > 0 ? '0.' . str_repeat('0', $scale - 1) . '1' : '1';
            $min  = $isUnsigned ? '0' : '-' . $maxVal;
            $max  = $maxVal;
        } elseif ($field->type === MYSQLI_TYPE_FLOAT || $field->type === MYSQLI_TYPE_DOUBLE) {
            $step = 'any';
            if ($isUnsigned) {
                $min = '0';
            }
        }

        $schema[$field->name] = [
            'type'       => $bindType,
            'input_type' => $inputType,
            'is_pk'      => $isPk,
            'is_auto'    => $isAuto,
            'is_blob'    => $isBlob,
            'min'        => $min,
            'max'        => $max,
            'step'       => $step,
            'required'   => !$isBlob && (bool)($field->flags & MYSQLI_NOT_NULL_FLAG) && !$isAuto && !$isPk,
            'label'      => $labels[$field->name] ?? ucfirst(str_replace('_', ' ', $field->name)),
        ];
    }

    mysqli_free_result($res);
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
    $search    = trim((string)($_GET['q'] ?? ''));

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

        $sort    = $_GET['sort'] ?? $primaryKey;
        $sortCol = array_key_exists($sort, $columns) ? $sort : $primaryKey;
        $dir     = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $whereSql = '';
        $params   = [];
        $types    = '';

        if ($search !== '') {
            $likeClauses = [];
            foreach ($columns as $colName => $meta) {
                $likeClauses[] = "CAST(`{$colName}` AS CHAR) LIKE ?";
                $params[]      = "%{$search}%";
                $types        .= 's';
            }
            $whereSql = 'WHERE ' . implode(' OR ', $likeClauses);
        }

        $sql  = "SELECT * FROM `{$tableName}` {$whereSql} ORDER BY `{$sortCol}` {$dir}";
        $stmt = mysqli_prepare($dbc, $sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
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

            if ($meta['is_blob']) {
                $file = $_FILES[$colName] ?? null;
                $hasNewFile = $file && $file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name']);
                $deleteExisting = ($id > 0 && ($_POST['delete_' . $colName] ?? '') === '1');

                if ($hasNewFile) {
                    if (@getimagesize($file['tmp_name']) === false) {
                        header("Location: {$redirectUri}?error=" . urlencode("Файл у полі '{$meta['label']}' не є зображенням."));
                        exit;
                    }
                    $cols[]   = "`{$colName}`";
                    $types   .= 's';
                    $values[] = file_get_contents($file['tmp_name']);
                } elseif ($deleteExisting || $id === 0) {
                    $cols[]   = "`{$colName}`";
                    $types   .= 's';
                    $values[] = null;
                }
                continue;
            }

            $raw = $_POST[$colName] ?? null;
            if ($meta['required'] && ($raw === null || trim((string)$raw) === '')) {
                header("Location: {$redirectUri}?error=" . urlencode("Поле '{$meta['label']}' є обов'язковим."));
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
        $msg = ($e->getCode() === 1062) ? "Такий запис вже існує!" : $e->getMessage();
        header("Location: {$redirectUri}?error=" . urlencode("Помилка збереження: " . $msg));
        exit;
    }
}

function handle_delete(mysqli $dbc, string $tableName, string $primaryKey, string $redirectUri): void {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        header("Location: " . $redirectUri);
        exit;
    }

    try {
        $stmt = mysqli_prepare($dbc, "DELETE FROM `{$tableName}` WHERE `{$primaryKey}` = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected === 0) {
            header("Location: {$redirectUri}?error=" . urlencode("Запис #{$id} не знайдено або вже видалено."));
            exit;
        }
    } catch (mysqli_sql_exception $e) {
        $error = ($e->getCode() === 1451)
            ? "Неможливо видалити запис #{$id}, оскільки він використовується в операціях!"
            : $e->getMessage();
        header("Location: {$redirectUri}?error=" . urlencode($error));
        exit;
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
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($dbc);

        if ($affected === 0) {
            header("Location: /operations?error=" . urlencode("Операцію #{$id} не знайдено."));
            exit;
        }

        header("Location: /operations");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($dbc);
        header("Location: /operations?error=" . urlencode("Помилка видалення: " . $e->getMessage()));
        exit;
    }
}

function render_links(?string $value, string $baseUri, string $fallback = '—', bool $isList = false): string {
    if ($value === null || trim($value) === '') {
        return htmlspecialchars($fallback);
    }

    $items = $isList ? explode(',', $value) : [$value];
    $links = [];

    foreach ($items as $item) {
        $item = trim($item);
        if ($item !== '') {
            $url = $baseUri . '?q=' . urlencode($item);
            $links[] = '<a href="' . htmlspecialchars($url) . '" class="subtle-link">' . htmlspecialchars($item) . '</a>';
        }
    }

    return $links ? implode(', ', $links) : htmlspecialchars($fallback);
}

?>
