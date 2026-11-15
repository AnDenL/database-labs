<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$cfg = require __DIR__ . '/.config.php';

try {
    $dbc = mysqli_connect($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db']);
    mysqli_set_charset($dbc, "utf8mb4");
} catch (Exception $e) {
    die("Помилка підключення до БД: " . $e->getMessage());
}
?>
