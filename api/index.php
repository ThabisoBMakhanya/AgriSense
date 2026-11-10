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
    case '/farm_journal.php':
        require __DIR__ . '/../farm_journal.php';
        break;
    case '/farm_journal_api.php':
        require __DIR__ . '/../farm_journal_api.php';
        break;
    case '/api/journal':
        require __DIR__ . '/../farm_journal_api.php';
        break;
    case '/logout.php':
        require __DIR__ . '/../logout.php';
        break;
    case '/profile.php':
        require __DIR__ . '/../profile.php';
        break;
    case '/recover.php':
        require __DIR__ . '/../recover.php';
        break;
    case '/register.php':
        require __DIR__ . '/../register.php';
        break;
    case '/rotate_recovery_code.php':
        require __DIR__ . '/../rotate_recovery_code.php';
        break;
    case '/send_advice.php':
        require __DIR__ . '/../send_advice.php';
        break;
    case '/api/cron/sms':
        $cronSecret = getenv('SMS_CRON_SECRET') ?: '';
        $authorization = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if ($cronSecret === '' || !hash_equals('Bearer ' . $cronSecret, $authorization)) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Unauthorized']);
            break;
        }
        require __DIR__ . '/../cron/generate_recommendations.php';
        break;
    default:
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not found';
}
