<?php

declare(strict_types=1);

namespace Humbug\SelfUpdate\Signature;

use Humbug\SelfUpdate\Exception\SignatureVerificationException;

interface SignatureVerifierInterface
{
    /**
     * Verify that the signature file is a valid signature of the file.
     *
     * @throws SignatureVerificationException if the signature cannot be verified
     */
    public function verify(string $file, string $signatureFile): void;
}
