<?php
declare(strict_types=1);

const OP_TYPES = [
    'PLOWING'    => 'Оранка (PLOWING)',
    'SOWING'     => 'Посів (SOWING)',
    'HARVESTING' => 'Збирання врожаю (HARVESTING)',
    'TRANSFER'   => 'Переміщення на склад (TRANSFER)',
    'SALES'      => 'Продаж партії (SALES)',
];

const OP_STATUSES = [
    'PLANNED'     => 'Заплановано (PLANNED)',
    'IN_PROGRESS' => 'Виконується (IN_PROGRESS)',
    'COMPLETED'   => 'Виконано (COMPLETED)',
    'POSTPONED'   => 'Відкладено (POSTPONED)',
    'CANCELLED'   => 'Скасовано (CANCELLED)',
];

const OP_DETAILS = [
    'PLOWING'    => ['plowing_ops',    ['field_id' => 'i']],
    'SOWING'     => ['sowing_ops',     ['field_id' => 'i', 'crop_id' => 'i']],
    'HARVESTING' => ['harvesting_ops', ['field_id' => 'i', 'harvested_tons' => 'd']],
    'TRANSFER'   => ['transfer_ops',   ['crop_id' => 'i', 'warehouse_id' => 'i', 'transferred_tons' => 'd']],
    'SALES'      => ['sales_ops',      ['crop_id' => 'i', 'buyer_id' => 'i', 'sold_tons' => 'd', 'price_per_ton' => 'd']],
];

const OP_SORT_COLUMNS = ['op_id', 'op_type', 'op_date', 'planned_hours', 'status'];

function op_child_tables(): array {
    return ['operation_workers', ...array_column(OP_DETAILS, 0)];
}

function to_float(mixed $v): float {
    return (float)str_replace(',', '.', (string)$v);
}

