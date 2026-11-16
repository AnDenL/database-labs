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
        'labels' => ['name' => 'Назва поля', 'area' => 'Площа (га)', 'actual' => 'В використанні'],
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
    $views = get_db_views($dbc);

    if (!$views) {
        http_response_code(404);
        die('У базі даних не знайдено жодного VIEW.');
    }

    $viewMeta = [
        'v_operations_report' => [
            'title'  => 'Зведений звіт по операціях',
            'labels' => [
                'op_id'          => 'ID',
                'op_type'        => 'Тип',
                'op_date'        => 'Дата',
                'planned_hours'  => 'Години',
                'status'         => 'Статус',
                'field_name'     => 'Поле',
                'crop_name'      => 'Культура',
                'warehouse_name' => 'Склад',
                'buyer_name'     => 'Покупець',
                'tons'           => 'Тонни',
                'total_revenue'  => 'Сума, грн',
                'workers'        => 'Працівники',
            ],
        ],
        'v_active_fields' => [
            'title'  => 'Зведений звіт по активним полям',
            'labels' => [
                'field_id' => 'ID',
                'name'     => 'Назва поля',
                'area'     => 'Площа',
            ],
        ],
        'v_sales_report' => [
            'title'  => 'Зведений звіт з продажів',
            'labels' => [
                'op_id'          => 'ID',
                'op_date'        => 'Дата',
                'crop_name'      => 'Культура',
                'buyer_name'     => 'Покупець',
                'sold_tons'      => 'Продано тон',
                'price_per_ton'  => 'Ціна за тонну грн',
                'total_sum'      => 'Загальна сума грн',
            ],
        ],
    ];

    $requested = get_str('view');
    $current   = in_array($requested, $views, true) ? $requested : $views[0];
    $meta      = $viewMeta[$current] ?? [];

    render_page(
        $dbc,
        $current,
        '',
        $meta['title'] ?? "Звіт (VIEW): {$current}",
        '/report',
        $meta['labels'] ?? [],
        true,
        [
            'views'   => $views,
            'current' => $current,
            'meta'    => $viewMeta,
        ]
    );
});
