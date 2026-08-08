<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../db.php';

use Pushok\AuthProvider;
use Pushok\Client;
use Pushok\Notification;
use Pushok\Payload;
use Pushok\Payload\Alert;

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

        $alert = Alert::create()->setTitle($title)->setBody($body);
        $payload = Payload::create()->setAlert($alert)->setSound('default');

        $custom = $deepLink;
        if ($target['instanceId'] !== null) {
            $custom['instanceId'] = $target['instanceId'];
        }
        if ($custom) {
            $payload->setCustomValue('plappa', $custom);
        }

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
                pushDbQuery($connection, 'DELETE FROM pushtokens WHERE devicetoken = $1', [$response->getDeviceToken()]);
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

function extractDeepLink(string $body): array
{
    if (!preg_match('/\[\[plappa\|(.*?)\]\]\s*$/s', $body, $matches)) {
        return [$body, []];
    }

    $stripped = trim(substr($body, 0, -strlen($matches[0])));
    $pairs = [];
    foreach (explode('|', $matches[1]) as $pair) {
        [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);
        if ($key !== null && $value !== null && $key !== '') {
            $pairs[$key] = $value;
        }
    }
    return [$stripped, $pairs];
}

function resolvePlappaTargets(array $urls): array
{
    $targets = [];
    foreach ($urls as $url) {
        if (!is_string($url)) {
            continue;
        }
        $parsed = parse_url($url);
        if (!$parsed || ($parsed['scheme'] ?? null) !== 'plappa' || empty($parsed['host'])) {
            continue;
        }
        $userId = $parsed['host'];
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $userId)) {
            continue;
        }
        $instanceId = null;
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
            if (!empty($queryParams['instance']) && is_string($queryParams['instance'])) {
                $instanceId = $queryParams['instance'];
            }
        }
        $targets[] = ['userId' => $userId, 'instanceId' => $instanceId];
    }
    return $targets;
}

function getDeviceTokensForUser(\PgSql\Connection $connection, string $userId): array
{
    $result = pushDbQuery($connection, 'SELECT devicetoken FROM pushtokens WHERE user_id = $1', [$userId]);
    $tokens = [];
    while ($row = pg_fetch_assoc($result)) {
        $tokens[] = $row['devicetoken'];
    }
    return $tokens;
}
