<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Pushok\Payload\Alert;

final class BuildNotificationPayloadTest extends TestCase
{
    public function testEpisodeEventIsSilentWithNoAlert(): void
    {
        $payload = buildNotificationPayload(
            true,
            'New Episode!',
            'Episode body',
            ['libraryItemId' => 'abc123', 'episodeId' => 'ep-42']
        );

        $this->assertTrue($payload->isContentAvailable());
        $this->assertNull($payload->getAlert());
        $this->assertNull($payload->getSound());
    }

    public function testEpisodeEventFoldsTitleAndBodyIntoCustomPayload(): void
    {
        $payload = buildNotificationPayload(
            true,
            'New Episode!',
            'Episode body',
            ['libraryItemId' => 'abc123', 'episodeId' => 'ep-42']
        );

        $custom = $payload->getCustomValue('plappa');

        $this->assertSame('abc123', $custom['libraryItemId']);
        $this->assertSame('ep-42', $custom['episodeId']);
        $this->assertSame('New Episode!', $custom['title']);
        $this->assertSame('Episode body', $custom['body']);
    }

    public function testNonEpisodeEventUsesRegularAlert(): void
    {
        $payload = buildNotificationPayload(false, 'Backup Failed', 'Check the logs', []);

        $this->assertNull($payload->isContentAvailable());
        $this->assertInstanceOf(Alert::class, $payload->getAlert());
        $this->assertSame('Backup Failed', $payload->getAlert()->getTitle());
        $this->assertSame('Check the logs', $payload->getAlert()->getBody());
        $this->assertSame('default', $payload->getSound());
    }

    public function testNonEpisodeEventDoesNotAddTitleOrBodyToCustomPayload(): void
    {
        $payload = buildNotificationPayload(
            false,
            'Backup Failed',
            'Check the logs',
            ['errorMsg' => 'disk full']
        );

        $custom = $payload->getCustomValue('plappa');

        $this->assertSame(['errorMsg' => 'disk full'], $custom);
    }

    public function testOmitsCustomPayloadEntirelyWhenEmpty(): void
    {
        $payload = buildNotificationPayload(false, 'Backup Failed', 'Check the logs', []);

        // Pushok's own getCustomValue() throws a bare TypeError (not its InvalidPayloadException)
        // when setCustomValue() was never called at all — array_key_exists() rejects a null
        // haystack in PHP 8.1+. Asserting this specifically confirms setCustomValue('plappa', ...)
        // was genuinely skipped, not just called with an empty array.
        $this->expectException(\TypeError::class);
        $payload->getCustomValue('plappa');
    }
}
