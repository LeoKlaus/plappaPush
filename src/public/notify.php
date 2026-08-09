<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../lib.php';
require __DIR__ . '/../pushTokenRepository.php';

use Pushok\AuthProvider;
use Pushok\Client;
use Pushok\Notification;

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data) || !isset($data['urls']) || !is_array($data['urls']) || !isset($data['title']) || !isset($data['body'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request. Expected {urls, title, body}.']);
    exit();
}

$title = (string) $data['title'];
$rawBody = (string) $data['body'];

[$body, $deepLink] = extractDeepLink($rawBody);
$targets = resolvePlappaTargets($data['urls']);
$isEpisodeEvent = isPodcastEpisodeEvent($deepLink);

if (!$targets) {
    echo json_encode(['ok' => true, 'sent' => 0]);
    exit();
}

$sentCount = 0;
$errors = [];

$connection = pushDbConnect();

try {
    $authProvider = AuthProvider\Token::create([
        'key_id' => getenv('KEY_ID'),
        'team_id' => getenv('TEAM_ID'),
        'app_bundle_id' => getenv('APP_BUNDLE_ID'),
        'private_key_path' => getenv('KEYFILE_PATH'),
        'private_key_secret' => null
    ]);
    $isProduction = strtolower((string) getenv('IS_PRODUCTION')) === 'true';
    $client = new Client($authProvider, $isProduction);

    $notificationCount = 0;
    foreach ($targets as $target) {
        $deviceTokens = getDeviceTokensForUser($connection, $target['userId']);
        if (!$deviceTokens) {
            continue;
        }

        $custom = $deepLink;
        if ($target['instanceId'] !== null) {
            $custom['instanceId'] = $target['instanceId'];
        }

        $payload = buildNotificationPayload($isEpisodeEvent, $title, $body, $custom);

        foreach ($deviceTokens as $deviceToken) {
            $client->addNotifications([new Notification($payload, $deviceToken)]);
            $notificationCount++;
        }
    }

    if ($notificationCount > 0) {
        $responses = $client->push();

        foreach ($responses as $response) {
            $statusCode = $response->getStatusCode();
            if ($statusCode === 410) {
                // Device unregistered/uninstalled — stop sending to it.
                deleteDeviceToken($connection, $response->getDeviceToken());
            }
            if ($statusCode === 200) {
                $sentCount++;
            } else {
                $errors[] = [
                    'deviceToken' => $response->getDeviceToken(),
                    'statusCode' => $statusCode,
                    'reasonPhrase' => $response->getReasonPhrase()
                ];
            }
        }
    }
} finally {
    pg_close($connection);
}

echo json_encode(['ok' => true, 'sent' => $sentCount, 'errors' => $errors]);

function getDeviceTokensForUser(\PgSql\Connection $connection, string $userId): array
{
    $result = pushDbQuery($connection, 'SELECT devicetoken FROM pushtokens WHERE user_id = $1', [$userId]);
    $tokens = [];
    while ($row = pg_fetch_assoc($result)) {
        $tokens[] = $row['devicetoken'];
    }
    return $tokens;
}
