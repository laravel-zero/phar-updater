<?php

declare(strict_types=1);

/**
 * Humbug.
 *
 * @category   Humbug
 *
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/phar-updater/blob/master/LICENSE New BSD License
 *
 * This class is partially patterned after Composer's self-update.
 */

namespace Humbug\SelfUpdate;

use Humbug\SelfUpdate\Exception\FilesystemException;
use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Exception\InvalidArgumentException;
use Humbug\SelfUpdate\Exception\RuntimeException;
use Humbug\SelfUpdate\Exception\SignatureVerificationException;
use Humbug\SelfUpdate\Signature\SignatureVerifierInterface;
use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Strategy\Sha256Strategy;
use Humbug\SelfUpdate\Strategy\Sha512Strategy;
use Humbug\SelfUpdate\Strategy\ShaStrategyAbstract;
use Humbug\SelfUpdate\Strategy\SignedStrategyInterface;
use Humbug\SelfUpdate\Strategy\StrategyInterface;

class Updater
{
    const string STRATEGY_SHA256 = 'sha256';

    const string STRATEGY_SHA512 = 'sha512';

    const string STRATEGY_GITHUB = 'github';

    protected StrategyInterface $strategy;

    protected string $localPharFile;

    protected string $localPharFileBasename;

    protected string $tempDirectory;

    protected string|false|null $newVersion = null;

    protected ?string $oldVersion = null;

    protected string $backupExtension = '-old.phar';

    protected ?string $backupPath = null;

    protected ?string $restorePath = null;

    protected ?bool $newVersionAvailable = null;

    protected ?SignatureVerifierInterface $signatureVerifier = null;

    public function __construct(?string $localPharFile = null, string $strategy = self::STRATEGY_SHA512)
    {
        ini_set('phar.require_hash', '1');
        $this->setLocalPharFile($localPharFile);
        $this->setTempDirectory();
        $this->setStrategy($strategy);
    }

    /**
     * Check for update.
     */
    public function hasUpdate(): bool
    {
        $this->newVersionAvailable = $this->newVersionAvailable();

        return $this->newVersionAvailable;
    }

    /**
     * Perform an update.
     */
    public function update(): bool
    {
        if ($this->newVersionAvailable === false
        || (! is_bool($this->newVersionAvailable) && ! $this->hasUpdate())) {
            return false;
        }
        $this->backupPhar();
        $this->downloadPhar();
        $this->replacePhar();

        return true;
    }

    /**
     * Perform an rollback to previous version.
     */
    public function rollback(): bool
    {
        if (! $this->restorePhar()) {
            return false;
        }

        return true;
    }

    public function setStrategy(string $strategy): void
    {
        $this->strategy = match ($strategy) {
            self::STRATEGY_GITHUB => new GithubStrategy,
            self::STRATEGY_SHA256 => new Sha256Strategy,
            self::STRATEGY_SHA512 => new Sha512Strategy,
            default => throw new InvalidArgumentException(sprintf(
                'Unknown update strategy: %s.',
                $strategy
            )),
        };
    }

    public function setStrategyObject(StrategyInterface $strategy): void
    {
        $this->strategy = $strategy;
    }

    public function getStrategy(): StrategyInterface
    {
        return $this->strategy;
    }

    /**
     * Set the verifier used to check the downloaded phar's detached signature.
     * When set, the strategy must implement SignedStrategyInterface, and updates
     * fail unless the signature is valid.
     */
    public function setSignatureVerifier(?SignatureVerifierInterface $verifier): void
    {
        $this->signatureVerifier = $verifier;
    }

    public function getSignatureVerifier(): ?SignatureVerifierInterface
    {
        return $this->signatureVerifier;
    }

    /**
     * Set backup extension for old phar versions.
     */
    public function setBackupExtension(string $extension): void
    {
        $this->backupExtension = $extension;
    }

    /**
     * Get backup extension for old phar versions.
     */
    public function getBackupExtension(): string
    {
        return $this->backupExtension;
    }

    public function getLocalPharFile(): string
    {
        return $this->localPharFile;
    }

    public function getLocalPharFileBasename(): string
    {
        return $this->localPharFileBasename;
    }

