# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com), and this project adheres to [Semantic Versioning](https://semver.org).

## Unreleased

## [v1.3.0 - 2022-02-04](https://github.com/laravel-zero/phar-updater/compare/v1.2.0...v1.3.0)

### Added
- Add a new abstract Direct Download class ([#8](https://github.com/laravel-zero/phar-updater/pull/8))

## [v1.2.0 - 2022-02-04](https://github.com/laravel-zero/phar-updater/compare/v1.1.1...v1.2.0)

### Added
- Add support for PHP 8.1 ([#7](https://github.com/laravel-zero/phar-updater/pull/7))

### Removed
- Drop support for PHP `<8.0` ([#7](https://github.com/laravel-zero/phar-updater/pull/7))

## [v1.1.1 - 2021-08-03](https://github.com/laravel-zero/phar-updater/compare/v1.1.0...v1.1.1)

### Fixed
- Apply packagist changes to GithubStrategy ([#5](https://github.com/laravel-zero/phar-updater/pull/5))

## [v1.1.0 - 2021-06-29](https://github.com/laravel-zero/phar-updater/compare/v1.0.6...v1.1.0)

### Added
- Add support for PHP 8 ([#1](https://github.com/laravel-zero/phar-updater/pull/1))
- Add PHPStan for static analysis ([#3](https://github.com/laravel-zero/phar-updater/pull/3))
- Add new SHA-256 strategy ([7632ea0](https://github.com/laravel-zero/phar-updater/commit/7632ea05325049700463743bffdadb29d072bb94))
- Add new SHA-512 strategy ([#4](https://github.com/laravel-zero/phar-updater/pull/4))

### Changed
- Update to use PHPUnit 9.4 ([fe5cfcc](https://github.com/laravel-zero/phar-updater/commit/fe5cfccb47b91920fc7cecb327c77e28650f3815))
- Update to use Packagist Composer 2.x API for `GitHubStrategy` ([162c9af](https://github.com/laravel-zero/phar-updater/commit/162c9af6cf53fabb4985c6e402e00fda3ed51654))

### Removed
- Drop support for PHP `<7.3` ([646c693](https://github.com/laravel-zero/phar-updater/commit/646c693f4fc03a2e1ec65eaf399a6eb014519397))
- Remove `humbug_get_contents` ([7737a2f](https://github.com/laravel-zero/phar-updater/commit/7737a2f6c2e2414252e89f0163be843f23615f28))

## v1.0.6 - 2020-10-29

### Added
- Initial version (the same as [`padraic/phar-updater:v1.0.6`](https://github.com/humbug/phar-updater))
