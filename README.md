# PHAR Updater

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laravel-zero/phar-updater.svg?style=flat-square)](https://packagist.org/packages/laravel-zero/phar-updater)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/github/workflow/status/laravel-zero/phar-updater/Tests.svg?style=flat-square)](https://github.com/laravel-zero/phar-updater/actions)
[![StyleCI](https://styleci.io/repos/308408356/shield?style=flat-square)](https://styleci.io/repos/308408356)
[![Total Downloads](https://img.shields.io/packagist/dt/laravel-zero/phar-updater.svg?style=flat-square)](https://packagist.org/packages/laravel-zero/phar-updater)

> This is a fork of the [Humbug PHAR Updater](https://github.com/humbug/phar-updater) for internal use in Laravel Zero.

The backend for the self-update command in Laravel Zero PHARs. Originally created by Humbug.

**Table of Contents**

- [Introduction](#introduction)
- [Installation](#installation)
- [Usage](#usage)
    - [Basic SHA-1 / SHA-256 / SHA-512 Strategy](#basic-sha-1--sha-256--sha-512-strategy)
    - [Github Release Strategy](#github-release-strategy)
    - [Rollback Support](#rollback-support)
    - [Constructor Parameters](#constructor-parameters)
    - [Check For Updates](#check-for-updates)
    - [Avoid Post Update File Includes](#avoid-post-update-file-includes)
    - [Custom Update Strategies](#custom-update-strategies)
- [Update Strategies](#update-strategies)
    - [SHA-1 / SHA-256 / SHA-512 Hash Synchronisation](#sha-1--sha-256--sha-512-hash-synchronisation)
    - [Github Releases](#github-releases)
    - [Direct Downloads](#direct-downloads)

## Introduction

The `laravel-zero/phar-updater` package has the following features:

* Full support for SSL/TLS verification.
* Support for OpenSSL phar signatures.
* Simple API where it either updates or Exceptions will go wild.
* Support for SHA-1/SHA-256/SHA-512 version synchronisation and Github Releases as update strategies.

Apart from the detailed documentation below, you can find the package being used within
[Laravel Zero's self-update component](https://github.com/laravel-zero/framework/blob/master/src/Components/Updater).

## Installation

Via Composer

```bash
composer require laravel-zero/phar-updater
```

Via the Laravel Zero component installer

```bash
php <application> app:install self-update
```

The package utilises PHP Streams for remote requests, so it will require the openssl extension and the `allow_url_open`
setting to both be enabled. Support for curl will follow in time.

## Usage

The default update strategy uses an SHA-1 hash of the current remote phar in a version file, and will update the local
phar when this version is changed. There is also a Github strategy which tracks Github Releases where you can upload a
new phar file for a release.

### Basic SHA-1 / SHA-256 / SHA-512 Strategy

> NOTE: The SHA-1 strategy is marked as deprecated, you should prefer the SHA-256 or SHA-512 strategies instead.

Create your self-update command, or even an update command for some other phar other than the current one, and include
this.

```php
/**
 * The simplest usage assumes the currently running phar is to be updated and
 * that it has been signed with a private key (using OpenSSL).
 *
 * The first constructor parameter is the path to a phar if you are not updating
 * the currently running phar.
 */

use Humbug\SelfUpdate\Updater;

$updater = new Updater();

// Add the below to use the SHA-256 strategy. It will default to SHA-1 if excluded.
$updater->setStrategy(Updater::STRATEGY_SHA256);

$updater->getStrategy()->setPharUrl('https://example.com/current.phar');
$updater->getStrategy()->setVersionUrl('https://example.com/current.version');
try {
    $result = $updater->update();
    echo $result ? "Updated!\n" : "No update needed!\n";
} catch (\Exception $e) {
    echo "Well, something happened! Either an oopsie or something involving hackers.\n";
    exit(1);
}
```

If you are not signing the phar using OpenSSL:

```php
/**
 * The second parameter to the constructor must be false if your phars are
 * not signed using OpenSSL.
 */

use Humbug\SelfUpdate\Updater;

$updater = new Updater(null, false);
$updater->getStrategy()->setPharUrl('https://example.com/current.phar');
$updater->getStrategy()->setVersionUrl('https://example.com/current.version');
try {
    $result = $updater->update();
    echo $result ? "Updated!\n" : "No update needed!\n";
} catch (\Exception $e) {
    echo "Well, something happened! Either an oopsie or something involving hackers.\n";
    exit(1);
}
```

If you need version information:

```php
use Humbug\SelfUpdate\Updater;

$updater = new Updater();
$updater->getStrategy()->setPharUrl('https://example.com/current.phar');
$updater->getStrategy()->setVersionUrl('https://example.com/current.version');
try {
    $result = $updater->update();
    if ($result) {
        $new = $updater->getNewVersion();
        $old = $updater->getOldVersion();
        printf(
            'Updated from SHA-1 %s to SHA-1 %s', $old, $new
        );
    } else {
        echo "No update needed!\n";
    }
} catch (\Exception $e) {
    echo "Well, something happened! Either an oopsie or something involving hackers.\n";
    exit(1);
}
```

See the [Update Strategies](#update-strategies) section for an overview of how to set up the SHA-1 or SHA-256 strategy.
It's a simple to maintain choice for development or nightly versions of phars which are released to a specific numbered
version.

### Github Release Strategy

Beyond development or nightly phars, if you are releasing numbered versions on Github (i.e. tags), you can upload
additional files (such as phars) to include in the Github Release.

```php
/**
 * Other than somewhat different setters for the strategy, all other operations
 * are identical.
 */

use Humbug\SelfUpdate\Updater;

$updater = new Updater();
$updater->setStrategy(Updater::STRATEGY_GITHUB);
$updater->getStrategy()->setPackageName('myvendor/myapp');
$updater->getStrategy()->setPharName('myapp.phar');
$updater->getStrategy()->setCurrentLocalVersion('v1.0.1');
try {
    $result = $updater->update();
    echo $result ? "Updated!\n" : "No update needed!\n";
} catch (\Exception $e) {
    echo "Well, something happened! Either an oopsie or something involving hackers.\n";
    exit(1);
}
```

Package name refers to the name used by Packagist, and phar name is the phar's file name assumed to be constant across
versions.

It's left to the implementation to supply the current release version associated with the local phar. This needs to be
stored within the phar and should match the version string used by Github. This can follow any standard practice with
recognisable pre- and postfixes, e.g.
`v1.0.3`, `1.0.3`, `1.1`, `1.3rc`, `1.3.2pl2`.

If you wish to update to a non-stable version, for example where users want to update according to a development track,
you can set the stability flag for the Github strategy. By default this is set to `stable` or, in constant form,
`\Humbug\SelfUpdate\Strategy\GithubStrategy::STABLE`:

```php
$updater->getStrategy()->setStability('unstable');
```

If you want to ignore stability and just update to the most recent version regardless:

```php
$updater->getStrategy()->setStability('any');
```

### Rollback Support

The Updater automatically copies a backup of the original phar to myname-old.phar. You can trigger a rollback quite
easily using this convention:

```php
use Humbug\SelfUpdate\Updater;

/**
 * Same constructor parameters as you would use for updating. Here, just defaults.
 */
$updater = new Updater();
try {
    $result = $updater->rollback();
    if (!$result) {
        echo "Failure!\n";
        exit(1);
    }
    echo "Success!\n";
} catch (\Exception $e) {
    echo "Well, something happened! Either an oopsie or something involving hackers.\n";
    exit(1);
}
```

As users may have diverse requirements in naming and locating backups, you can explicitly manage the precise path to
where a backup should be written, or read from using the `setBackupPath()` function when updating a current phar or the
`setRestorePath()` prior to triggering a rollback. These will be used instead of the simple built in convention.

### Constructor Parameters

The Updater constructor is fairly simple. The three basic variations are:

```php
/**
 * Default: Update currently running phar which has been signed.
 */
$updater = new Updater;
```

```php
/**
 * Update currently running phar which has NOT been signed.
 */
$updater = new Updater(null, false);
```

```php
/**
 * Use a strategy other than the default SHA Hash.
 */
$updater = new Updater(null, false, Updater::STRATEGY_GITHUB);
```

```php
/**
 * Update a different phar which has NOT been signed.
 */
$updater = new Updater('/path/to/impersonatephil.phar', false);
```

### Check For Updates

You can tell users what updates are available, across any stability track, by making use of the `hasUpdate` method. This
gets the most recent remote version for a stability level, compares it to the current version, and returns a simple
true/false result, i.e. it will only be false where the local version is identical or where there was no remote version
for that stability level at all. You can easily differentiate between the two false states as the new version will be a
string where a version did exist, but `false` if not.

```php
use Humbug\SelfUpdate\Updater;

/**
 * Configuration is identical in every way for actual updates. You can run this
 * across multiple configuration variants to get recent stable, unstable, and dev
 * versions available.
 *
 * This would configure update for an unsigned phar (second constructor must be
 * false in this case).
 */
$updater = new Updater(null, false);
$updater->setStrategy(Updater::STRATEGY_GITHUB);
$updater->getStrategy()->setPackageName('myvendor/myapp');
$updater->getStrategy()->setPharName('myapp.phar');
$updater->getStrategy()->setCurrentLocalVersion('v1.0.1');

try {
    $result = $updater->hasUpdate();
    if ($result) {
        printf(
            'The current stable build available remotely is: %s',
            $updater->getNewVersion()
        );
    } elseif (false === $updater->getNewVersion()) {
        echo "There are no stable builds available.\n";
    } else {
        echo "You have the current stable build installed.\n";
    }
} catch (\Exception $e) {
    echo "Well, something happened! Either an oopsie or something involving hackers.\n";
    exit(1);
}
```

### Avoid Post Update File Includes

Updating a currently running phar is made trickier since, once replaced, attempts to load files from it within a process
originating from an older phar is likely to create an `internal corruption of phar` error. For example, if you're using
Symfony Console and have created an event dispatcher for your commands, the lazy loading of some event classes will have
this impact.

The solution is to disable or remove the dispatcher for your self-update command.

In general, when writing your self-update CLI commands, either pre-load any classes likely needed prior to updating, or
disable their loading if not essential.

### Custom Update Strategies

All update strategies revolve around checking for updates, and downloading updates. The actual work behind replacing
local files and backups is handled separately. To create a custom strategy, you can
implement `Humbug\SelfUpdate\Strategy\StrategyInterface`
and pass a new instance of your implementation post-construction.

```php
$updater = new Updater(null, false);
$updater->setStrategyObject(new MyStrategy);
```

The similar `setStrategy()` method is solely used to pass flags matching internal strategies.

## Update Strategies

### SHA-1 / SHA-256 / SHA-512 Hash Synchronisation

The phar-updater package supports an update strategy where phars are updated according to the SHA-1, SHA-256, or SHA-512
hash of the current PHAR file available remotely. This assumes the existence of only two to three remote files:

* myname.phar
* myname.version
* myname.phar.pubkey (optional)

The `myname.phar` is the most recently built phar.

The `myname.version` contains the SHA-1, SHA-256, or SHA-512 hash of the most recently built phar where the hash is the very first
string (if not the only string). You can generate this quite easily from bash using:

```bash
# For SHA-1
sha1sum myname.phar > myname.version

# For SHA-256
sha256sum myname.phar > myname.version

# For SHA-512
sha512sum myname.phar > myname.version
```

Remember to regenerate the version file for each new phar build you want to distribute. Using `sha1sum`/`sha256sum`/`sha512sum`
adds additional data after the hash, but it's fine since the hash is the first string in the file which is the only
requirement.

If using OpenSSL signing, which is very much recommended, you can also put the public key online as `myname.phar.pubkey`
, for the initial installation of your phar. However, please note that phar-updater itself will never download this key,
will never replace this key on your filesystem, and will never install a phar whose signature cannot be verified by the
locally cached public key.

If you need to switch keys for any reason whatsoever, users will need to manually download a new phar along with the new
key. While that sounds extreme, it's generally not a good idea to allow for arbitrary key changes that occur without
user knowledge. The openssl signing has no mechanism such as a central authority, or a browser's trusted certificate
stash with which to automate such key changes in a safe manner.

### Github Releases

When tagging new versions on Github, these are created and hosted as `Github Releases`
which allow you to attach a changelog and additional file downloads. Using this Github feature allows you to attach new
phars to releases, associating them with a version string that is published on Packagist.

Taking advantage of this architecture, the Github Strategy for updating phars can compare the existing local phar's
version against remote release versions and update to the most recent stable (or unstable) version from Github.

At present, it's assume that phar files all bear the same name across releases, i.e. just a plain name like `myapp.phar`
without versioning information in the file name. You can also upload your phar's public key in the same way. Using the
established convention of being the phar name with `.pubkey` appended, e.g.
`myapp.phar` would be matched with `myapp.phar.pubkey`.

You can read more about Github releases [here](https://help.github.com/articles/creating-releases).

While you can draft a release, Github releases are created automatically whenever you create a new git tag. If you use
git tagging, you can go to the matching release on Github, click the `Edit` button and attach files. It's recommended to
do this as soon as possible after tagging to limit the window whereby a new release exists without an updated phar
attached.

### Direct Downloads

PHAR Updater provides an abstract [`Humbug\SelfUpdate\Strategy\DirectDownloadStrategyAbstract` class](src/Strategy/DirectDownloadStrategyAbstract.php)
which can be used to quickly and easily create download strategies with just a `getDownloadUrl(): string` method.

For example, if a PHAR downloads it's latest updates from `https://example.com/latest/example.phar`, you can utilise this
with the following code:

```php
use Humbug\SelfUpdate\Strategy\DirectDownloadStrategyAbstract;

class ExampleDirectDownloadStrategy extends DirectDownloadStrategyAbstract
{
    public function getDownloadUrl(): string
    {
        return 'https://example.com/latest/example.phar';
    }
}
```

The abstract strategy also supports overriding the `getCurrentRemoteVersion()` method, so that you could add a custom
HTTP call or other method for seeing what the latest version is. By default, it returns the string `latest`.

```php
use Illuminate\Support\Facades\Http;
use Humbug\SelfUpdate\Strategy\DirectDownloadStrategyAbstract;

class ExampleDirectDownloadStrategy extends DirectDownloadStrategyAbstract
{
    /** {@inheritdoc} */
    public function getCurrentRemoteVersion(Updater $updater)
    {
        return Http::get('https://example.com/example-releases.json')->object()->latest_version;
    }

    public function getDownloadUrl(): string
    {
        return "https://example.com/{$this->getCurrentRemoteVersion()}/example.phar";
    }
}
```

You can also set and retrieve the current local version using the `setCurrentLocalVersion()` and `getCurrentLocalVersion()`
methods, which will be used for comparison with the remote version.
