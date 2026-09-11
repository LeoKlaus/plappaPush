<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../db.php';

header('Content-Type: application/json');

try {
    $connection = pushDbConnect();
    pushDbQuery($connection, 'SELECT 1');
    pg_close($connection);
} catch (\Throwable $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database unavailable.']);
    exit();
}

echo json_encode(['ok' => true]);
