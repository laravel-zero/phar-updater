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
 * This is partially patterned after Composer's self-update.
 */

namespace Humbug\SelfUpdate\Strategy;

use Humbug\SelfUpdate\Updater;

interface StrategyInterface
{
    /**
     * Download the remote Phar file.
     */
    public function download(Updater $updater): void;

    /**
     * Retrieve the current version available remotely, or false if none is available.
     */
    public function getCurrentRemoteVersion(Updater $updater): string|false;

    /**
     * Retrieve the current version of the local phar file.
     */
    public function getCurrentLocalVersion(Updater $updater): string;
}
