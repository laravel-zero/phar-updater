<?php

declare(strict_types=1);

namespace Humbug\SelfUpdate\Signature;

use Humbug\SelfUpdate\Exception\GpgvUnavailableException;
use Humbug\SelfUpdate\Exception\InvalidArgumentException;
use Humbug\SelfUpdate\Exception\SignatureVerificationException;

/**
 * Verifies detached OpenPGP signatures (as created by `gpg --armor --detach-sign`)
 * using GnuPG's `gpgv` tool.
 *
 * Signatures are only checked against the public key given here, never against
 * the user's own keyring, and must be made by the key with the given fingerprint.
 */
final class GpgvVerifier implements SignatureVerifierInterface
{
    private const string ARMOR_HEADER = '-----BEGIN PGP PUBLIC KEY BLOCK-----';

    private const string ARMOR_FOOTER = '-----END PGP PUBLIC KEY BLOCK-----';

    private const string STATUS_PREFIX = '[GNUPG:] ';

    /** Status keywords that report the result of checking a single signature. */
    private const array SIGNATURE_RESULTS = ['GOODSIG', 'BADSIG', 'ERRSIG', 'EXPSIG', 'EXPKEYSIG', 'REVKEYSIG'];

    private readonly string $keyring;

    private readonly string $fingerprint;

    /**
     * @param  string  $publicKey  The signing public key, either ASCII-armored or binary.
     * @param  string  $fingerprint  The fingerprint of the signing key's primary key.
     * @param  string  $binary  The `gpgv` executable to run.
     */
    public function __construct(
        string $publicKey,
        string $fingerprint,
        private readonly string $binary = 'gpgv',
    ) {
        $this->keyring = self::dearmor($publicKey);
        $this->fingerprint = self::normaliseFingerprint($fingerprint);
    }

    public static function fromFile(string $publicKeyFile, string $fingerprint, string $binary = 'gpgv'): self
    {
        $publicKey = is_readable($publicKeyFile) ? file_get_contents($publicKeyFile) : false;
        if ($publicKey === false) {
            throw new InvalidArgumentException(sprintf(
                'Unable to read the public key file: %s.',
                $publicKeyFile
            ));
        }

        return new self($publicKey, $fingerprint, $binary);
    }

    #[\Override]
    public function verify(string $file, string $signatureFile): void
    {
        foreach ([$file, $signatureFile] as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                throw new SignatureVerificationException(sprintf(
                    'Unable to read file for signature verification: %s.',
                    $path
                ));
            }
        }

        $home = $this->createTemporaryHome();
        try {
            $keyring = $home.'/pinned.gpg';
            if (file_put_contents($keyring, $this->keyring) === false) {
                throw new SignatureVerificationException('Unable to write the temporary keyring.');
            }

            [$exitCode, $status, $errors] = $this->run(
                [$this->binary, '--status-fd', '1', '--keyring', $keyring, '--', $signatureFile, $file],
                $home
            );
        } finally {
            $this->removeDirectory($home);
        }

        $this->assertValidSignature($exitCode, $status, $errors);
    }

    private function assertValidSignature(int $exitCode, string $status, string $errors): void
    {
        $results = [];
        $signers = [];
        foreach (preg_split('/\R/', $status) ?: [] as $line) {
            if (! str_starts_with($line, self::STATUS_PREFIX)) {
                continue;
            }
            $fields = explode(' ', substr($line, strlen(self::STATUS_PREFIX)));
            if (in_array($fields[0], self::SIGNATURE_RESULTS, true)) {
                $results[] = $fields[0];
            }
            if ($fields[0] === 'VALIDSIG') {
                /** The primary key fingerprint is the last field, falling back to the signing key's */
                $signers[] = strtoupper($fields[10] ?? $fields[1] ?? '');
            }
        }

        if ($exitCode !== 0 || $results !== ['GOODSIG'] || count($signers) !== 1) {
            throw new SignatureVerificationException(sprintf(
                'The signature could not be verified (%s): %s',
                $results === [] ? sprintf('gpgv exited with code %d', $exitCode) : implode(', ', $results),
                trim($errors) ?: 'no further details.'
            ));
        }

        if (! hash_equals($this->fingerprint, $signers[0])) {
            throw new SignatureVerificationException(sprintf(
                'The signature was made by key %s, but key %s was expected.',
                $signers[0],
                $this->fingerprint
            ));
        }
    }

    /**
     * @param  list<string>  $command
     * @return array{int, string, string}
     */
    private function run(array $command, string $home): array
    {
        $environment = ['GNUPGHOME' => $home] + getenv();

        $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
        $exitCode = null;
        if ($process !== false) {
            fclose($pipes[0]);
            $status = (string) stream_get_contents($pipes[1]);
            $errors = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
        }

        /** Depending on the platform, a missing executable either fails to start or exits with 127 */
        if ($exitCode === null || ($exitCode === 127 && $status === '')) {
            throw new GpgvUnavailableException(sprintf(
                'Unable to run %s. GnuPG must be installed to verify the signature.',
                $this->binary
            ));
        }

        return [$exitCode, $status, $errors];
    }

    private function createTemporaryHome(): string
    {
        $home = sys_get_temp_dir().'/phar-updater-gpgv-'.bin2hex(random_bytes(8));
        if (! @mkdir($home, 0700)) {
            throw new SignatureVerificationException(sprintf(
                'Unable to create a temporary directory for signature verification: %s.',
                $home
            ));
        }

        return $home;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory.'/'.$entry;
            is_dir($path) && ! is_link($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($directory);
    }

    /**
     * Convert an ASCII-armored public key to the binary form `gpgv` reads keyrings in.
     */
    private static function dearmor(string $publicKey): string
    {
        $armored = trim($publicKey);
        if (! str_starts_with($armored, self::ARMOR_HEADER)) {
            /** OpenPGP packets always start with a byte with the high bit set */
            if ($publicKey === '' || (ord($publicKey[0]) & 0x80) === 0) {
                throw new InvalidArgumentException('The public key is not an OpenPGP public key.');
            }

            return $publicKey;
        }

        $lines = array_map(rtrim(...), preg_split('/\R/', $armored) ?: []);
        $footer = array_search(self::ARMOR_FOOTER, $lines, true);
        $separator = array_search('', $lines, true);
        if ($footer === false || $separator === false || $separator > $footer) {
            throw new InvalidArgumentException('The public key is not a valid ASCII-armored OpenPGP public key.');
        }

        /** Skip the armor headers, and the trailing checksum line if present */
        $body = array_filter(
            array_slice($lines, $separator + 1, $footer - $separator - 1),
            fn (string $line): bool => ! str_starts_with($line, '=')
        );
        $binary = base64_decode(implode('', $body), true);
        if ($binary === false || $binary === '') {
            throw new InvalidArgumentException('The public key is not a valid ASCII-armored OpenPGP public key.');
        }

        return $binary;
    }

    private static function normaliseFingerprint(string $fingerprint): string
    {
        $fingerprint = strtoupper((string) preg_replace('/\s+/', '', $fingerprint));
        if (! preg_match('/^(?:[0-9A-F]{40}|[0-9A-F]{64})$/', $fingerprint)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid fingerprint: %s. Expected the full 40 or 64 character key fingerprint.',
                $fingerprint
            ));
        }

        return $fingerprint;
    }
}
