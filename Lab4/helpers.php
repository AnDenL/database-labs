<?php
declare(strict_types=1);

function ident(string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
}

function get_db_views(mysqli $dbc): array {
    $rows = db_all(
        $dbc,
        'SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME'
    );
    return array_column($rows, 'TABLE_NAME');
}

function db_run(mysqli $dbc, string $sql, string $types = '', array $params = []): mysqli_stmt {
    $stmt = mysqli_prepare($dbc, $sql);
    if ($params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

function db_all(mysqli $dbc, string $sql, string $types = '', array $params = []): array {
    $stmt = db_run($dbc, $sql, $types, $params);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function db_one(mysqli $dbc, string $sql, string $types = '', array $params = []): ?array {
    return db_all($dbc, $sql, $types, $params)[0] ?? null;
}

function db_exec(mysqli $dbc, string $sql, string $types = '', array $params = []): int {
    $stmt     = db_run($dbc, $sql, $types, $params);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $affected;
}

function redirect(string $uri, ?string $error = null): void {
    header('Location: ' . $uri . ($error !== null ? '?error=' . urlencode($error) : ''));
    exit;
}

function get_str(string $key, string $default = ''): string {
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? $v : $default;
}

const BLOB_TYPES = [
    MYSQLI_TYPE_TINY_BLOB, MYSQLI_TYPE_BLOB, MYSQLI_TYPE_MEDIUM_BLOB, MYSQLI_TYPE_LONG_BLOB,
];

const INT_RANGES = [
    MYSQLI_TYPE_TINY     => ['-128', '127', '255'],
    MYSQLI_TYPE_SHORT    => ['-32768', '32767', '65535'],
    MYSQLI_TYPE_INT24    => ['-8388608', '8388607', '16777215'],
    MYSQLI_TYPE_LONG     => ['-2147483648', '2147483647', '4294967295'],
    MYSQLI_TYPE_LONGLONG => ['-9223372036854775808', '9223372036854775807', '18446744073709551615'],
];

function get_table_schema(mysqli $dbc, string $tableName, array $labels = []): array {
    $res    = mysqli_query($dbc, 'SELECT * FROM ' . ident($tableName) . ' LIMIT 0');
    $fields = mysqli_fetch_fields($res);
    mysqli_free_result($res);

    $schema = [];

    foreach ($fields as $f) {
        $isUnsigned = (bool)($f->flags & MYSQLI_UNSIGNED_FLAG);
        $isAuto     = (bool)($f->flags & MYSQLI_AUTO_INCREMENT_FLAG);
        $isPk       = (bool)($f->flags & MYSQLI_PRI_KEY_FLAG);
        $isBlob     = in_array($f->type, BLOB_TYPES, true) && $f->charsetnr === 63;
        $isInt      = array_key_exists($f->type, INT_RANGES);
        $isDecimal  = in_array($f->type, [MYSQLI_TYPE_DECIMAL, MYSQLI_TYPE_NEWDECIMAL], true);
        $isFloat    = in_array($f->type, [MYSQLI_TYPE_FLOAT, MYSQLI_TYPE_DOUBLE], true);

        $bindType = $isInt ? 'i' : (($isDecimal || $isFloat) ? 'd' : 's');

        $inputType = match (true) {
            $isBlob                       => 'file',
            $f->type === MYSQLI_TYPE_DATE => 'date',
            $bindType !== 's'             => 'number',
            default                       => 'text',
        };

        $min = $max = $step = null;

        if ($isInt) {
            [$signedMin, $signedMax, $unsignedMax] = INT_RANGES[$f->type];
            $step = '1';
            $min  = $isUnsigned ? '0' : $signedMin;
            $max  = $isUnsigned ? $unsignedMax : $signedMax;
        } elseif ($isDecimal) {
            $scale     = (int)$f->decimals;
            $intDigits = $f->length - ($scale > 0 ? $scale + 1 : 0) - ($isUnsigned ? 0 : 1);
            $intPart   = $intDigits > 0 ? str_repeat('9', $intDigits) : '0';
            $maxVal    = $scale > 0 ? $intPart . '.' . str_repeat('9', $scale) : $intPart;

            $step = $scale > 0 ? '0.' . str_repeat('0', $scale - 1) . '1' : '1';
            $min  = $isUnsigned ? '0' : '-' . $maxVal;
            $max  = $maxVal;
        } elseif ($isFloat) {
            $step = 'any';
            $min  = $isUnsigned ? '0' : null;
        }

        $schema[$f->name] = [
            'type'       => $bindType,
            'input_type' => $inputType,
            'is_pk'      => $isPk,
            'is_auto'    => $isAuto,
            'is_blob'    => $isBlob,
            'min'        => $min,
            'max'        => $max,
            'step'       => $step,
            'required'   => !$isBlob && !$isAuto && !$isPk && (bool)($f->flags & MYSQLI_NOT_NULL_FLAG),
            'label'      => $labels[$f->name] ?? ucfirst(str_replace('_', ' ', $f->name)),
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
    array $labels = [],
    bool $readOnly = false,
    array $viewSwitcher = []
): void {
    $tbl         = ident($tableName);
    $pk          = $primaryKey !== '' ? ident($primaryKey) : '';
    $edit_id     = !$readOnly ? (int)($_GET['edit'] ?? 0) : 0;
    $edit_data   = null;
    $items       = [];
    $columns     = [];
    $error       = get_str('error') ?: null;
    $search      = trim(get_str('q'));
    $currentSort = $primaryKey;
    $currentDir  = 'DESC';

    try {
        $columns = get_table_schema($dbc, $tableName, $labels);

        if ($edit_id > 0 && $pk !== '') {
            $edit_data = db_one($dbc, "SELECT * FROM $tbl WHERE $pk = ?", 'i', [$edit_id]);
            if ($edit_data === null) {
                $error = "Запис з ID {$edit_id} не знайдено.";
            }
        }

        $defaultSort = array_key_exists($primaryKey, $columns)
            ? $primaryKey
            : (string)(array_key_first($columns) ?? '');

        $sort        = get_str('sort', $defaultSort);
        $currentSort = array_key_exists($sort, $columns) ? $sort : $defaultSort;
        $currentDir  = strtoupper(get_str('dir')) === 'ASC' ? 'ASC' : 'DESC';

        $where  = '';
        $params = [];
        if ($search !== '') {
            $like  = '%' . addcslashes($search, '%_\\') . '%';
            $conds = [];
            foreach ($columns as $col => $meta) {
                if (!$meta['is_blob']) {
                    $conds[]  = 'CAST(' . ident($col) . ' AS CHAR) LIKE ?';
                    $params[] = $like;
                }
            }
            if ($conds) {
                $where = 'WHERE ' . implode(' OR ', $conds);
            }
        }

        $orderBy = $currentSort !== '' ? 'ORDER BY ' . ident($currentSort) . " $currentDir" : '';

        $items = db_all(
            $dbc,
            "SELECT * FROM $tbl $where $orderBy",
            str_repeat('s', count($params)),
            $params
        );
    } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
        http_response_code(500);
        $error = 'Помилка бази даних.';
    }

    require 'views/crud.php';
}

function auto_save(mysqli $dbc, string $tableName, string $primaryKey, string $back): void {
    try {
        $schema = get_table_schema($dbc, $tableName);
        $id     = (int)($_POST[$primaryKey] ?? 0);
        $data   = [];

        foreach ($schema as $col => $meta) {
            if ($meta['is_pk'] || $meta['is_auto']) {
                continue;
            }

            if ($meta['is_blob']) {
                $file = $_FILES[$col] ?? null;
                if ($file && $file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
                    if (@getimagesize($file['tmp_name']) === false) {
                        redirect($back, "Файл у полі '{$meta['label']}' не є зображенням.");
                    }
                    $data[$col] = ['s', file_get_contents($file['tmp_name'])];
                } elseif ($id === 0 || ($_POST['delete_' . $col] ?? '') === '1') {
                    $data[$col] = ['s', null];
                }
                continue;
            }

            $raw = trim((string)($_POST[$col] ?? ''));
            if ($meta['required'] && $raw === '') {
                redirect($back, "Поле '{$meta['label']}' є обов'язковим.");
            }

            $data[$col] = [$meta['type'], match ($meta['type']) {
                'i'     => (int)$raw,
                'd'     => (float)str_replace(',', '.', $raw),
                default => $raw,
            }];
        }

        $names  = array_map('ident', array_keys($data));
        $types  = '';
        $values = [];
        foreach ($data as [$t, $v]) {
            $types   .= $t;
            $values[] = $v;
        }

        if ($id > 0) {
            $set      = implode(', ', array_map(fn($n) => "$n = ?", $names));
            $sql      = 'UPDATE ' . ident($tableName) . " SET $set WHERE " . ident($primaryKey) . ' = ?';
            $types   .= 'i';
            $values[] = $id;
        } else {
            $marks = implode(', ', array_fill(0, count($names), '?'));
            $sql   = 'INSERT INTO ' . ident($tableName) . ' (' . implode(', ', $names) . ") VALUES ($marks)";
        }

        db_exec($dbc, $sql, $types, $values);
        redirect($back);
    } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
        redirect($back, 'Помилка збереження: ' . ($e->getCode() === 1062 ? 'такий запис вже існує!' : 'не вдалося зберегти дані.'));
    }
}

function handle_delete(mysqli $dbc, string $tableName, string $primaryKey, string $back): void {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        redirect($back);
    }

    try {
        $affected = db_exec($dbc, 'DELETE FROM ' . ident($tableName) . ' WHERE ' . ident($primaryKey) . ' = ?', 'i', [$id]);
    } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
        redirect($back, $e->getCode() === 1451
            ? "Неможливо видалити запис #{$id}, оскільки він використовується в операціях!"
            : 'Не вдалося видалити запис.');
    }

    redirect($back, $affected === 0 ? "Запис #{$id} не знайдено або вже видалено." : null);
}

function render_links(?string $value, string $baseUri, string $fallback = '—', bool $isList = false): string {
    $items = array_filter(array_map('trim', $isList ? explode(',', (string)$value) : [(string)$value]), 'strlen');

    if (!$items) {
        return htmlspecialchars($fallback);
    }

    $links = array_map(
        fn($item) => '<a href="' . htmlspecialchars($baseUri . '?q=' . urlencode($item)) . '" class="subtle-link">'
                   . htmlspecialchars($item) . '</a>',
        $items
    );
    return implode(', ', $links);
}
