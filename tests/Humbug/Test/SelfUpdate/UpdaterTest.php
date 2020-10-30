<?php
/**
 * Humbug.
 *
 * @category   Humbug
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/pharupdater/blob/master/LICENSE New BSD License
 */

namespace Humbug\Test\SelfUpdate;

use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Exception\InvalidArgumentException;
use Humbug\SelfUpdate\Exception\RuntimeException;
use Humbug\SelfUpdate\Strategy\StrategyInterface;
use Humbug\SelfUpdate\Updater;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class UpdaterTest extends TestCase
{
    private $files;

    /** @var Updater */
    private $updater;

    private $tmp;

    public function setUp(): void
    {
        $this->tmp = sys_get_temp_dir();
        $this->files = __DIR__.'/_files';

        $this->updater = new Updater($this->files.'/test.phar');
    }

    public function tearDown(): void
    {
        $this->deleteTempPhars();
    }

    public function testConstruction(): void
    {
        // with key
        $updater = new Updater($this->files.'/test.phar');
        $this->assertEquals($updater->getLocalPharFile(), $this->files.'/test.phar');
        $this->assertEquals($updater->getLocalPubKeyFile(), $this->files.'/test.phar.pubkey');

        // without key
        $updater = new Updater($this->files.'/test.phar', false);
        $this->assertEquals($updater->getLocalPharFile(), $this->files.'/test.phar');
        $this->assertNull($updater->getLocalPubKeyFile());

        // no name - detect running console app
        $updater = new Updater(null, false);
        $this->assertStringEndsWith(
            'phpunit.phar',
            basename($updater->getLocalPharFile(), '.phar').'.phar'
        );
    }

    public function testConstructorThrowsExceptionIfPubKeyNotExistsButFlagTrue(): void
    {
        $this->expectException(RuntimeException::class);
        new Updater($this->files.'/test-nopubkey.phar');
    }

    public function testConstructorAncilliaryValues(): void
    {
        $this->assertEquals('test', $this->updater->getLocalPharFileBasename());
        $this->assertEquals($this->updater->getTempDirectory(), $this->files);
    }

    public function testSetPharUrlWithUrl(): void
    {
        $this->updater->getStrategy()->setPharUrl('http://www.example.com');
        $this->assertEquals('http://www.example.com', $this->updater->getStrategy()->getPharUrl());

        $this->updater->getStrategy()->setPharUrl('https://www.example.com');
        $this->assertEquals('https://www.example.com', $this->updater->getStrategy()->getPharUrl());
    }

    public function testSetPharUrlThrowsExceptionOnInvalidUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->updater->getStrategy()->setPharUrl('silly:///home/padraic');
    }

    public function testSetVersionUrlWithUrl(): void
    {
        $this->updater->getStrategy()->setVersionUrl('http://www.example.com');
        $this->assertEquals('http://www.example.com', $this->updater->getStrategy()->getVersionUrl());

        $this->updater->getStrategy()->setVersionUrl('https://www.example.com');
        $this->assertEquals('https://www.example.com', $this->updater->getStrategy()->getVersionUrl());
    }

    public function testSetVersionUrlThrowsExceptionOnInvalidUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->updater->getStrategy()->setVersionUrl('silly:///home/padraic');
    }

    public function testCanDetectNewRemoteVersionAndStoreVersions(): void
    {
        $this->updater->getStrategy()->setVersionUrl('file://'.$this->files.'/good.version');
        $this->assertTrue($this->updater->hasUpdate());
        $this->assertEquals('da39a3ee5e6b4b0d3255bfef95601890afd80709', $this->updater->getOldVersion());
        $this->assertEquals('1af1b9c94dea1ff337587bfa9109f1dad1ec7b9b', $this->updater->getNewVersion());
    }

    public function testThrowsExceptionOnEmptyRemoteVersion(): void
    {
        $this->expectException(HttpRequestException::class);
        $this->expectExceptionMessage('Version request returned empty response');
        $this->updater->getStrategy()->setVersionUrl('file://'.$this->files.'/empty.version');
        $this->assertTrue($this->updater->hasUpdate());
    }

    public function testThrowsExceptionOnInvalidRemoteVersion(): void
    {
        $this->expectException(HttpRequestException::class);
        $this->expectExceptionMessage('Version request returned incorrectly formatted response');
        $this->updater->getStrategy()->setVersionUrl('file://'.$this->files.'/bad.version');
        $this->assertTrue($this->updater->hasUpdate());
    }

    /**
     * @runInSeparateProcess
     */
    public function testUpdatePhar(): void
    {
        if (! extension_loaded('openssl')) {
            $this->markTestSkipped('This test requires the openssl extension to run.');
        }

        $this->createTestPharAndKey();
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
    public function testUpdatePharFailsIfCurrentPublicKeyEmpty(): void
    {
        //$this->markTestSkipped('Segmentation fault at present under PHP');
        copy($this->files.'/build/badkey.phar', $this->tmp.'/old.phar');
        chmod($this->tmp.'/old.phar', 0755);
        copy($this->files.'/build/badkey.phar.pubkey', $this->tmp.'/old.phar.pubkey');

        $updater = new Updater($this->tmp.'/old.phar');
        $updater->getStrategy()->setPharUrl('file://'.$this->files.'/build/new.phar');
        $updater->getStrategy()->setVersionUrl('file://'.$this->files.'/build/new.version');

        $this->expectException(UnexpectedValueException::class);
        $updater->update();
    }

    /**
     * @runInSeparateProcess
     */
    public function testUpdatePharFailsIfCurrentPublicKeyInvalid(): void
    {
        $this->markTestIncomplete('Segmentation fault at present under PHP');
        /** Should be similar to testUpdatePharFailsIfCurrentPublicKeyEmpty with
         * corrupt or truncated public key */
    }

    /**
     * @runInSeparateProcess
     */
    public function testUpdatePharFailsOnExpectedSignatureMismatch(): void
    {
        if (! extension_loaded('openssl')) {
            $this->markTestSkipped('This test requires the openssl extension to run.');
        }

        $this->createTestPharAndKey();
        $this->assertEquals('old', $this->getPharOutput($this->tmp.'/old.phar'));

        /** Signature check should fail with invalid signature by a different privkey */
        $this->expectException(UnexpectedValueException::class);

        $updater = new Updater($this->tmp.'/old.phar');
        $updater->getStrategy()->setPharUrl('file://'.$this->files.'/build/badsig.phar');
        $updater->getStrategy()->setVersionUrl('file://'.$this->files.'/build/badsig.version');
        $updater->update();
    }

    /**
     * @runInSeparateProcess
     */
    public function testUpdatePharFailsIfDownloadPharIsUnsignedWhenExpected(): void
    {
        $this->createTestPharAndKey();
        $updater = new Updater($this->tmp.'/old.phar');
        $updater->getStrategy()->setPharUrl('file://'.$this->files.'/build/nosig.phar');
        $updater->getStrategy()->setVersionUrl('file://'.$this->files.'/build/nosig.version');

        /** If newly download phar lacks an expected signature, an exception should be thrown */
        $this->expectException(RuntimeException::class);
        $updater->update();
    }

    public function testSetBackupPathSetsThePathWhenTheDirectoryExistsAndIsWriteable(): void
    {
        $this->createTestPharAndKey();
        $updater = new Updater($this->tmp.'/old.phar');
        $updater->setBackupPath($this->tmp.'/backup.phar');
        $res = $updater->getBackupPath();
        $this->assertEquals($this->tmp.'/backup.phar', $res);
    }

    public function testSetRestorePathSetsThePathWhenTheDirectoryExistsAndIsWriteable(): void
    {
        $this->createTestPharAndKey();
        $updater = new Updater($this->tmp.'/old.phar');
        $updater->setRestorePath($this->tmp.'/backup.phar');
        $res = $updater->getRestorePath();
        $this->assertEquals($this->tmp.'/backup.phar', $res);
    }

    /**
     * Custom Strategies.
     */
    public function testCanSetCustomStrategyObjects(): void
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
        @unlink($this->tmp.'/old.phar.pubkey');
        @unlink($this->tmp.'/old.1c7049180abee67826d35ce308c38272242b64b8.phar');
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

class FooStrategy implements StrategyInterface
{
    public function download(Updater $updater)
    {
    }

    public function getCurrentRemoteVersion(Updater $updater)
    {
    }

    public function getCurrentLocalVersion(Updater $updater)
    {
    }
}
