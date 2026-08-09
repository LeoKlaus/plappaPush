<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../lib.php';
require __DIR__ . '/../pushTokenRepository.php';

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

$userId = null;
if (array_key_exists('userId', $data)) {
    if (!is_string($data['userId']) || !isValidUserId($data['userId'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid userId.']);
        exit();
    }
    $userId = $data['userId'];
}

$connection = pushDbConnect();

try {
    $userId = registerDeviceToken($connection, $deviceToken, $userId);

    http_response_code(201);
    echo json_encode(['userId' => $userId]);
} finally {
    pg_close($connection);
}
