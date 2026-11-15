<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$cfg = require __DIR__ . '/.config.php';

try {
    $dbc = mysqli_connect($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db']);
    mysqli_set_charset($dbc, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('DB connect: ' . $e->getMessage());
    http_response_code(500);
    die('Помилка підключення до БД.');
}
