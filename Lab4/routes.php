<?php
declare(strict_types=1);

require_once 'helpers.php';

// --- HOMEPAGE ---
$router->get('/', function() {
    $filename = "counter.txt";
    $counter = file_exists($filename) ? (int)file_get_contents($filename) : 0;
    $counter++;
    file_put_contents($filename, (string)$counter);

    require 'views/home.php';
});

// --- WORKERS ---
$router->get('/workers', function() use ($dbc) {
    render_page($dbc, 'workers', 'tab_number', 'Управління працівниками', '/workers', [
        'full_name' => 'ПІБ',
        'position'  => 'Посада',
    ]);
});
$router->post('/workers/save', function() use ($dbc) {
    auto_save($dbc, 'workers', 'tab_number', '/workers');
});
$router->get('/workers/delete', function() use ($dbc) {
    handle_delete($dbc, 'workers', 'tab_number', '/workers');
});

// --- FIELDS ---
$router->get('/fields', function() use ($dbc) {
    render_page($dbc, 'fields', 'field_id', 'Управління полями', '/fields', [
        'name' => 'Назва поля',
        'area' => 'Площа (га)',
    ]);
});
$router->post('/fields/save', function() use ($dbc) {
    auto_save($dbc, 'fields', 'field_id', '/fields');
});
$router->get('/fields/delete', function() use ($dbc) {
    handle_delete($dbc, 'fields', 'field_id', '/fields');
});

// --- BUYERS ---
$router->get('/buyers', function() use ($dbc) {
    render_page($dbc, 'buyers', 'buyer_id', 'Управління покупцями', '/buyers', [
        'name'  => 'Назва / ПІБ',
        'phone' => 'Телефон',
    ]);
});
$router->post('/buyers/save', function() use ($dbc) {
    auto_save($dbc, 'buyers', 'buyer_id', '/buyers');
});
$router->get('/buyers/delete', function() use ($dbc) {
    handle_delete($dbc, 'buyers', 'buyer_id', '/buyers');
});

// --- CROPS ---
$router->get('/crops', function() use ($dbc) {
    render_page($dbc, 'crops', 'crop_id', 'Управління культурами', '/crops', [
        'name'    => 'Назва культури',
        'variety' => 'Сорт',
    ]);
});
$router->post('/crops/save', function() use ($dbc) {
    auto_save($dbc, 'crops', 'crop_id', '/crops');
});
$router->get('/crops/delete', function() use ($dbc) {
    handle_delete($dbc, 'crops', 'crop_id', '/crops');
});

// --- WAREHOUSES ---
$router->get('/warehouses', function() use ($dbc) {
    render_page($dbc, 'warehouses', 'warehouse_id', 'Управління складами', '/warehouses', [
        'name'    => 'Назва',
        'capacity' => 'Місткість',
    ]);
});
$router->post('/warehouses/save', function() use ($dbc) {
    auto_save($dbc, 'warehouses', 'warehouse_id', '/warehouses');
});
$router->get('/warehouses/delete', function() use ($dbc) {
    handle_delete($dbc, 'warehouses', 'warehouse_id', '/warehouses');
});

