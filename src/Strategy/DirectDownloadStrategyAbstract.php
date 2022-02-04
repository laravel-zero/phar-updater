<?php

namespace Humbug\SelfUpdate\Strategy;

use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Updater;

abstract class DirectDownloadStrategyAbstract implements StrategyInterface
{
    /** @var string */
    protected string $localVersion;

    abstract public function getDownloadUrl(): string;

    /** {@inheritdoc} */
    public function download(Updater $updater)
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler([$updater, 'throwHttpRequestException']);
        $result = file_get_contents($this->getDownloadUrl());
        restore_error_handler();
        if (false === $result) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s',
                $this->getDownloadUrl()
            ));
        }

        file_put_contents($updater->getTempPharFile(), $result);
    }

    /** {@inheritdoc} */
    public function getCurrentRemoteVersion(Updater $updater)
    {
        return 'latest';
    }

    public function setCurrentLocalVersion(string $version): void
    {
        $this->localVersion = $version;
    }

    /** {@inheritdoc} */
    public function getCurrentLocalVersion(Updater $updater)
    {
        return $this->localVersion;
    }
}
