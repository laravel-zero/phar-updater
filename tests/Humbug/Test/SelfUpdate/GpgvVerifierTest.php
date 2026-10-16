<?php

declare(strict_types=1);

namespace Humbug\Test\SelfUpdate;

use Humbug\SelfUpdate\Exception\GpgvUnavailableException;
use Humbug\SelfUpdate\Exception\InvalidArgumentException;
use Humbug\SelfUpdate\Exception\SignatureVerificationException;
use Humbug\SelfUpdate\Signature\GpgvVerifier;
use PHPUnit\Framework\TestCase;

class GpgvVerifierTest extends TestCase
{
    private string $files;

    private string $phar;

    protected function setup(): void
    {
        exec('command -v gpgv', $output, $exitCode);
        if ($exitCode !== 0) {
            $this->markTestSkipped('This test requires gpgv (GnuPG) to be installed.');
        }

        $this->files = __DIR__.'/_files/gpg';
        $this->phar = __DIR__.'/_files/build/new.phar';
    }

    public function test_verifies_valid_signature(): void
    {
        $this->verifier()->verify($this->phar, $this->phar.'.asc');
        $this->addToAssertionCount(1);
    }

    public function test_verifies_valid_signature_with_binary_public_key(): void
    {
        GpgvVerifier::fromFile($this->files.'/test.pubkey.gpg', $this->fingerprint('test'))
            ->verify($this->phar, $this->phar.'.asc');
        $this->addToAssertionCount(1);
    }

    public function test_accepts_fingerprint_with_spaces_and_lowercase(): void
    {
        $fingerprint = strtolower(implode(' ', str_split($this->fingerprint('test'), 4)));

        GpgvVerifier::fromFile($this->files.'/test.pubkey.asc', $fingerprint)
            ->verify($this->phar, $this->phar.'.asc');
        $this->addToAssertionCount(1);
    }

    public function test_rejects_tampered_file(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('BADSIG');
        $this->verifier()->verify(__DIR__.'/_files/build/tampered.phar', $this->phar.'.asc');
    }

    public function test_rejects_signature_from_unknown_key(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('ERRSIG');
        $this->verifier()->verify($this->phar, $this->files.'/new.phar.other.asc');
    }

    public function test_rejects_signature_from_other_key_in_public_key_bundle(): void
    {
        $verifier = GpgvVerifier::fromFile($this->files.'/bundle.pubkey.asc', $this->fingerprint('test'));

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage(sprintf(
            'The signature was made by key %s, but key %s was expected.',
            $this->fingerprint('other'),
            $this->fingerprint('test')
        ));
        $verifier->verify($this->phar, $this->files.'/new.phar.other.asc');
    }

    public function test_rejects_multiple_signatures(): void
    {
        $verifier = GpgvVerifier::fromFile($this->files.'/bundle.pubkey.asc', $this->fingerprint('test'));

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('GOODSIG, GOODSIG');
        $verifier->verify($this->phar, $this->files.'/new.phar.multiple.asc');
    }

    public function test_rejects_signed_message_instead_of_detached_signature(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('not a detached signature');
        $this->verifier()->verify($this->phar, $this->files.'/attached.asc');
    }

    public function test_rejects_malformed_signature(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->verifier()->verify($this->phar, $this->files.'/garbage.asc');
    }

    public function test_rejects_missing_signature_file(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('Unable to read file for signature verification');
        $this->verifier()->verify($this->phar, $this->files.'/does-not-exist.asc');
    }

    public function test_throws_when_gpgv_is_not_installed(): void
    {
        $verifier = GpgvVerifier::fromFile($this->files.'/test.pubkey.asc', $this->fingerprint('test'), '/does/not/exist/gpgv');

        $this->expectException(GpgvUnavailableException::class);
        $this->expectExceptionMessage('Unable to run /does/not/exist/gpgv. GnuPG must be installed');
        $verifier->verify($this->phar, $this->phar.'.asc');
    }

    public function test_does_not_leave_temporary_files(): void
    {
        $pattern = sys_get_temp_dir().'/phar-updater-gpgv-*';
        $before = glob($pattern) ?: [];

        $this->verifier()->verify($this->phar, $this->phar.'.asc');

        $this->assertSame($before, glob($pattern) ?: []);
    }

    public function test_rejects_invalid_fingerprint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid fingerprint');
        GpgvVerifier::fromFile($this->files.'/test.pubkey.asc', substr($this->fingerprint('test'), -16));
    }

    public function test_rejects_invalid_public_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not an OpenPGP public key');
        new GpgvVerifier('not a key', $this->fingerprint('test'));
    }

    public function test_rejects_truncated_armored_public_key(): void
    {
        $key = (string) file_get_contents($this->files.'/test.pubkey.asc');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a valid ASCII-armored OpenPGP public key');
        new GpgvVerifier(substr($key, 0, 80), $this->fingerprint('test'));
    }

    public function test_rejects_unreadable_public_key_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to read the public key file');
        GpgvVerifier::fromFile($this->files.'/does-not-exist.asc', $this->fingerprint('test'));
    }

    private function verifier(): GpgvVerifier
    {
        return GpgvVerifier::fromFile($this->files.'/test.pubkey.asc', $this->fingerprint('test'));
    }

    private function fingerprint(string $name): string
    {
        return trim((string) file_get_contents($this->files.'/'.$name.'.fingerprint'));
    }
}
