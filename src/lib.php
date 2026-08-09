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
