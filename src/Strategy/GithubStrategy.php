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
 * This class is partially patterned after Composer's self-update.
 */

namespace Humbug\SelfUpdate\Strategy;

use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Exception\InvalidArgumentException;
use Humbug\SelfUpdate\Exception\JsonParsingException;
use Humbug\SelfUpdate\Exception\RuntimeException;
use Humbug\SelfUpdate\Updater;
use Humbug\SelfUpdate\VersionParser;
use JsonException;

use function file_get_contents;

class GithubStrategy implements SignedStrategyInterface
{
    const string API_URL = 'https://repo.packagist.org/p2/%s.json';

    const string STABLE = 'stable';

    const string UNSTABLE = 'unstable';

    const string ANY = 'any';

    private string $localVersion = '';

    private string|false $remoteVersion = false;

    private string $remoteUrl = '';

    private string $pharName = '';

    private string $packageName = '';

    private string $stability = self::STABLE;

    #[\Override]
    public function download(Updater $updater): void
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler([$updater, 'throwHttpRequestException']);
        $result = file_get_contents($this->remoteUrl);
        restore_error_handler();
        if ($result === false) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s',
                $this->remoteUrl
            ));
        }

        file_put_contents($updater->getTempPharFile(), $result);
    }

    #[\Override]
    public function getSignatureUrl(): string
    {
        if ($this->remoteUrl === '') {
            throw new RuntimeException(
                'The remote version must be resolved before the signature URL is available.'
            );
        }

        return $this->remoteUrl.'.asc';
    }

    #[\Override]
    public function getCurrentRemoteVersion(Updater $updater): string|false
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler([$updater, 'throwHttpRequestException']);
        $packageUrl = $this->getApiUrl();
        $contents = file_get_contents($packageUrl);
        restore_error_handler();
        if ($contents === false) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s',
                $packageUrl
            ));
        }

        try {
            $package = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new JsonParsingException('Error parsing JSON package data: '.$e->getMessage(), previous: $e);
        }

        if (! is_array($package)) {
            throw new JsonParsingException('Error parsing JSON package data: expected an object.');
        }

        $versions = array_column($package['packages'][$this->getPackageName()], 'version');
        $versionParser = new VersionParser($versions);
        if ($this->getStability() === self::STABLE) {
            $this->remoteVersion = $versionParser->getMostRecentStable();
        } elseif ($this->getStability() === self::UNSTABLE) {
            $this->remoteVersion = $versionParser->getMostRecentUnstable();
        } else {
            $this->remoteVersion = $versionParser->getMostRecentAll();
        }

        /**
         * Setup remote URL if there's an actual version to download.
         */
        if (! empty($this->remoteVersion)) {
            $remoteVersionPackages = array_filter($package['packages'][$this->getPackageName()], function (array $package) {
                return $package['version'] === $this->remoteVersion;
            });
            $chosenVersion = reset($remoteVersionPackages);

            $this->remoteUrl = $this->getDownloadUrl($chosenVersion);
        }

        return $this->remoteVersion;
    }

    #[\Override]
    public function getCurrentLocalVersion(Updater $updater): string
    {
        return $this->localVersion;
    }

    /**
     * Set version string of the local phar.
     */
    public function setCurrentLocalVersion(string $version): void
    {
        $this->localVersion = $version;
    }

    /**
     * Set Package name.
     */
    public function setPackageName(string $name): void
    {
        $this->packageName = $name;
    }

    /**
     * Get Package name.
     */
    public function getPackageName(): string
    {
        return $this->packageName;
    }

    /**
     * Set phar file's name.
     */
    public function setPharName(string $name): void
    {
        $this->pharName = $name;
    }

    /**
     * Get phar file's name.
     */
    public function getPharName(): string
    {
        return $this->pharName;
    }

    /**
     * Set target stability.
     */
    public function setStability(string $stability): void
    {
        if ($stability !== self::STABLE && $stability !== self::UNSTABLE && $stability !== self::ANY) {
            throw new InvalidArgumentException(
                'Invalid stability value. Must be one of "stable", "unstable" or "any".'
            );
        }
        $this->stability = $stability;
    }

    /**
     * Get target stability.
     */
    public function getStability(): string
    {
        return $this->stability;
    }

    protected function getApiUrl(): string
    {
        return sprintf(self::API_URL, $this->getPackageName());
    }

    /** @param  array<mixed, mixed>  $package */
    protected function getDownloadUrl(array $package): string
    {
        $baseUrl = preg_replace(
            '{\.git$}',
            '',
            $package['source']['url']
        ) ?? $package['source']['url'];

        return sprintf(
            '%s/releases/download/%s/%s',
            $baseUrl,
            $this->remoteVersion,
            $this->getPharName()
        );
    }
}
