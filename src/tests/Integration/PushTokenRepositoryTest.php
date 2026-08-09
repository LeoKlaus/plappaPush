<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PushTokenRepositoryTest extends TestCase
{
    private \PgSql\Connection $connection;

    protected function setUp(): void
    {
        $this->connection = pushDbConnect();
        pushDbQuery($this->connection, 'TRUNCATE pushtokens');
    }

    protected function tearDown(): void
    {
        pg_close($this->connection);
    }

    public function testRegisterDeviceTokenGeneratesNewUserIdWhenNoneProvided(): void
    {
        $deviceToken = str_repeat('a', 64);

        $userId = registerDeviceToken($this->connection, $deviceToken, null);

        $this->assertTrue(isValidUserId($userId));
        $this->assertSame($userId, $this->storedUserIdFor($deviceToken));
    }

    public function testRegisterDeviceTokenGeneratesDifferentUserIdsForDifferentTokens(): void
    {
        $first = registerDeviceToken($this->connection, str_repeat('a', 64), null);
        $second = registerDeviceToken($this->connection, str_repeat('b', 64), null);

        $this->assertNotSame($first, $second);
    }

    public function testRegisterDeviceTokenHonorsProvidedUserId(): void
    {
        $deviceToken = str_repeat('c', 64);
        $providedUserId = 'a1b2c3d4-e5f6-4789-89ab-0123456789ab';

        $userId = registerDeviceToken($this->connection, $deviceToken, $providedUserId);

        $this->assertSame($providedUserId, $userId);
        $this->assertSame($providedUserId, $this->storedUserIdFor($deviceToken));
    }

    public function testRegisteringSameTokenTwiceDoesNotDuplicateRows(): void
    {
        $deviceToken = str_repeat('d', 64);
        $userId = 'a1b2c3d4-e5f6-4789-89ab-0123456789ab';

        registerDeviceToken($this->connection, $deviceToken, $userId);
        registerDeviceToken($this->connection, $deviceToken, $userId);

        $this->assertSame(1, $this->rowCountForToken($deviceToken));
    }

    public function testRegisteringSameTokenWithNewUserIdReassignsOwnership(): void
    {
        $deviceToken = str_repeat('e', 64);
        $originalUserId = 'a1b2c3d4-e5f6-4789-89ab-0123456789ab';
        $newUserId = 'ffffffff-ffff-4fff-8fff-ffffffffffff';

        registerDeviceToken($this->connection, $deviceToken, $originalUserId);
        registerDeviceToken($this->connection, $deviceToken, $newUserId);

        $this->assertSame($newUserId, $this->storedUserIdFor($deviceToken));
        $this->assertSame(1, $this->rowCountForToken($deviceToken));
    }

    public function testDeleteDeviceTokenRemovesOnlyThatToken(): void
    {
        $keep = str_repeat('f', 64);
        $remove = str_repeat('0', 64);
        registerDeviceToken($this->connection, $keep, null);
        registerDeviceToken($this->connection, $remove, null);

        deleteDeviceToken($this->connection, $remove);

        $this->assertSame(0, $this->rowCountForToken($remove));
        $this->assertSame(1, $this->rowCountForToken($keep));
    }

    public function testDeleteDeviceTokenIsSafeForUnknownToken(): void
    {
        $deviceToken = str_repeat('9', 64);

        deleteDeviceToken($this->connection, $deviceToken);

        $this->assertSame(0, $this->rowCountForToken($deviceToken));
    }

    private function storedUserIdFor(string $deviceToken): ?string
    {
        $result = pushDbQuery($this->connection, 'SELECT user_id FROM pushtokens WHERE devicetoken = $1', [$deviceToken]);
        $row = pg_fetch_assoc($result);
        return $row['user_id'] ?? null;
    }

    private function rowCountForToken(string $deviceToken): int
    {
        $result = pushDbQuery($this->connection, 'SELECT COUNT(*) AS count FROM pushtokens WHERE devicetoken = $1', [$deviceToken]);
        $row = pg_fetch_assoc($result);
        return (int) $row['count'];
    }
}
