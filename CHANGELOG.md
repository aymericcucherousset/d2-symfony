# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Core: `CachedRenderer`, a decorator caching rendered SVG in any PSR-6 pool; the key covers the source, the options and the engine version, and failed renders are never cached.
- Core: `VersionAwareRenderer` interface; `BinaryRenderer::getVersion()` runs `d2 --version` once per instance.
- Bundle: `d2.cache.enabled` (default `true`) and `d2.cache.pool` (default `cache.d2`, created automatically with FrameworkBundle); `d2.renderer` now points to the cached renderer.
- Twig: `{% d2 %}…{% endd2 %}` tag with optional options (`{% d2 with { theme: 200 } %}`); Twig variables and tags are supported in the body and not HTML-escaped.
- Core: readable D2 errors: `RenderException::getD2Line()`, `getD2Column()` and `getD2Errors()` parse the location reported by d2, and the message reads `D2 syntax error at line 2, column 4: …`.
- Twig: errors in a `{% d2 %}` tag point to the template line of the faulty D2 line.

### Changed

- Twig 3.21 or later is required for the Twig integration (`conflict` with `twig/twig <3.21`).

## [0.1.0] - 2026-11-25

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

### Changed

- The bundle class is now `Aymericcucherousset\D2Symfony\D2SymfonyBundle` at the package root, so Symfony Flex registers it automatically.

### Fixed

- `D2Bundle::getPath()` now returns the package root instead of `src/`.

[Unreleased]: https://github.com/aymericcucherousset/d2-symfony/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/aymericcucherousset/d2-symfony/releases/tag/v0.1.0
