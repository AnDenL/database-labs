<?php
declare(strict_types=1);

require_once 'db.php';
require_once 'router.php';

$router = new Router();

require_once 'routes.php';

$router->route(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI'] ?? '/'
);
?>