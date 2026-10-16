<?php

declare(strict_types=1);

/**
 * Humbug.
 *
 * @category   Humbug
 *
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/phar-updater/blob/master/LICENSE New BSD License
 *
 * This class is partially patterned after Composer's version parser.
 */

namespace Humbug\SelfUpdate;

class VersionParser
{
    private const string MODIFIER = '[._-]?(?:(stable|beta|b|RC|alpha|a|patch|pl|p)(?:[.-]?(\d+))?)?([.-]?dev)?';

    /**
     * @param  array<mixed, string>  $versions
     */
    public function __construct(
        private readonly array $versions = [],
    ) {}

    /**
     * Get the most recent stable numbered version from versions passed to
     * constructor (if any), or false if there are none.
     */
    public function getMostRecentStable(): string|false
    {
        return $this->selectRecentStable();
    }

    /**
     * Get the most recent unstable numbered version from versions passed to
     * constructor (if any), or false if there are none.
     */
    public function getMostRecentUnStable(): string|false
    {
        return $this->selectRecentUnstable();
    }

    /**
     * Get the most recent stable or unstable numbered version from versions passed to
     * constructor (if any), or false if there are none.
     */
    public function getMostRecentAll(): string|false
    {
        return $this->selectRecentAll();
    }

    /**
     * Checks if given version string represents a stable numbered version.
     */
    public function isStable(string $version): bool
    {
        return $this->stable($version);
    }

    /**
     * Checks if given version string represents a 'pre-release' version, i.e.
     * it's unstable but not development level.
     */
    public function isPreRelease(string $version): bool
    {
        return ! $this->stable($version) && ! $this->development($version);
    }

    /**
     * Checks if given version string represents an unstable or dev-level
     * numbered version.
     */
    public function isUnstable(string $version): bool
    {
        return ! $this->stable($version);
    }

    /**
     * Checks if given version string represents a dev-level numbered version.
     */
    public function isDevelopment(string $version): bool
    {
        return $this->development($version);
    }

    private function selectRecentStable(): string|false
    {
        $candidates = [];
        foreach ($this->versions as $version) {
            if (! $this->stable($version)) {
                continue;
            }
            $candidates[] = $version;
        }
        if (empty($candidates)) {
            return false;
        }

        return $this->findMostRecent($candidates);
    }

    private function selectRecentUnstable(): string|false
    {
        $candidates = [];
        foreach ($this->versions as $version) {
            if ($this->stable($version) || $this->development($version)) {
                continue;
            }
            $candidates[] = $version;
        }
        if (empty($candidates)) {
            return false;
        }

        return $this->findMostRecent($candidates);
    }

    private function selectRecentAll(): string|false
    {
        $candidates = [];
        foreach ($this->versions as $version) {
            if ($this->development($version)) {
                continue;
            }
            $candidates[] = $version;
        }
        if (empty($candidates)) {
            return false;
        }

        return $this->findMostRecent($candidates);
    }

    /** @param  array<mixed, string>  $candidates */
    private function findMostRecent(array $candidates): string
    {
        $candidate = '';
        foreach ($candidates as $version) {
            if (version_compare($candidate, $version, '<')) {
                $candidate = $version;
            }
        }

        return $candidate;
    }

    private function stable(string $version): bool
    {
        $version = preg_replace('{#.+$}i', '', $version) ?? $version;
        if ($this->development($version)) {
            return false;
        }
        preg_match('{'.self::MODIFIER.'$}i', strtolower($version), $match);
        if (! empty($match[3])) {
            return false;
        }
        if (! empty($match[1])) {
            if ($match[1] === 'beta' || $match[1] === 'b'
            || $match[1] === 'alpha' || $match[1] === 'a'
            || $match[1] === 'rc') {
                return false;
            }
        }

        return true;
    }

    private function development(string $version): bool
    {
        if (str_starts_with($version, 'dev-') || str_ends_with($version, '-dev')) {
            return true;
        }
        if (preg_match("/-\d+-[a-z0-9]{8,}$/", $version) === 1) {
            return true;
        }

        return false;
    }
}