function operations_page(mysqli $dbc): void {
    $error     = get_str('error') ?: null;
    $edit_id   = (int)($_GET['edit'] ?? 0);
    $edit_data = null;

    if ($edit_id > 0) {
        $edit_data = db_one($dbc, '
            SELECT
                o.*,
                COALESCE(p.field_id, sow.field_id, h.field_id) AS field_id,
                COALESCE(sow.crop_id, t.crop_id, s.crop_id)    AS crop_id,
                t.warehouse_id,
                h.harvested_tons,
                t.transferred_tons,
                s.sold_tons,
                s.price_per_ton,
                s.buyer_id
            FROM operations o
            LEFT JOIN plowing_ops    p   ON o.op_id = p.op_id
            LEFT JOIN sowing_ops     sow ON o.op_id = sow.op_id
            LEFT JOIN harvesting_ops h   ON o.op_id = h.op_id
            LEFT JOIN transfer_ops   t   ON o.op_id = t.op_id
            LEFT JOIN sales_ops      s   ON o.op_id = s.op_id
            WHERE o.op_id = ?
        ', 'i', [$edit_id]);

        if ($edit_data) {
            $rows = db_all($dbc, 'SELECT tab_number FROM operation_workers WHERE op_id = ?', 'i', [$edit_id]);
            $edit_data['workers'] = array_map('intval', array_column($rows, 'tab_number'));
        }
    }

    $fields     = db_all($dbc, 'SELECT field_id, name FROM fields ORDER BY name');
    $crops      = db_all($dbc, 'SELECT crop_id, name FROM crops ORDER BY name');
    $workers    = db_all($dbc, 'SELECT tab_number, full_name FROM workers ORDER BY full_name');
    $warehouses = db_all($dbc, 'SELECT warehouse_id, name FROM warehouses ORDER BY name');
    $buyers     = db_all($dbc, 'SELECT buyer_id, name FROM buyers ORDER BY name');

    $filterType   = get_str('filter_type');
    $filterStatus = get_str('filter_status');
    $filterWorker = (int)($_GET['filter_worker'] ?? 0);
    $dateFrom     = get_str('date_from');
    $dateTo       = get_str('date_to');
    $isDate       = fn(string $d): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;

    $filters = []; 
    if (array_key_exists($filterType, OP_TYPES)) {
        $filters[] = ['o.op_type = ?', 's', $filterType];
    }
    if (array_key_exists($filterStatus, OP_STATUSES)) {
        $filters[] = ['o.status = ?', 's', $filterStatus];
    }
    if ($filterWorker > 0) {
        $filters[] = ['EXISTS (SELECT 1 FROM operation_workers owf WHERE owf.op_id = o.op_id AND owf.tab_number = ?)', 'i', $filterWorker];
    }
    if ($isDate($dateFrom)) {
        $filters[] = ['o.op_date >= ?', 's', $dateFrom];
    }
    if ($isDate($dateTo)) {
        $filters[] = ['o.op_date <= ?', 's', $dateTo];
    }

    $whereSql  = $filters ? 'WHERE ' . implode(' AND ', array_column($filters, 0)) : '';
    $bindTypes = implode('', array_column($filters, 1));
    $params    = array_column($filters, 2);

    $sort        = get_str('sort', 'op_id');
    $currentSort = in_array($sort, OP_SORT_COLUMNS, true) ? $sort : 'op_id';
    $currentDir  = strtoupper(get_str('dir')) === 'ASC' ? 'ASC' : 'DESC';

    $items = db_all($dbc, "
        SELECT
            o.op_id, o.op_type, o.op_date, o.planned_hours, o.status,
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
        LEFT JOIN plowing_ops    p   ON o.op_id = p.op_id
        LEFT JOIN sowing_ops     sow ON o.op_id = sow.op_id
        LEFT JOIN harvesting_ops h   ON o.op_id = h.op_id
        LEFT JOIN transfer_ops   t   ON o.op_id = t.op_id
        LEFT JOIN sales_ops      s   ON o.op_id = s.op_id
        LEFT JOIN fields         f   ON f.field_id = COALESCE(p.field_id, sow.field_id, h.field_id)
        LEFT JOIN crops          c   ON c.crop_id  = COALESCE(sow.crop_id, t.crop_id, s.crop_id)
        LEFT JOIN warehouses     w   ON w.warehouse_id = t.warehouse_id
        LEFT JOIN buyers         b   ON b.buyer_id = s.buyer_id
        LEFT JOIN operation_workers ow  ON o.op_id = ow.op_id
        LEFT JOIN workers        wrk ON ow.tab_number = wrk.tab_number
        $whereSql
        GROUP BY o.op_id
        ORDER BY o.$currentSort $currentDir
    ", $bindTypes, $params);

    require 'views/operations.php';
}

function save_operation(mysqli $dbc, array $post): void {
    $opId   = (int)($post['op_id'] ?? 0);
    $opType = (string)($post['op_type'] ?? '');
    $status = (string)($post['status'] ?? 'COMPLETED');
    $date   = (string)($post['op_date'] ?? date('Y-m-d'));
    $hours  = to_float($post['planned_hours'] ?? 0);

    if (!array_key_exists($opType, OP_TYPES)) {
        redirect('/operations', 'Невідомий тип операції.');
    }
    if (!array_key_exists($status, OP_STATUSES)) {
        redirect('/operations', 'Невідомий статус.');
    }

    $workerIds = is_array($post['workers'] ?? null)
        ? array_filter(array_map('intval', $post['workers']))
        : [];

    mysqli_begin_transaction($dbc);

    try {
        if ($opId > 0) {
            db_exec(
                $dbc,
                'UPDATE operations SET op_type = ?, op_date = ?, planned_hours = ?, status = ? WHERE op_id = ?',
                'ssdsi',
                [$opType, $date, $hours, $status, $opId]
            );
            foreach (op_child_tables() as $table) {
                db_exec($dbc, 'DELETE FROM ' . ident($table) . ' WHERE op_id = ?', 'i', [$opId]);
            }
        } else {
            db_exec(
                $dbc,
                'INSERT INTO operations (op_type, op_date, planned_hours, status) VALUES (?, ?, ?, ?)',
                'ssds',
                [$opType, $date, $hours, $status]
            );
            $opId = mysqli_insert_id($dbc);
        }

        [$table, $columns] = OP_DETAILS[$opType];
        $types  = 'i';
        $values = [$opId];
        foreach ($columns as $col => $t) {
            $types   .= $t;
            $values[] = $t === 'd' ? to_float($post[$col] ?? 0) : (int)($post[$col] ?? 0);
        }
        $names = implode(', ', array_map('ident', ['op_id', ...array_keys($columns)]));
        $marks = implode(', ', array_fill(0, count($values), '?'));
        db_exec($dbc, 'INSERT INTO ' . ident($table) . " ($names) VALUES ($marks)", $types, $values);

        foreach ($workerIds as $tabNumber) {
            db_exec($dbc, 'INSERT INTO operation_workers (op_id, tab_number) VALUES (?, ?)', 'ii', [$opId, $tabNumber]);
        }

        mysqli_commit($dbc);
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($dbc);
        error_log($e->getMessage());
        redirect('/operations', 'Помилка збереження операції.');
    }

    redirect('/operations');
}

function delete_operation(mysqli $dbc, int $id): void {
    if ($id <= 0) {
        redirect('/operations');
    }

    mysqli_begin_transaction($dbc);

    try {
        foreach (op_child_tables() as $table) {
            db_exec($dbc, 'DELETE FROM ' . ident($table) . ' WHERE op_id = ?', 'i', [$id]);
        }
        $affected = db_exec($dbc, 'DELETE FROM operations WHERE op_id = ?', 'i', [$id]);
        mysqli_commit($dbc);
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($dbc);
        error_log($e->getMessage());
        redirect('/operations', 'Помилка видалення операції.');
    }

    redirect('/operations', $affected === 0 ? "Операцію #{$id} не знайдено." : null);
}