// --- OPERATIONS ---
$router->get('/operations', function () use ($dbc) {
    $edit_id   = (int)($_GET['edit'] ?? 0);
    $edit_data = null;
    $error     = $_GET['error'] ?? null;

    // Підтягуємо операцію разом з усіма деталями з дочірніх таблиць
    if ($edit_id > 0) {
        $editSql = "
            SELECT
                o.*,
                COALESCE(p.field_id, sow.field_id, h.field_id) AS field_id,
                COALESCE(sow.crop_id, t.crop_id, s.crop_id) AS crop_id,
                t.warehouse_id,
                h.harvested_tons,
                t.transferred_tons,
                s.sold_tons,
                s.price_per_ton,
                s.buyer_id
            FROM operations o
            LEFT JOIN plowing_ops p ON o.op_id = p.op_id
            LEFT JOIN sowing_ops sow ON o.op_id = sow.op_id
            LEFT JOIN harvesting_ops h ON o.op_id = h.op_id
            LEFT JOIN transfer_ops t ON o.op_id = t.op_id
            LEFT JOIN sales_ops s ON o.op_id = s.op_id
            WHERE o.op_id = ?
        ";
        $stmt = mysqli_prepare($dbc, $editSql);
        mysqli_stmt_bind_param($stmt, "i", $edit_id);
        mysqli_stmt_execute($stmt);
        $edit_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
        mysqli_stmt_close($stmt);

        if ($edit_data) {
            $wStmt = mysqli_prepare($dbc, "SELECT tab_number FROM operation_workers WHERE op_id = ?");
            mysqli_stmt_bind_param($wStmt, "i", $edit_id);
            mysqli_stmt_execute($wStmt);
            $wRes = mysqli_stmt_get_result($wStmt);
            $edit_data['workers'] = array_map(
                'intval',
                array_column(mysqli_fetch_all($wRes, MYSQLI_ASSOC), 'tab_number')
            );
            mysqli_stmt_close($wStmt);
        }
    }

    $fields     = mysqli_fetch_all(mysqli_query($dbc, "SELECT field_id, name FROM fields ORDER BY name"), MYSQLI_ASSOC);
    $crops      = mysqli_fetch_all(mysqli_query($dbc, "SELECT crop_id, name FROM crops ORDER BY name"), MYSQLI_ASSOC);
    $workers    = mysqli_fetch_all(mysqli_query($dbc, "SELECT tab_number, full_name FROM workers ORDER BY full_name"), MYSQLI_ASSOC);
    $warehouses = mysqli_fetch_all(mysqli_query($dbc, "SELECT warehouse_id, name FROM warehouses ORDER BY name"), MYSQLI_ASSOC);
    $buyers     = mysqli_fetch_all(mysqli_query($dbc, "SELECT buyer_id, name FROM buyers ORDER BY name"), MYSQLI_ASSOC);

    // --- ФІЛЬТРАЦІЯ ТА СОРТУВАННЯ ---
    $filterType   = $_GET['filter_type'] ?? '';
    $filterStatus = $_GET['filter_status'] ?? '';
    $filterWorker = (int)($_GET['filter_worker'] ?? 0);
    $dateFrom     = $_GET['date_from'] ?? '';
    $dateTo       = $_GET['date_to'] ?? '';

    $where  = [];
    $params = [];
    $types  = '';

    $allowedTypes = ['PLOWING', 'SOWING', 'HARVESTING', 'TRANSFER', 'SALES'];
    if (in_array($filterType, $allowedTypes, true)) {
        $where[]  = "o.op_type = ?";
        $params[] = $filterType;
        $types   .= 's';
    }

    $allowedStatuses = ['PLANNED', 'IN_PROGRESS', 'COMPLETED', 'POSTPONED', 'CANCELLED'];
    if (in_array($filterStatus, $allowedStatuses, true)) {
        $where[]  = "o.status = ?";
        $params[] = $filterStatus;
        $types   .= 's';
    }

    if ($filterWorker > 0) {
        // EXISTS зберігає повний список працівників у GROUP_CONCAT
        $where[]  = "EXISTS (SELECT 1 FROM operation_workers owf WHERE owf.op_id = o.op_id AND owf.tab_number = ?)";
        $params[] = $filterWorker;
        $types   .= 'i';
    }

    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $where[]  = "o.op_date >= ?";
        $params[] = $dateFrom;
        $types   .= 's';
    }

    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $where[]  = "o.op_date <= ?";
        $params[] = $dateTo;
        $types   .= 's';
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $allowedSorts = [
        'op_id'         => 'o.op_id',
        'op_type'       => 'o.op_type',
        'op_date'       => 'o.op_date',
        'planned_hours' => 'o.planned_hours',
        'status'        => 'o.status',
    ];
    $sort    = $_GET['sort'] ?? 'op_id';
    $sortCol = $allowedSorts[$sort] ?? 'o.op_id';
    $dir     = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

    $sql = "
        SELECT
            o.op_id,
            o.op_type,
            o.op_date,
            o.planned_hours,
            o.status,
            f.name AS field_name,
            c.name AS crop_name,
            w.name AS warehouse_name,
            b.name AS buyer_name,
            h.harvested_tons,
            t.transferred_tons,
            s.sold_tons,
            s.price_per_ton,
            GROUP_CONCAT(wrk.full_name ORDER BY wrk.full_name SEPARATOR ', ') AS workers_list
        FROM operations o
        LEFT JOIN plowing_ops p ON o.op_id = p.op_id
        LEFT JOIN sowing_ops sow ON o.op_id = sow.op_id
        LEFT JOIN harvesting_ops h ON o.op_id = h.op_id
        LEFT JOIN transfer_ops t ON o.op_id = t.op_id
        LEFT JOIN sales_ops s ON o.op_id = s.op_id
        LEFT JOIN fields f ON f.field_id = COALESCE(p.field_id, sow.field_id, h.field_id)
        LEFT JOIN crops c ON c.crop_id = COALESCE(sow.crop_id, t.crop_id, s.crop_id)
        LEFT JOIN warehouses w ON w.warehouse_id = t.warehouse_id
        LEFT JOIN buyers b ON b.buyer_id = s.buyer_id
        LEFT JOIN operation_workers ow ON o.op_id = ow.op_id
        LEFT JOIN workers wrk ON ow.tab_number = wrk.tab_number
        {$whereSql}
        GROUP BY o.op_id
        ORDER BY {$sortCol} {$dir}
    ";

    $stmt = mysqli_prepare($dbc, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    require 'views/operations.php';
});

$router->post('/operations/save', function() use ($dbc) {
    save_operation($dbc, $_POST);
    header("Location: /operations");
    exit;
});

$router->get('/operations/delete', function() use ($dbc) {
    $id = (int)($_GET['id'] ?? 0);
    delete_operation($dbc, $id);
});