    public function getTempDirectory(): string
    {
        return $this->tempDirectory;
    }

    public function getTempPharFile(): string
    {
        return $this->getTempDirectory()
            .'/'
            .sprintf('%s.phar.temp', $this->getLocalPharFileBasename());
    }

    public function getTempSignatureFile(): string
    {
        return $this->getTempPharFile().'.asc';
    }

    /**
     * Get the new version, which is false if no remote version was available,
     * or null if no update check has been performed.
     */
    public function getNewVersion(): string|false|null
    {
        return $this->newVersion;
    }

    public function getOldVersion(): ?string
    {
        return $this->oldVersion;
    }

    /**
     * Set backup path for old phar versions.
     */
    public function setBackupPath(string $filePath): void
    {
        $path = realpath(dirname($filePath));
        if ($path === false || ! is_dir($path)) {
            throw new FilesystemException(sprintf(
                'The backup directory does not exist: %s.',
                dirname($filePath)
            ));
        }
        if (! is_writable($path)) {
            throw new FilesystemException(sprintf(
                'The backup directory is not writeable: %s.',
                $path
            ));
        }
        $this->backupPath = $filePath;
    }

    /**
     * Get backup path for old phar versions.
     */
    public function getBackupPath(): ?string
    {
        return $this->backupPath;
    }

    /**
     * Set path for the backup phar to rollback/restore from.
     */
    public function setRestorePath(string $filePath): void
    {
        $path = realpath(dirname($filePath));
        if ($path === false || ! file_exists($path)) {
            throw new FilesystemException(sprintf(
                'The restore phar does not exist: %s.',
                dirname($filePath)
            ));
        }
        if (! is_readable($path)) {
            throw new FilesystemException(sprintf(
                'The restore file is not readable: %s.',
                $path
            ));
        }
        $this->restorePath = $filePath;
    }

    /**
     * Get path for the backup phar to rollback/restore from.
     */
    public function getRestorePath(): ?string
    {
        return $this->restorePath;
    }

    public function throwRuntimeException(int $errno, string $errstr): bool
    {
        if ($errno === E_USER_DEPRECATED) {
            return false;
        }

        throw new RuntimeException($errstr);
    }

    public function throwHttpRequestException(int $errno, string $errstr): bool
    {
        if ($errno === E_USER_DEPRECATED) {
            return false;
        }

        throw new HttpRequestException($errstr);
    }

    protected function newVersionAvailable(): bool
    {
        $this->newVersion = $this->strategy->getCurrentRemoteVersion($this);
        $this->oldVersion = $this->strategy->getCurrentLocalVersion($this);

        if (! empty($this->newVersion) && ($this->newVersion !== $this->oldVersion)) {
            return true;
        }

        return false;
    }

    protected function backupPhar(): void
    {
        $result = copy($this->getLocalPharFile(), $this->getBackupPharFile());
        if ($result === false) {
            $this->cleanupAfterError();
            throw new FilesystemException(sprintf(
                'Unable to backup %s to %s.',
                $this->getLocalPharFile(),
                $this->getBackupPharFile()
            ));
        }
    }

    protected function downloadPhar(): void
    {
        $this->strategy->download($this);

        if (! file_exists($this->getTempPharFile())) {
            throw new FilesystemException(
                'Creation of download file failed.'
            );
        }

        $strategy = $this->getStrategy();

        if ($strategy instanceof ShaStrategyAbstract) {
            if ($strategy instanceof Sha512Strategy) {
                $tmpVersion = hash_file('sha512', $this->getTempPharFile());
                $algo = 'SHA-512';
            } else {
                $tmpVersion = hash_file('sha256', $this->getTempPharFile());
                $algo = 'SHA-256';
            }

            if ($tmpVersion !== $this->getNewVersion()) {
                $this->cleanupAfterError();
                throw new HttpRequestException(sprintf(
                    'Download file appears to be corrupted or outdated. The file '
                        .'received does not have the expected %s hash: %s.',
                    $algo,
                    $this->getNewVersion()
                ));
            }
        }

        try {
            if ($this->signatureVerifier !== null) {
                $this->verifySignature($this->signatureVerifier);
            }
            $this->validatePhar($this->getTempPharFile());
        } catch (\Exception $e) {
            $this->cleanupAfterError();
            throw $e;
        }
    }

