<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../db.php';

use Ramsey\Uuid\Uuid;

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

if (!preg_match('/^[0-9a-fA-F]{32,200}$/', $deviceToken)) {
    http_response_code(400);
    echo json_encode(['error' => 'deviceToken does not look like a valid APNs token.']);
    exit();
}

$userId = null;
if (array_key_exists('userId', $data)) {
    if (!is_string($data['userId']) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $data['userId'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid userId.']);
        exit();
    }
    $userId = $data['userId'];
}

$connection = pushDbConnect();

try {
    if ($userId === null) {
        $userId = Uuid::uuid4()->toString();
    }
    
    pushDbQuery(
        $connection,
        'INSERT INTO pushtokens (devicetoken, user_id) VALUES ($1, $2)
         ON CONFLICT (devicetoken) DO UPDATE SET user_id = EXCLUDED.user_id',
        [$deviceToken, $userId]
    );

    http_response_code(201);
    echo json_encode(['userId' => $userId]);
} finally {
    pg_close($connection);
}
