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
use Humbug\SelfUpdate\Updater;

use function file_get_contents;

/**
 * @deprecated 1.0.4 SHA-1 is increasingly susceptible to collision attacks; use SHA-256 or SHA-512
 */
class ShaStrategy extends ShaStrategyAbstract
{
    /** {@inheritdoc} */
    public function getCurrentRemoteVersion(Updater $updater)
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler([$updater, 'throwHttpRequestException']);
        $version = file_get_contents($this->getVersionUrl());
        restore_error_handler();
        if ($version === false) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s',
                $this->getVersionUrl()
            ));
        }
        if (empty($version)) {
            throw new HttpRequestException(
                'Version request returned empty response.'
            );
        }
        if (! preg_match('%^[a-z0-9]{40}%', $version, $matches)) {
            throw new HttpRequestException(
                'Version request returned incorrectly formatted response.'
            );
        }

        return $matches[0];
    }

    /** {@inheritdoc} */
    public function getCurrentLocalVersion(Updater $updater)
    {
        return sha1_file($updater->getLocalPharFile());
    }
}
