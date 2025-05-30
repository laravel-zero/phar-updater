<?php

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
use Humbug\SelfUpdate\Updater;
use Humbug\SelfUpdate\VersionParser;

use function file_get_contents;

class GithubStrategy implements StrategyInterface
{
    const API_URL = 'https://repo.packagist.org/p2/%s.json';

    const STABLE = 'stable';

    const UNSTABLE = 'unstable';

    const ANY = 'any';

    /**
     * @var string
     */
    private $localVersion;

    /**
     * @var string
     */
    private $remoteVersion;

    /**
     * @var string
     */
    private $remoteUrl;

    /**
     * @var string
     */
    private $pharName;

    /**
     * @var string
     */
    private $packageName;

    /**
     * @var string
     */
    private $stability = self::STABLE;

    /** {@inheritdoc} */
    public function download(Updater $updater)
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

    /** {@inheritdoc} */
    public function getCurrentRemoteVersion(Updater $updater)
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler([$updater, 'throwHttpRequestException']);
        $packageUrl = $this->getApiUrl();
        $package = json_decode(file_get_contents($packageUrl), true);
        restore_error_handler();

        if ($package === null || json_last_error() !== JSON_ERROR_NONE) {
            throw new JsonParsingException(
                'Error parsing JSON package data'
                .(function_exists('json_last_error_msg') ? ': '.json_last_error_msg() : '')
            );
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

    /** {@inheritdoc} */
    public function getCurrentLocalVersion(Updater $updater)
    {
        return $this->localVersion;
    }

    /**
     * Set version string of the local phar.
     *
     * @param  string  $version
     */
    public function setCurrentLocalVersion($version)
    {
        $this->localVersion = $version;
    }

    /**
     * Set Package name.
     *
     * @param  string  $name
     */
    public function setPackageName($name)
    {
        $this->packageName = $name;
    }

    /**
     * Get Package name.
     *
     * @return string
     */
    public function getPackageName()
    {
        return $this->packageName;
    }

    /**
     * Set phar file's name.
     *
     * @param  string  $name
     */
    public function setPharName($name)
    {
        $this->pharName = $name;
    }

    /**
     * Get phar file's name.
     *
     * @return string
     */
    public function getPharName()
    {
        return $this->pharName;
    }

    /**
     * Set target stability.
     *
     * @param  string  $stability
     */
    public function setStability($stability)
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
     *
     * @return string
     */
    public function getStability()
    {
        return $this->stability;
    }

    protected function getApiUrl()
    {
        return sprintf(self::API_URL, $this->getPackageName());
    }

    /** @param  array<mixed, mixed>  $package */
    protected function getDownloadUrl(array $package)
    {
        $baseUrl = preg_replace(
            '{\.git$}',
            '',
            $package['source']['url']
        );

        return sprintf(
            '%s/releases/download/%s/%s',
            $baseUrl,
            $this->remoteVersion,
            $this->getPharName()
        );
    }
}
