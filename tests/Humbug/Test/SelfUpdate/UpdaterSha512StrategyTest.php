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

use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Exception\InvalidArgumentException;
use Humbug\SelfUpdate\Strategy\Sha512Strategy;
use Humbug\SelfUpdate\Updater;
use PHPUnit\Framework\TestCase;

class UpdaterSha512StrategyTest extends TestCase
{
    private $files;

    /** @var Updater */
    private $updater;

    private $tmp;

    protected function setup(): void
    {
        $this->tmp = sys_get_temp_dir();
        $this->files = __DIR__.'/_files';
        $this->updater = new Updater($this->files.'/test.phar', true, Updater::STRATEGY_SHA512);
    }

    protected function teardown(): void
    {
        $this->deleteTempPhars();
    }

    public function test_construction(): void
    {
        $updater = new Updater(null, false, Updater::STRATEGY_SHA512);
        $this->assertInstanceOf(Sha512Strategy::class, $updater->getStrategy());
    }

    public function test_get_current_local_version(): void
    {
        $this->assertEquals(
            'cf83e1357eefb8bdf1542850d66d8007d620e4050b5715dc83f4a921d36ce9ce47d0d13c5d85f2b0ff8318d2877eec2f63b931bd47417a81a538327af927da3e',
            $this->updater->getStrategy()->getCurrentLocalVersion($this->updater)
        );
    }

    public function test_set_phar_url_with_url(): void
    {
        $this->updater->getStrategy()->setPharUrl('http://www.example.com');
        $this->assertEquals('http://www.example.com', $this->updater->getStrategy()->getPharUrl());

        $this->updater->getStrategy()->setPharUrl('https://www.example.com');
        $this->assertEquals('https://www.example.com', $this->updater->getStrategy()->getPharUrl());
    }

    public function test_set_phar_url_throws_exception_on_invalid_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->updater->getStrategy()->setPharUrl('silly:///home/padraic');
    }

    public function test_set_version_url_with_url(): void
    {
        $this->updater->getStrategy()->setVersionUrl('http://www.example.com');
        $this->assertEquals('http://www.example.com', $this->updater->getStrategy()->getVersionUrl());

        $this->updater->getStrategy()->setVersionUrl('https://www.example.com');
        $this->assertEquals('https://www.example.com', $this->updater->getStrategy()->getVersionUrl());
    }

    public function test_set_version_url_throws_exception_on_invalid_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->updater->getStrategy()->setVersionUrl('silly:///home/padraic');
    }

    public function test_can_detect_new_remote_version_and_store_versions(): void
    {
        $this->updater->getStrategy()->setVersionUrl('file://'.$this->files.'/good.sha512.version');
        $this->assertTrue($this->updater->hasUpdate());
        $this->assertEquals(
            'cf83e1357eefb8bdf1542850d66d8007d620e4050b5715dc83f4a921d36ce9ce47d0d13c5d85f2b0ff8318d2877eec2f63b931bd47417a81a538327af927da3e',
            $this->updater->getOldVersion()
        );
        $this->assertEquals(
            'b8a705a7430e69f23594c4bf58e8110d0e00e2fe0eb88cfdb5980a825b5734dc4c54e4cebba44e0424b1a79303745b8fb662d65eb34b35da51fd3347e6302902',
            $this->updater->getNewVersion()
        );
    }

    public function test_throws_exception_on_empty_remote_version(): void
    {
        $this->expectException(HttpRequestException::class);
        $this->expectExceptionMessage('Version request returned empty response');
        $this->updater->getStrategy()->setVersionUrl('file://'.$this->files.'/empty.version');
        $this->assertTrue($this->updater->hasUpdate());
    }

    public function test_throws_exception_on_invalid_remote_version(): void
    {
        $this->expectException(HttpRequestException::class);
        $this->expectExceptionMessage('Version request returned incorrectly formatted response');
        $this->updater->getStrategy()->setVersionUrl('file://'.$this->files.'/bad.version');
        $this->assertTrue($this->updater->hasUpdate());
    }

    /**
     * @runInSeparateProcess
     */
    public function test_update_phar(): void
    {
        $this->createTestPharAndKey();
        $this->assertEquals('old', $this->getPharOutput($this->tmp.'/old.phar'));

        $updater = new Updater($this->tmp.'/old.phar', true, Updater::STRATEGY_SHA512);
        $updater->getStrategy()->setPharUrl('file://'.$this->files.'/build/new.phar');
        $updater->getStrategy()->setVersionUrl('file://'.$this->files.'/build/new.sha512.version');
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
        @unlink($this->tmp.'/old.phar.temp.pubkey');
        @unlink($this->tmp.'/old-old.phar');
    }

    private function createTestPharAndKey(): void
    {
        copy($this->files.'/build/old.phar', $this->tmp.'/old.phar');
        chmod($this->tmp.'/old.phar', 0755);
        copy(
            $this->files.'/build/old.phar.pubkey',
            $this->tmp.'/old.phar.pubkey'
        );
    }
}
