<?php
declare(strict_types=1);

$requestedPath = (string)($_GET['route'] ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$requestedPath = '/' . ltrim(rawurldecode($requestedPath), '/');

switch ($requestedPath) {
    case '/':
    case '/index.php':
        require __DIR__ . '/../index.php';
        break;
    case '/about.php':
        require __DIR__ . '/../about.php';
        break;
    case '/dashboard.php':
        require __DIR__ . '/../dashboard.php';
        break;
    case '/logout.php':
        require __DIR__ . '/../logout.php';
        break;
    case '/profile.php':
        require __DIR__ . '/../profile.php';
        break;
    case '/register.php':
        require __DIR__ . '/../register.php';
        break;
    case '/send_advice.php':
        require __DIR__ . '/../send_advice.php';
        break;
    default:
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not found';
}
