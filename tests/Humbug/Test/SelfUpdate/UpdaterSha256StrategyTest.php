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
use Humbug\SelfUpdate\Strategy\Sha256Strategy;
use Humbug\SelfUpdate\Updater;
use PHPUnit\Framework\TestCase;

class UpdaterSha256StrategyTest extends TestCase
{
    private $files;

    /** @var Updater */
    private $updater;

    private $tmp;

    protected function setup(): void
    {
        $this->tmp = sys_get_temp_dir();
        $this->files = __DIR__.'/_files';
        $this->updater = new Updater($this->files.'/test.phar', true, Updater::STRATEGY_SHA256);
    }

    protected function teardown(): void
    {
        $this->deleteTempPhars();
    }

    public function test_construction(): void
    {
        $updater = new Updater(null, false, Updater::STRATEGY_SHA256);
        $this->assertInstanceOf(Sha256Strategy::class, $updater->getStrategy());
    }

    public function test_get_current_local_version(): void
    {
        $this->assertEquals(
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
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
        $this->updater->getStrategy()->setVersionUrl('file://'.$this->files.'/good.sha256.version');
        $this->assertTrue($this->updater->hasUpdate());
        $this->assertEquals(
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            $this->updater->getOldVersion()
        );
        $this->assertEquals(
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b858', // 5 => 8
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

        $updater = new Updater($this->tmp.'/old.phar', true, Updater::STRATEGY_SHA256);
        $updater->getStrategy()->setPharUrl('file://'.$this->files.'/build/new.phar');
        $updater->getStrategy()->setVersionUrl('file://'.$this->files.'/build/new.sha256.version');
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
