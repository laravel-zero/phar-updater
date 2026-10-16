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
use Humbug\SelfUpdate\Updater;

use function file_get_contents;

abstract class ShaStrategyAbstract implements StrategyInterface
{
    private const array SUPPORTED_SCHEMES = [
        'http',
        'https',
        'file',
    ];

    protected string $versionUrl = '';

    protected string $pharUrl = '';

    #[\Override]
    public function download(Updater $updater): void
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler([$updater, 'throwHttpRequestException']);
        $result = file_get_contents($this->getPharUrl());
        restore_error_handler();
        if ($result === false) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s',
                $this->getPharUrl()
            ));
        }

        file_put_contents($updater->getTempPharFile(), $result);
    }

    /**
     * Set URL to phar file.
     */
    public function setPharUrl(string $url): void
    {
        if (! $this->validateAllowedUrl($url)) {
            throw new InvalidArgumentException(
                sprintf('Invalid url passed as argument: %s.', $url)
            );
        }
        $this->pharUrl = $url;
    }

    /**
     * Get URL for phar file.
     */
    public function getPharUrl(): string
    {
        return $this->pharUrl;
    }

    /**
     * Set URL to version file.
     */
    public function setVersionUrl(string $url): void
    {
        if (! $this->validateAllowedUrl($url)) {
            throw new InvalidArgumentException(
                sprintf('Invalid url passed as argument: %s.', $url)
            );
        }
        $this->versionUrl = $url;
    }

    /**
     * Get URL for version file.
     */
    public function getVersionUrl(): string
    {
        return $this->versionUrl;
    }

    protected function validateAllowedUrl(string $url): bool
    {
        return
            filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), self::SUPPORTED_SCHEMES, true);
    }
}
