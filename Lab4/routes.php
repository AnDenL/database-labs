<?php
declare(strict_types=1);

require_once 'helpers.php';
require_once 'ops.php';

// --- HOMEPAGE ---
$router->get('/', function () {
    $file    = 'counter.txt';
    $counter = (is_file($file) ? (int)file_get_contents($file) : 0) + 1;
    file_put_contents($file, (string)$counter, LOCK_EX);

    require 'views/home.php';
});

$crud = [
    '/workers' => [
        'table' => 'workers', 'pk' => 'tab_number', 'title' => 'Управління працівниками',
        'labels' => ['full_name' => 'ПІБ', 'position' => 'Посада', 'salary' => 'Зарплата', 'photo' => 'Фото'],
    ],
    '/fields' => [
        'table' => 'fields', 'pk' => 'field_id', 'title' => 'Управління полями',
        'labels' => ['name' => 'Назва поля', 'area' => 'Площа (га)'],
    ],
    '/buyers' => [
        'table' => 'buyers', 'pk' => 'buyer_id', 'title' => 'Управління покупцями',
        'labels' => ['name' => 'Назва / ПІБ', 'phone' => 'Телефон'],
    ],
    '/crops' => [
        'table' => 'crops', 'pk' => 'crop_id', 'title' => 'Управління культурами',
        'labels' => ['name' => 'Назва культури', 'variety' => 'Сорт'],
    ],
    '/warehouses' => [
        'table' => 'warehouses', 'pk' => 'warehouse_id', 'title' => 'Управління складами',
        'labels' => ['name' => 'Назва', 'capacity' => 'Місткість'],
    ],
];

foreach ($crud as $uri => $c) {
    $router->get($uri,             fn() => render_page($dbc, $c['table'], $c['pk'], $c['title'], $uri, $c['labels']));
    $router->post("$uri/save",     fn() => auto_save($dbc, $c['table'], $c['pk'], $uri));
    $router->post("$uri/delete",   fn() => handle_delete($dbc, $c['table'], $c['pk'], $uri));
}

// --- OPERATIONS ---
$router->get('/operations',         fn() => operations_page($dbc));
$router->post('/operations/save',   fn() => save_operation($dbc, $_POST));
$router->post('/operations/delete', fn() => delete_operation($dbc, (int)($_POST['id'] ?? 0)));

// --- REPORT ---
$router->get('/report', function () use ($dbc) {
    $items = db_all($dbc, 'SELECT * FROM v_operations_report ORDER BY op_date DESC, op_id DESC');
    require 'views/report.php';
});
