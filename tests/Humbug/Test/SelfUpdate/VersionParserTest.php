<?php
/**
 * Humbug.
 *
 * @category   Humbug
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/pharupdater/blob/master/LICENSE New BSD License
 */

namespace Humbug\Test\SelfUpdate;

use Humbug\SelfUpdate\VersionParser;
use PHPUnit\Framework\TestCase;

class VersionParserTest extends TestCase
{
    // Stable Versions

    public function testShouldSelectNothingFromUnstablesIfStableRequested(): void
    {
        $versions = ['1.0.0a', '1.0.0alpha', '1.0.0-dev', 'dev-1.0.0', '1.0.0b',
            '1.0.0beta', '1.0.0rc', '1.0.0RC', ];
        $parser = new VersionParser($versions);
        $this->assertFalse($parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromStandardSelection(): void
    {
        $versions = ['1.0.0', '1.0.1', '1.1.0'];
        $parser = new VersionParser($versions);
        $this->assertSame('1.1.0', $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromMixedSelection(): void
    {
        $versions = ['1.0.0', '1.0.1', '1.1.0', '1.2.0a', '1.2.0b', '1.1.0rc'];
        $parser = new VersionParser($versions);
        $this->assertSame('1.1.0', $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromPrefixedSelection(): void
    {
        $versions = ['v1.0.0', 'v1.0.1', 'v1.1.0'];
        $parser = new VersionParser($versions);
        $this->assertSame('v1.1.0', $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromPartlyPrefixedSelection(): void
    {
        $versions = ['v1.0.0', 'v1.0.1', '1.1.0'];
        $parser = new VersionParser($versions);
        $this->assertSame('1.1.0', $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromPatchLevels(): void
    {
        $versions = ['1.0.0', '1.0.0-pl2', '1.0.0-pl3', '1.0.0-pl1'];
        $parser = new VersionParser($versions);
        $this->assertSame('1.0.0-pl3', $parser->getMostRecentStable());
    }

    public function testShouldSelectMostRecentVersionFromPatchLevels2(): void
    {
        $versions = ['1.0.0', '1.0.0pl2', '1.0.0pl3', '1.0.0pl1'];
        $parser = new VersionParser($versions);
        $this->assertSame('1.0.0pl3', $parser->getMostRecentStable());
    }

    // Unstable

    public function testShouldSelectNothingFromUnstablesIfUnstableRequested(): void
    {
        $versions = ['1.0.0', '1.0.1', '1.1.0'];
        $parser = new VersionParser($versions);
        $this->assertFalse($parser->getMostRecentUnstable());
    }

    public function testShouldSelectNothingFromStablesOrDevsIfUnstableRequested(): void
    {
        $versions = ['1.0.0', '1.0.1', '1.1.0-dev', 'dev-1.1.1'];
        $parser = new VersionParser($versions);
        $this->assertFalse($parser->getMostRecentUnstable());
    }

    public function testShouldSelectMostRecentUnstableVersionFromStandardSelection(): void
    {
        $versions = ['1.0.0a', '1.0.0alpha', '1.0.0-dev', 'dev-1.0.0', '1.0.0b',
            '1.0.0beta', '1.0.0rc', '1.0.0RC', ];
        $parser = new VersionParser($versions);
        $this->assertSame('1.0.0rc', $parser->getMostRecentUnstable());
    }

    public function testShouldSelectMostRecentUnstableVersionFromMixedSelection(): void
    {
        $versions = ['1.0.0', '1.0.1', '1.1.0', '1.2.0a', '1.2.0b', '1.1.0rc'];
        $parser = new VersionParser($versions);
        $this->assertSame('1.2.0b', $parser->getMostRecentUnstable());
    }

    public function testShouldSelectMostRecentUnstableVersionFromPrefixedSelection(): void
    {
        $versions = ['v1.0.0b', 'v1.0.1', 'v1.1.0'];
        $parser = new VersionParser($versions);
        $this->assertSame('v1.0.0b', $parser->getMostRecentUnstable());
    }

    public function testShouldSelectMostRecentUnstableVersionFromPartlyPrefixedSelection(): void
    {
        $versions = ['v1.0.0b', 'v1.0.0a', '1.1.0a'];
        $parser = new VersionParser($versions);
        $this->assertSame('1.1.0a', $parser->getMostRecentUnstable());
    }

    public function testShouldSelectMostRecentUnstableFromVaryingNumeralCounts(): void
    {
        $versions = ['1.0-dev', '1.0.0-alpha1'];
        $parser = new VersionParser($versions);
        $this->assertSame('1.0.0-alpha1', $parser->getMostRecentUnstable());
    }

    // All versions (ignoring stability)

    public function testShouldSelectMostRecentIgnoringStabilityExceptDevFromPrefixedSelection(): void
    {
        $versions = ['v1.0.0b', 'v1.0.1', 'v1.1.0a', 'v1.2.0-dev'];
        $parser = new VersionParser($versions);
        $this->assertSame('v1.1.0a', $parser->getMostRecentAll());
    }

    // Basic Version Category Checks

    public function testIsStable(): void
    {
        $parser = new VersionParser;
        $this->assertTrue($parser->isStable('1.0.0'));
        $this->assertFalse($parser->isStable('1.0.0b'));
        $this->assertFalse($parser->isStable('1.0.0-dev'));
        $this->assertFalse($parser->isStable('1.0.0-alpha1-5-g5b46ad8'));
    }

    public function testIsPreRelease(): void
    {
        $parser = new VersionParser;
        $this->assertFalse($parser->isPreRelease('1.0.0'));
        $this->assertTrue($parser->isPreRelease('1.0.0b'));
        $this->assertFalse($parser->isPreRelease('1.0.0-dev'));
        $this->assertFalse($parser->isPreRelease('1.0.0-alpha1-5-g5b46ad8'));
    }

    public function testIsUnstable(): void
    {
        $parser = new VersionParser;
        $this->assertFalse($parser->isUnstable('1.0.0'));
        $this->assertTrue($parser->isUnstable('1.0.0b'));
        $this->assertTrue($parser->isUnstable('1.0.0-dev'));
        $this->assertTrue($parser->isUnstable('1.0.0-alpha1-5-g5b46ad8'));
    }

    public function testIsDevelopment(): void
    {
        $parser = new VersionParser;
        $this->assertFalse($parser->isDevelopment('1.0.0'));
        $this->assertFalse($parser->isDevelopment('1.0.0b'));
        $this->assertTrue($parser->isDevelopment('1.0.0-dev'));
        $this->assertTrue($parser->isDevelopment('1.0.0-alpha1-5-g5b46ad8'));
    }
}
