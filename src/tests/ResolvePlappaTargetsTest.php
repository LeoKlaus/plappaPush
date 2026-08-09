<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ResolvePlappaTargetsTest extends TestCase
{
    private const VALID_UUID = 'a1b2c3d4-e5f6-4789-89ab-0123456789ab';

    public function testResolvesValidUrlWithoutInstance(): void
    {
        $targets = resolvePlappaTargets(['plappa://' . self::VALID_UUID]);

        $this->assertSame([
            ['userId' => self::VALID_UUID, 'instanceId' => null],
        ], $targets);
    }

    public function testResolvesValidUrlWithInstance(): void
    {
        $targets = resolvePlappaTargets(['plappa://' . self::VALID_UUID . '?instance=abs-1']);

        $this->assertSame([
            ['userId' => self::VALID_UUID, 'instanceId' => 'abs-1'],
        ], $targets);
    }

    public function testSkipsNonPlappaScheme(): void
    {
        $targets = resolvePlappaTargets(['https://' . self::VALID_UUID]);

        $this->assertSame([], $targets);
    }

    public function testSkipsUrlMissingHost(): void
    {
        $targets = resolvePlappaTargets(['plappa://']);

        $this->assertSame([], $targets);
    }

    public function testSkipsInvalidUuidHost(): void
    {
        $targets = resolvePlappaTargets(['plappa://not-a-uuid']);

        $this->assertSame([], $targets);
    }

    public function testSkipsNonStringEntries(): void
    {
        $targets = resolvePlappaTargets([123, null, ['nested' => true]]);

        $this->assertSame([], $targets);
    }

    public function testIgnoresEmptyInstanceQueryParam(): void
    {
        $targets = resolvePlappaTargets(['plappa://' . self::VALID_UUID . '?instance=']);

        $this->assertSame([
            ['userId' => self::VALID_UUID, 'instanceId' => null],
        ], $targets);
    }

    public function testHandlesMixOfValidAndInvalidUrls(): void
    {
        $secondUuid = 'ffffffff-ffff-4fff-8fff-ffffffffffff';
        $targets = resolvePlappaTargets([
            'plappa://' . self::VALID_UUID,
            'not a url at all',
            'plappa://bad-uuid',
            'plappa://' . $secondUuid . '?instance=abs-2',
        ]);

        $this->assertSame([
            ['userId' => self::VALID_UUID, 'instanceId' => null],
            ['userId' => $secondUuid, 'instanceId' => 'abs-2'],
        ], $targets);
    }
}
