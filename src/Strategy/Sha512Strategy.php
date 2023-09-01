<?php

namespace Humbug\SelfUpdate\Strategy;

use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Updater;

use function file_get_contents;

final class Sha512Strategy extends ShaStrategyAbstract
{
    /**
     * Retrieve the current version available remotely.
     *
     * @return string|bool
     */
    public function getCurrentRemoteVersion(Updater $updater)
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler([$updater, 'throwHttpRequestException']);
        $version = file_get_contents($this->getVersionUrl());
        restore_error_handler();
        if (false === $version) {
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
        if (! preg_match('%^[a-z0-9]{128}%', $version, $matches)) {
            throw new HttpRequestException(
                'Version request returned incorrectly formatted response.'
            );
        }

        return $matches[0];
    }

    /**
     * Retrieve the current version of the local phar file.
     *
     * @return string
     */
    public function getCurrentLocalVersion(Updater $updater)
    {
        return hash_file('sha512', $updater->getLocalPharFile());
    }
}
