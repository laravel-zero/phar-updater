<?php

declare(strict_types=1);

namespace Humbug\SelfUpdate\Strategy;

interface SignedStrategyInterface extends StrategyInterface
{
    /**
     * Retrieve the URL of the detached signature for the remote Phar file.
     */
    public function getSignatureUrl(): string;
}
