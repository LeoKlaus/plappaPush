<?php
declare(strict_types=1);

function isValidDeviceToken(string $deviceToken): bool
{
    return (bool) preg_match('/^[0-9a-fA-F]{32,200}$/', $deviceToken);
}

function isValidUserId(string $userId): bool
{
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $userId);
}

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

/**
 * Whether a decoded deep-link marker belongs to onPodcastEpisodeDownloaded — the only
 * Audiobookshelf notification event whose marker carries an episodeId (see
 * AbsNotificationEventCatalogue in the app for the full per-event variable list). Used to decide
 * whether notify.php should send a silent/background push (app decides locally whether to show a
 * notification and/or auto-download) instead of a regular alert push.
 */
function isPodcastEpisodeEvent(array $deepLink): bool
{
    return isset($deepLink['episodeId']);
}

/**
 * Builds the APNs payload for one target — the actual episode-vs-everything-else branch, pulled
 * out of notify.php so it's testable without a real APNs client or network call. Episode events
 * get a silent/background push (Payload::setContentAvailability(true) — pushok's Request class
 * derives apns-push-type/apns-priority from this automatically, see Request::prepareApnsHeaders)
 * with title/body folded into the custom payload instead of aps.alert. Every other event keeps
 * the regular alert+sound push.
 */
function buildNotificationPayload(bool $isEpisodeEvent, string $title, string $body, array $custom): \Pushok\Payload
{
    $payload = \Pushok\Payload::create();

    if ($isEpisodeEvent) {
        $payload->setContentAvailability(true);
        $custom['title'] = $title;
        $custom['body'] = $body;
    } else {
        $alert = \Pushok\Payload\Alert::create()->setTitle($title)->setBody($body);
        $payload->setAlert($alert)->setSound('default');
    }

    if ($custom) {
        $payload->setCustomValue('plappa', $custom);
    }

    return $payload;
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
        if (!isValidUserId($userId)) {
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
