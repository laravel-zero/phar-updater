<?php

declare(strict_types=1);

namespace Humbug\SelfUpdate\Exception;

/**
 * Thrown when a signature cannot be verified because gpgv (GnuPG) cannot be run,
 * rather than because the signature itself is invalid.
 */
class GpgvUnavailableException extends SignatureVerificationException {}
