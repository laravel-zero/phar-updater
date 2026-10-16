<?php

declare(strict_types=1);

/**
 * Humbug.
 *
 * @category   Humbug
 *
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/pharupdater/blob/master/LICENSE New BSD License
 */

namespace Humbug\Test\SelfUpdate;

use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Exception\InvalidArgumentException;
use Humbug\SelfUpdate\Exception\RuntimeException;
use Humbug\SelfUpdate\Exception\SignatureVerificationException;
use Humbug\SelfUpdate\Signature\GpgvVerifier;
use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class UpdaterGithubStrategyTest extends TestCase
{
    private string $files;

    private Updater $updater;

    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir();
        $this->files = __DIR__.'/_files';
        $this->updater = new Updater($this->files.'/test.phar', Updater::STRATEGY_GITHUB);
    }

    protected function tearDown(): void
    {
        $this->deleteTempPhars();
    }

    public function test_construction(): void
    {
        $updater = new Updater(null, Updater::STRATEGY_GITHUB);
        $this->assertInstanceOf(GithubStrategy::class, $updater->getStrategy());
    }

    public function test_set_current_local_version(): void
    {
        $this->updater->getStrategy()->setCurrentLocalVersion('1.0');
        $this->assertEquals(
            '1.0',
            $this->updater->getStrategy()->getCurrentLocalVersion($this->updater)
        );
    }

    public function test_set_phar_name(): void
    {
        $this->updater->getStrategy()->setPharName('foo.phar');
        $this->assertEquals(
            'foo.phar',
            $this->updater->getStrategy()->getPharName()
        );
    }

    public function test_set_package_name(): void
    {
        $this->updater->getStrategy()->setPackageName('foo/bar');
        $this->assertEquals(
            'foo/bar',
            $this->updater->getStrategy()->getPackageName()
        );
    }

    public function test_set_stability(): void
    {
        $this->assertEquals(
            'stable',
            $this->updater->getStrategy()->getStability()
        );
        $this->updater->getStrategy()->setStability('unstable');
        $this->assertEquals(
            'unstable',
            $this->updater->getStrategy()->getStability()
        );
    }

    public function test_set_stability_throws_exception_on_invalid_stability_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->updater->getStrategy()->setStability('foo');
    }

    #[RunInSeparateProcess]
    public function test_update_phar(): void
    {
        $this->createTestPhar();
        $this->assertEquals('old', $this->getPharOutput($this->tmp.'/old.phar'));

        $updater = new Updater($this->tmp.'/old.phar');
        $updater->setStrategyObject(new GithubTestStrategy);
        $updater->getStrategy()->setPharName('new.phar');
        $updater->getStrategy()->setPackageName('humbug/test-phar');
        $updater->getStrategy()->setCurrentLocalVersion('1.0.0');

        $this->assertTrue($updater->update());
        $this->assertEquals('new', $this->getPharOutput($this->tmp.'/old.phar'));
    }

    public function test_get_signature_url_throws_exception_before_remote_version_is_resolved(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The remote version must be resolved before the signature URL is available.');
        (new GithubStrategy)->getSignatureUrl();
    }

    #[RunInSeparateProcess]
    public function test_update_phar_with_valid_signature(): void
    {
        $this->requireGpgv();
        $this->createTestPhar();
        copy($this->files.'/build/new.phar.asc', $this->tmp.'/releases/download/1.0.1/new.phar.asc');

        $updater = $this->createSignedUpdater();

        $this->assertTrue($updater->update());
        $this->assertSame(
            'file://'.$this->tmp.'/releases/download/1.0.1/new.phar.asc',
            $updater->getStrategy()->getSignatureUrl()
        );
        $this->assertEquals('new', $this->getPharOutput($this->tmp.'/old.phar'));
    }

    #[RunInSeparateProcess]
    public function test_update_phar_fails_if_release_signature_is_invalid(): void
    {
        $this->requireGpgv();
        $this->createTestPhar();
        copy($this->files.'/gpg/new.phar.other.asc', $this->tmp.'/releases/download/1.0.1/new.phar.asc');

        $this->assertSignedUpdateFails(SignatureVerificationException::class);
    }

    #[RunInSeparateProcess]
    public function test_update_phar_fails_if_release_signature_is_missing(): void
    {
        $this->requireGpgv();
        $this->createTestPhar();

        $this->assertSignedUpdateFails(HttpRequestException::class);
    }

    // Helpers

    private function requireGpgv(): void
    {
        exec('command -v gpgv', $output, $exitCode);
        if ($exitCode !== 0) {
            $this->markTestSkipped('This test requires gpgv (GnuPG) to be installed.');
        }
    }

    private function createSignedUpdater(): Updater
    {
        $strategy = new GithubTestStrategy;
        $strategy->setPharName('new.phar');
        $strategy->setPackageName('humbug/test-phar');
        $strategy->setCurrentLocalVersion('1.0.0');

        $updater = new Updater($this->tmp.'/old.phar');
        $updater->setStrategyObject($strategy);
        $updater->setSignatureVerifier(GpgvVerifier::fromFile(
            $this->files.'/gpg/test.pubkey.asc',
            trim((string) file_get_contents($this->files.'/gpg/test.fingerprint'))
        ));

        return $updater;
    }

    /**
     * @param  class-string<\Throwable>  $exception
     */
    private function assertSignedUpdateFails(string $exception): void
    {
        $updater = $this->createSignedUpdater();

        try {
            $updater->update();
            $this->fail('The update should have failed.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf($exception, $e, $e->getMessage());
        }

        $this->assertEquals('old', $this->getPharOutput($this->tmp.'/old.phar'));
        $this->assertFileDoesNotExist($updater->getTempPharFile());
        $this->assertFileDoesNotExist($updater->getTempSignatureFile());
    }

    private function getPharOutput(string $path): string
    {
        return exec('php '.escapeshellarg($path));
    }

    private function deleteTempPhars(): void
    {
        @unlink($this->tmp.'/old.phar');
        @unlink($this->tmp.'/releases/download/1.0.1/new.phar');
        @unlink($this->tmp.'/releases/download/1.0.1/new.phar.asc');
        @unlink($this->tmp.'/packages.json');
    }

    private function createTestPhar(): void
    {
        copy($this->files.'/build/old.phar', $this->tmp.'/old.phar');
        chmod($this->tmp.'/old.phar', 0755);
        @mkdir($this->tmp.'/releases/download/1.0.1', 0755, true);
        copy($this->files.'/build/new.phar', $this->tmp.'/releases/download/1.0.1/new.phar');
        file_put_contents($this->tmp.'/packages.json', json_encode([
            'packages' => [
                'humbug/test-phar' => [
                    [
                        'version' => '2.0.0-beta.1',
                    ],
                    [
                        'version' => '1.0.1',
                        'source' => [
                            'url' => 'file://'.$this->tmp.'.git',
                        ],
                    ],
                    [
                        'version' => '1.0.0',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
    }
}

class GithubTestStrategy extends GithubStrategy
{
    #[\Override]
    protected function getApiUrl(): string
    {
        return 'file://'.sys_get_temp_dir().'/packages.json';
    }
}
