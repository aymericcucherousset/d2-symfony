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
- Core: `Renderer` port (`render(Diagram): string`, returns SVG) and exception hierarchy: `D2Exception` marker interface implemented by `RenderException` (keeps the raw D2 error), `BinaryNotFoundException` and `InvalidArgumentException` (now thrown by `Diagram` and `RenderOptions`).
- Core: `BinaryRenderer`, rendering through the local `d2` binary with `symfony/process` (source on stdin, SVG on stdout, no temporary file), configurable binary path and timeout; failures raise `RenderException` with the raw stderr, a missing or non-executable binary raises `BinaryNotFoundException`.
- Bundle: `d2` configuration (`binary_path`, `timeout`, `defaults.theme|layout|pad|sketch`, validated with the core rules), `BinaryRenderer` registered as `d2.renderer.binary` behind the `d2.renderer` alias and the `Renderer` autowiring alias, default options exposed as the `d2.default_options` service.
- Core: `RenderOptions::merge(array)` to override options from arrays (unknown keys and wrong types rejected, `null` resets to the d2 default).
- Twig: `|d2` filter (`{{ source|d2({ theme: 200, sketch: true }) }}`) rendering inline SVG through a lazily loaded runtime, using `d2.defaults` as base options; registered when Twig is installed.
- README quick start: installation, configuration reference, Twig and PHP usage, errors, roadmap, and a diagram rendered by the bundle.

### Fixed

- `D2Bundle::getPath()` now returns the package root instead of `src/`.

[Unreleased]: https://github.com/aymericcucherousset/d2-symfony/commits/main
