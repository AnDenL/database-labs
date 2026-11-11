<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $dbc = mysqli_connect("localhost", "andenl", "", "farm_db");
    mysqli_set_charset($dbc, "utf8mb4");
} catch (Exception $e) {
    die("Помилка підключення до БД: " . $e->getMessage());
}
?>
