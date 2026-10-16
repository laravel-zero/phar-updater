<?php

declare(strict_types=1);

namespace Humbug\Test\SelfUpdate;

use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Exception\SignatureVerificationException;
use Humbug\SelfUpdate\Signature\GpgvVerifier;
use Humbug\SelfUpdate\Strategy\Sha512Strategy;
use Humbug\SelfUpdate\Strategy\StrategyInterface;
use Humbug\SelfUpdate\Updater;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class UpdaterSignatureTest extends TestCase
{
    private string $files;

    private string $tmp;

    private Sha512Strategy $strategy;

    protected function setup(): void
    {
        exec('command -v gpgv', $output, $exitCode);
        if ($exitCode !== 0) {
            $this->markTestSkipped('This test requires gpgv (GnuPG) to be installed.');
        }

        $this->tmp = sys_get_temp_dir();
        $this->files = __DIR__.'/_files';
    }

    protected function teardown(): void
    {
        @unlink($this->tmp.'/old.phar');
        @unlink($this->tmp.'/old-old.phar');
    }

    public function test_signature_url_defaults_to_phar_url(): void
    {
        $strategy = new Sha512Strategy;
        $strategy->setPharUrl('https://example.com/myapp.phar');
        $this->assertSame('https://example.com/myapp.phar.asc', $strategy->getSignatureUrl());

        $strategy->setSignatureUrl('https://example.com/signatures/myapp.phar.sig');
        $this->assertSame('https://example.com/signatures/myapp.phar.sig', $strategy->getSignatureUrl());
    }

    #[RunInSeparateProcess]
    public function test_update_phar_with_valid_signature(): void
    {
        $updater = $this->createUpdater('new.phar');

        $this->assertTrue($updater->update());
        $this->assertEquals('new', $this->getPharOutput($this->tmp.'/old.phar'));
        $this->assertFileDoesNotExist($updater->getTempSignatureFile());
    }

    #[RunInSeparateProcess]
    public function test_update_phar_fails_if_signed_by_other_key(): void
    {
        $updater = $this->createUpdater('new.phar');
        $this->strategy->setSignatureUrl('file://'.$this->files.'/gpg/new.phar.other.asc');

        $this->assertUpdateFails($updater, SignatureVerificationException::class);
    }

    #[RunInSeparateProcess]
    public function test_update_phar_fails_if_signature_does_not_match_phar(): void
    {
        /** The version hash and phar hash both match, but the signature is for new.phar */
        $updater = $this->createUpdater('tampered.phar', 'tampered.sha512.version');
        $this->strategy->setSignatureUrl('file://'.$this->files.'/build/new.phar.asc');

        $this->assertUpdateFails($updater, SignatureVerificationException::class);
    }

    #[RunInSeparateProcess]
    public function test_update_phar_fails_if_signature_is_missing(): void
    {
        $updater = $this->createUpdater('new.phar');
        $this->strategy->setSignatureUrl('file://'.$this->files.'/gpg/does-not-exist.asc');

        $this->assertUpdateFails($updater, HttpRequestException::class);
    }

    #[RunInSeparateProcess]
    public function test_update_phar_fails_if_strategy_does_not_support_signatures(): void
    {
        $updater = $this->createUpdater('new.phar');
        $updater->setStrategyObject(new class($this->files) implements StrategyInterface
        {
            public function __construct(private string $files) {}

            #[\Override]
            public function download(Updater $updater): void
            {
                copy($this->files.'/build/new.phar', $updater->getTempPharFile());
            }

            #[\Override]
            public function getCurrentRemoteVersion(Updater $updater): string
            {
                return 'new';
            }

            #[\Override]
            public function getCurrentLocalVersion(Updater $updater): string
            {
                return 'old';
            }
        });

        $this->assertUpdateFails(
            $updater,
            SignatureVerificationException::class,
            'does not implement Humbug\SelfUpdate\Strategy\SignedStrategyInterface'
        );
    }

    // Helpers

    private function createUpdater(string $phar, string $version = 'new.sha512.version'): Updater
    {
        copy($this->files.'/build/old.phar', $this->tmp.'/old.phar');
        chmod($this->tmp.'/old.phar', 0755);

        $this->strategy = new Sha512Strategy;
        $this->strategy->setPharUrl('file://'.$this->files.'/build/'.$phar);
        $this->strategy->setVersionUrl('file://'.$this->files.'/build/'.$version);

        $updater = new Updater($this->tmp.'/old.phar');
        $updater->setStrategyObject($this->strategy);
        $updater->setSignatureVerifier(GpgvVerifier::fromFile(
            $this->files.'/gpg/test.pubkey.asc',
            trim((string) file_get_contents($this->files.'/gpg/test.fingerprint'))
        ));

        return $updater;
    }

    /**
     * @param  class-string<\Throwable>  $exception
     */
    private function assertUpdateFails(Updater $updater, string $exception, string $message = ''): void
    {
        try {
            $updater->update();
            $this->fail('The update should have failed.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf($exception, $e, $e->getMessage());
            $this->assertStringContainsString($message, $e->getMessage());
        }

        $this->assertEquals('old', $this->getPharOutput($this->tmp.'/old.phar'));
        $this->assertFileDoesNotExist($updater->getTempPharFile());
        $this->assertFileDoesNotExist($updater->getTempSignatureFile());
    }

    private function getPharOutput(string $path): string
    {
        return exec('php '.escapeshellarg($path));
    }
}
