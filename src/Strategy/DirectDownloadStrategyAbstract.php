<?php

declare(strict_types=1);

namespace Humbug\SelfUpdate\Strategy;

use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Updater;

abstract class DirectDownloadStrategyAbstract implements StrategyInterface
{
    protected string $localVersion;

    abstract public function getDownloadUrl(): string;

    #[\Override]
    public function download(Updater $updater): void
    {
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler([$updater, 'throwHttpRequestException']);
        $result = file_get_contents($this->getDownloadUrl());
        restore_error_handler();
        if ($result === false) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s',
                $this->getDownloadUrl()
            ));
        }

        file_put_contents($updater->getTempPharFile(), $result);
    }

    #[\Override]
    public function getCurrentRemoteVersion(Updater $updater): string
    {
        return 'latest';
    }

    public function setCurrentLocalVersion(string $version): void
    {
        $this->localVersion = $version;
    }

    #[\Override]
    public function getCurrentLocalVersion(Updater $updater): string
    {
        return $this->localVersion;
    }
}
