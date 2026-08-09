<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ExtractDeepLinkTest extends TestCase
{
    public function testReturnsBodyUnchangedWhenNoDeepLinkSuffix(): void
    {
        [$body, $deepLink] = extractDeepLink('Just a plain notification body.');

        $this->assertSame('Just a plain notification body.', $body);
        $this->assertSame([], $deepLink);
    }

    public function testParsesSinglePair(): void
    {
        [$body, $deepLink] = extractDeepLink('New episode released [[plappa|libraryItemId=abc123]]');

        $this->assertSame('New episode released', $body);
        $this->assertSame(['libraryItemId' => 'abc123'], $deepLink);
    }

    public function testParsesMultiplePairs(): void
    {
        [$body, $deepLink] = extractDeepLink(
            'New episode [[plappa|libraryItemId=abc123|episodeId=ep-42]]'
        );

        $this->assertSame('New episode', $body);
        $this->assertSame(['libraryItemId' => 'abc123', 'episodeId' => 'ep-42'], $deepLink);
    }

    public function testTrimsWhitespaceAroundStrippedBody(): void
    {
        [$body, $deepLink] = extractDeepLink("New episode released   \n[[plappa|libraryItemId=abc123]]");

        $this->assertSame('New episode released', $body);
        $this->assertSame(['libraryItemId' => 'abc123'], $deepLink);
    }

    public function testSkipsPairWithoutEqualsSign(): void
    {
        [, $deepLink] = extractDeepLink('Body [[plappa|justakey]]');

        $this->assertSame([], $deepLink);
    }

    public function testSkipsPairWithEmptyKey(): void
    {
        [, $deepLink] = extractDeepLink('Body [[plappa|=value]]');

        $this->assertSame([], $deepLink);
    }

    public function testAllowsEmptyValue(): void
    {
        [, $deepLink] = extractDeepLink('Body [[plappa|key=]]');

        $this->assertSame(['key' => ''], $deepLink);
    }

    public function testOnlyMatchesSuffixAtEndOfBody(): void
    {
        // The [[plappa|...]] marker only strips when it is the trailing content.
        $body = '[[plappa|key=value]] is not a real deep link because more text follows.';
        [$resultBody, $deepLink] = extractDeepLink($body);

        $this->assertSame($body, $resultBody);
        $this->assertSame([], $deepLink);
    }

    public function testHandlesMultilineBody(): void
    {
        $body = "Line one\nLine two\n[[plappa|libraryItemId=abc123]]";
        [$resultBody, $deepLink] = extractDeepLink($body);

        $this->assertSame("Line one\nLine two", $resultBody);
        $this->assertSame(['libraryItemId' => 'abc123'], $deepLink);
    }

    public function testLastValueWinsForDuplicateKeys(): void
    {
        [, $deepLink] = extractDeepLink('Body [[plappa|key=first|key=second]]');

        $this->assertSame(['key' => 'second'], $deepLink);
    }
}
