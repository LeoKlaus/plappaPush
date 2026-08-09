<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../lib.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data) || !isset($data['deviceToken']) || !is_string($data['deviceToken'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid deviceToken.']);
    exit();
}

$deviceToken = $data['deviceToken'];

if (!isValidDeviceToken($deviceToken)) {
    http_response_code(400);
    echo json_encode(['error' => 'deviceToken does not look like a valid APNs token.']);
    exit();
}

$connection = pushDbConnect();
try {
    pushDbQuery($connection, 'DELETE FROM pushtokens WHERE devicetoken = $1', [$deviceToken]);
    http_response_code(200);
    echo json_encode(['ok' => true]);
} finally {
    pg_close($connection);
}
