<?php

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
    /**
     * @var array<int, string>
     */
    private $versions;

    /**
     * @var string
     */
    private $modifier = '[._-]?(?:(stable|beta|b|RC|alpha|a|patch|pl|p)(?:[.-]?(\d+))?)?([.-]?dev)?';

    /**
     * @param  array<mixed, string>  $versions
     */
    public function __construct(array $versions = [])
    {
        $this->versions = $versions;
    }

    /**
     * Get the most recent stable numbered version from versions passed to
     * constructor (if any).
     *
     * @return string
     */
    public function getMostRecentStable()
    {
        return $this->selectRecentStable();
    }

    /**
     * Get the most recent unstable numbered version from versions passed to
     * constructor (if any).
     *
     * @return string
     */
    public function getMostRecentUnStable()
    {
        return $this->selectRecentUnstable();
    }

    /**
     * Get the most recent stable or unstable numbered version from versions passed to
     * constructor (if any).
     *
     * @return string
     */
    public function getMostRecentAll()
    {
        return $this->selectRecentAll();
    }

    /**
     * Checks if given version string represents a stable numbered version.
     *
     * @param  string  $version
     * @return bool
     */
    public function isStable($version)
    {
        return $this->stable($version);
    }

    /**
     * Checks if given version string represents a 'pre-release' version, i.e.
     * it's unstable but not development level.
     *
     * @param  string  $version
     * @return bool
     */
    public function isPreRelease($version)
    {
        return ! $this->stable($version) && ! $this->development($version);
    }

    /**
     * Checks if given version string represents an unstable or dev-level
     * numbered version.
     *
     * @param  string  $version
     * @return bool
     */
    public function isUnstable($version)
    {
        return ! $this->stable($version);
    }

    /**
     * Checks if given version string represents a dev-level numbered version.
     *
     * @param  string  $version
     * @return bool
     */
    public function isDevelopment($version)
    {
        return $this->development($version);
    }

    private function selectRecentStable()
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

    private function selectRecentUnstable()
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

    private function selectRecentAll()
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
    private function findMostRecent(array $candidates)
    {
        $candidate = '';
        foreach ($candidates as $version) {
            if (version_compare($candidate, $version, '<')) {
                $candidate = $version;
            }
        }

        return $candidate;
    }

    private function stable($version)
    {
        $version = preg_replace('{#.+$}i', '', $version);
        if ($this->development($version)) {
            return false;
        }
        preg_match('{'.$this->modifier.'$}i', strtolower($version), $match);
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

    private function development($version)
    {
        if (substr($version, 0, 4) === 'dev-' || substr($version, -4) === '-dev') {
            return true;
        }
        if (preg_match("/-\d+-[a-z0-9]{8,}$/", $version) == 1) {
            return true;
        }

        return false;
    }
}
