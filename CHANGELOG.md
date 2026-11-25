# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial package structure: `composer.json` (`symfony-bundle`, PHP >= 8.2, Symfony 6.4 / 7.4 / 8.x), `src/Core`, `src/Bundle`, `tests/`.
- MIT license, `.gitattributes`, `.editorconfig`, `.gitignore`.
- GitHub Actions CI: PHPUnit (PHP 8.2 lowest/highest deps, PHP 8.5), PHPStan at max level (with `phpstan-symfony` and `phpstan-phpunit`), PHP-CS-Fixer (`@Symfony`, `@Symfony:risky`), d2 binary installed for integration tests.
- README with CI badges.
- Core: immutable `Diagram` (non-empty D2 source + options) and `RenderOptions` (`theme`, `layout`, `pad`, `sketch`) value objects, with `toCliArguments()` and `fingerprint()` for cache keys.

### Fixed

- `D2Bundle::getPath()` now returns the package root instead of `src/`.

[Unreleased]: https://github.com/aymericcucherousset/d2-symfony/commits/main
