<?php

/**
 * Humbug.
 *
 * @category   Humbug
 *
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/pharupdater/blob/master/LICENSE New BSD License
 */

namespace Humbug\Test\SelfUpdate;

use Humbug\SelfUpdate\Exception\InvalidArgumentException;
use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;
use PHPUnit\Framework\TestCase;

class UpdaterGithubStrategyTest extends TestCase
{
    private $files;

    /** @var Updater */
    private $updater;

    private $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir();
        $this->files = __DIR__.'/_files';
        $this->updater = new Updater($this->files.'/test.phar', false, Updater::STRATEGY_GITHUB);
    }

    protected function tearDown(): void
    {
        $this->deleteTempPhars();
    }

    public function test_construction(): void
    {
        $updater = new Updater(null, false, Updater::STRATEGY_GITHUB);
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

    /**
     * @runInSeparateProcess
     */
    public function test_update_phar(): void
    {
        if (! extension_loaded('openssl')) {
            $this->markTestSkipped('This test requires the openssl extension to run.');
        }

        $this->createTestPharAndKey();
        $this->assertEquals('old', $this->getPharOutput($this->tmp.'/old.phar'));

        $updater = new Updater($this->tmp.'/old.phar');
        $updater->setStrategyObject(new GithubTestStrategy);
        $updater->getStrategy()->setPharName('new.phar');
        $updater->getStrategy()->setPackageName('humbug/test-phar');
        $updater->getStrategy()->setCurrentLocalVersion('1.0.0');

        $this->assertTrue($updater->update());
        $this->assertEquals('new', $this->getPharOutput($this->tmp.'/old.phar'));
    }

    // Helpers

    private function getPharOutput(string $path): string
    {
        return exec('php '.escapeshellarg($path));
    }

    private function deleteTempPhars(): void
    {
        @unlink($this->tmp.'/old.phar');
        @unlink($this->tmp.'/old.phar.pubkey');
        @unlink($this->tmp.'/releases/download/1.0.1/new.phar');
        @unlink($this->tmp.'/releases/download/1.0.1/new.phar.pubkey');
        @unlink($this->tmp.'/old.1c7049180abee67826d35ce308c38272242b64b8.phar');
        @unlink($this->tmp.'/packages.json');
    }

    private function createTestPharAndKey(): void
    {
        copy($this->files.'/build/old.phar', $this->tmp.'/old.phar');
        chmod($this->tmp.'/old.phar', 0755);
        copy(
            $this->files.'/build/old.phar.pubkey',
            $this->tmp.'/old.phar.pubkey'
        );
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
    protected function getApiUrl(): string
    {
        return 'file://'.sys_get_temp_dir().'/packages.json';
    }
}