    protected function verifySignature(SignatureVerifierInterface $verifier): void
    {
        $strategy = $this->getStrategy();
        if (! $strategy instanceof SignedStrategyInterface) {
            throw new SignatureVerificationException(sprintf(
                'A signature verifier is set, but the %s strategy does not implement %s.',
                $strategy::class,
                SignedStrategyInterface::class
            ));
        }

        $signatureUrl = $strategy->getSignatureUrl();
        /** Switch remote request errors to HttpRequestExceptions */
        set_error_handler([$this, 'throwHttpRequestException']);
        try {
            $signature = file_get_contents($signatureUrl);
        } finally {
            restore_error_handler();
        }
        if ($signature === false) {
            throw new HttpRequestException(sprintf(
                'Request to URL failed: %s',
                $signatureUrl
            ));
        }

        file_put_contents($this->getTempSignatureFile(), $signature);
        try {
            $verifier->verify($this->getTempPharFile(), $this->getTempSignatureFile());
        } finally {
            @unlink($this->getTempSignatureFile());
        }
    }

    protected function replacePhar(): void
    {
        rename($this->getTempPharFile(), $this->getLocalPharFile());
    }

    protected function restorePhar(): bool
    {
        $backup = $this->getRestorePharFile();
        if (! file_exists($backup)) {
            throw new RuntimeException(sprintf(
                'The backup file does not exist: %s.',
                $backup
            ));
        }
        $this->validatePhar($backup);

        return rename($backup, $this->getLocalPharFile());
    }

    protected function getBackupPharFile(): string
    {
        if ($this->getBackupPath() !== null) {
            return $this->getBackupPath();
        }

        return $this->getTempDirectory()
            .'/'
            .sprintf('%s%s', $this->getLocalPharFileBasename(), $this->getBackupExtension());
    }

    protected function getRestorePharFile(): string
    {
        if ($this->getRestorePath() !== null) {
            return $this->getRestorePath();
        }

        return $this->getTempDirectory()
            .'/'
            .sprintf(
                '%s%s',
                $this->getLocalPharFileBasename(),
                $this->getBackupExtension()
            );
    }

    protected function setLocalPharFile(?string $localPharFile): void
    {
        if ($localPharFile !== null) {
            $localPharFile = realpath($localPharFile) ?: $localPharFile;
        } else {
            $localPharFile = realpath($_SERVER['argv'][0]) ?: $_SERVER['argv'][0];
        }
        if (! file_exists($localPharFile)) {
            throw new RuntimeException(sprintf(
                'The set phar file does not exist: %s.',
                $localPharFile
            ));
        }
        if (! is_writable($localPharFile)) {
            throw new FilesystemException(sprintf(
                'The current phar file is not writeable and cannot be replaced: %s.',
                $localPharFile
            ));
        }
        $this->localPharFile = $localPharFile;
        $this->localPharFileBasename = basename($localPharFile, '.phar');
    }

    protected function setTempDirectory(): void
    {
        $tempDirectory = dirname($this->getLocalPharFile());
        if (! is_writable($tempDirectory)) {
            throw new FilesystemException(sprintf(
                'The directory is not writeable: %s.',
                $tempDirectory
            ));
        }
        $this->tempDirectory = $tempDirectory;
    }

    protected function validatePhar(string $phar): void
    {
        $phar = realpath($phar) ?: $phar;
        $permissions = fileperms($this->getLocalPharFile());
        if ($permissions !== false) {
            chmod($phar, $permissions);
        }
        /** Switch invalid phar errors to RuntimeExceptions */
        set_error_handler([$this, 'throwRuntimeException']);
        try {
            /** Opening the phar verifies its internal hash, as phar.require_hash is enabled */
            $phar = new \Phar($phar);
        } finally {
            restore_error_handler();
        }
        unset($phar);
    }

    protected function cleanupAfterError(): void
    {
        // @unlink($this->getBackupPharFile());
        @unlink($this->getTempPharFile());
        @unlink($this->getTempSignatureFile());
    }
}
