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
use Humbug\SelfUpdate\Strategy\StrategyInterface;
use Humbug\SelfUpdate\Updater;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class UpdaterTest extends TestCase
{
    private string $files;

    private Updater $updater;

    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir();
        $this->files = __DIR__.'/_files';

        $this->updater = new Updater($this->files.'/test.phar');
    }

    protected function tearDown(): void
    {
        $this->deleteTempPhars();
    }

    public function test_construction(): void
    {
        $updater = new Updater($this->files.'/test.phar');
        $this->assertEquals($updater->getLocalPharFile(), $this->files.'/test.phar');

        // no name - detect running console app
        $updater = new Updater;
        $this->assertStringEndsWith(
            'phpunit.phar',
            basename($updater->getLocalPharFile(), '.phar').'.phar'
        );
    }

    public function test_constructor_ancilliary_values(): void
    {
        $this->assertEquals('test', $this->updater->getLocalPharFileBasename());
        $this->assertEquals($this->updater->getTempDirectory(), $this->files);
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
        $this->updater->getStrategy()->setVersionUrl('file://'.$this->files.'/good.version');
        $this->assertTrue($this->updater->hasUpdate());
        $this->assertEquals('da39a3ee5e6b4b0d3255bfef95601890afd80709', $this->updater->getOldVersion());
        $this->assertEquals('1af1b9c94dea1ff337587bfa9109f1dad1ec7b9b', $this->updater->getNewVersion());
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
        $this->createTestPhar();
        $this->assertEquals('old', $this->getPharOutput($this->tmp.'/old.phar'));

        $updater = new Updater($this->tmp.'/old.phar');
        $updater->getStrategy()->setPharUrl('file://'.$this->files.'/build/new.phar');
        $updater->getStrategy()->setVersionUrl('file://'.$this->files.'/build/new.version');
        $this->assertTrue($updater->update());
        $this->assertEquals('new', $this->getPharOutput($this->tmp.'/old.phar'));
    }

    /**
     * @runInSeparateProcess
     */
    public function test_update_phar_fails_if_download_phar_is_tampered(): void
    {
        $this->createTestPhar();

        /** The version hash matches, but the phar's internal signature does not */
        $this->expectException(UnexpectedValueException::class);

        $updater = new Updater($this->tmp.'/old.phar');
        $updater->getStrategy()->setPharUrl('file://'.$this->files.'/build/tampered.phar');
        $updater->getStrategy()->setVersionUrl('file://'.$this->files.'/build/tampered.version');

        try {
            $updater->update();
        } finally {
            $this->assertEquals('old', $this->getPharOutput($this->tmp.'/old.phar'));
            $this->assertFileDoesNotExist($updater->getTempPharFile());
        }
    }

    public function test_set_backup_path_sets_the_path_when_the_directory_exists_and_is_writeable(): void
    {
        $this->createTestPhar();
        $updater = new Updater($this->tmp.'/old.phar');
        $updater->setBackupPath($this->tmp.'/backup.phar');
        $res = $updater->getBackupPath();
        $this->assertEquals($this->tmp.'/backup.phar', $res);
    }

    public function test_set_restore_path_sets_the_path_when_the_directory_exists_and_is_writeable(): void
    {
        $this->createTestPhar();
        $updater = new Updater($this->tmp.'/old.phar');
        $updater->setRestorePath($this->tmp.'/backup.phar');
        $res = $updater->getRestorePath();
        $this->assertEquals($this->tmp.'/backup.phar', $res);
    }

    /**
     * Custom Strategies.
     */
    public function test_can_set_custom_strategy_objects(): void
    {
        $this->updater->setStrategyObject(new FooStrategy);
        $this->assertInstanceOf(FooStrategy::class, $this->updater->getStrategy());
    }

    // Helpers

    private function getPharOutput(string $path): string
    {
        return exec('php '.escapeshellarg($path));
    }

    private function deleteTempPhars(): void
    {
        @unlink($this->tmp.'/old.phar');
    }

    private function createTestPhar(): void
    {
        copy($this->files.'/build/old.phar', $this->tmp.'/old.phar');
        chmod($this->tmp.'/old.phar', 0755);
    }
}

class FooStrategy implements StrategyInterface
{
    #[\Override]
    public function download(Updater $updater): void {}

    #[\Override]
    public function getCurrentRemoteVersion(Updater $updater): string
    {
        return '';
    }

    #[\Override]
    public function getCurrentLocalVersion(Updater $updater): string
    {
        return '';
    }
}
