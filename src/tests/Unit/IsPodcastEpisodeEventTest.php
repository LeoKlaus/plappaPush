<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class IsPodcastEpisodeEventTest extends TestCase
{
    public function testTrueWhenEpisodeIdPresent(): void
    {
        $this->assertTrue(isPodcastEpisodeEvent(['libraryItemId' => 'abc123', 'episodeId' => 'ep-42']));
    }

    public function testTrueWhenEpisodeIdIsEmptyString(): void
    {
        // isset() only checks not-null — an empty-but-present episodeId (e.g. from a marker like
        // "episodeId=") still counts, matching extractDeepLink's own testAllowsEmptyValue case.
        $this->assertTrue(isPodcastEpisodeEvent(['episodeId' => '']));
    }

    public function testFalseWhenEpisodeIdAbsent(): void
    {
        $this->assertFalse(isPodcastEpisodeEvent(['libraryItemId' => 'abc123', 'libraryId' => 'lib1']));
    }

    public function testFalseForEmptyDeepLink(): void
    {
        $this->assertFalse(isPodcastEpisodeEvent([]));
    }
}
