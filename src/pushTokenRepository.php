<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

use Ramsey\Uuid\Uuid;

function registerDeviceToken(\PgSql\Connection $connection, string $deviceToken, ?string $userId): string
{
    if ($userId === null) {
        $userId = Uuid::uuid4()->toString();
    }

    pushDbQuery(
        $connection,
        'INSERT INTO pushtokens (devicetoken, user_id) VALUES ($1, $2)
         ON CONFLICT (devicetoken) DO UPDATE SET user_id = EXCLUDED.user_id',
        [$deviceToken, $userId]
    );

    return $userId;
}

function deleteDeviceToken(\PgSql\Connection $connection, string $deviceToken): void
{
    pushDbQuery($connection, 'DELETE FROM pushtokens WHERE devicetoken = $1', [$deviceToken]);
}
