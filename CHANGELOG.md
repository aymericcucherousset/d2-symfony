# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.5.0] - 2026-11-28

### Added

- Bundle: Web Profiler panel (`kernel.debug` and profiler enabled only): previews of the rendered SVG with their collapsible D2 source, render count and duration, cache hits and misses, renderer used and chain fallbacks, plus a toolbar item and Stopwatch events in the `d2` category ([#21](https://github.com/aymericcucherousset/d2-symfony/issues/21)).
- `bin/console d2:render <input> [-o output.svg]` renders a D2 file (or stdin with `-`) to SVG with the configured renderer and cache; the SVG is written to stdout by default. `--theme`, `--dark-theme`, `--layout`, `--pad`, `--scale`, `--sketch`/`--no-sketch` and `--center`/`--no-center` override `d2.defaults`, and D2 syntax errors are reported as `file:line:column: message` ([#22](https://github.com/aymericcucherousset/d2-symfony/issues/22)).
- Core: `Layout` enum (`Dagre`, `Elk`, `Tala`) and `Theme` enum covering every official d2 theme (`isDark()`); `RenderOptions` accepts them as well as raw IDs and names ([#23](https://github.com/aymericcucherousset/d2-symfony/issues/23)).
- Core: `RenderOptions` supports `darkTheme` (`--dark-theme`), `center`, `scale` and the `fontRegular`, `fontItalic`, `fontBold` and `fontSemibold` .ttf files, with `withDarkTheme()`, `withCenter()`, `withScale()` and `withFonts()`; option names are `dark_theme`, `center`, `scale` and `font_*` in Twig and `merge()`. Kroki receives `dark_theme` and `scale`.
- Bundle: `d2.defaults.dark_theme`, `center`, `scale`, `font_regular`, `font_italic`, `font_bold` and `font_semibold`; theme IDs and layout engines are validated when the container is built, and PHP configuration accepts the enums.

### Changed

- `RenderOptions::$theme`, `$darkTheme` and `$layout` are now `Theme` and `Layout` enums, and only official theme IDs and the `dagre`, `elk` and `tala` layout engines are accepted (any lowercase name was accepted before). `toArray()` returns their IDs and names, so cache keys of existing diagrams are unchanged.

## [0.4.0] - 2026-11-28

### Added

- Core: `KrokiRenderer` renders diagrams over HTTP with a Kroki server (`POST /d2/svg`, configurable base URL and timeout). The `theme`, `layout`, `pad` and `sketch` options are sent as `Kroki-Diagram-Options-*` headers, and unsupported options are logged as warnings through an optional PSR-3 logger. Network errors and server errors (HTTP 5xx) throw a `RemoteUnavailableException`; D2 errors throw a `RenderException`.
- Core: `RenderOptions::toArray()` returns the options that differ from the d2 defaults.
- Core: `ChainRenderer` tries renderers in order and moves to the next one only when a renderer is unavailable; when none is available it throws `NoRendererAvailableException` (`getFailures()`).
- Core: `RendererUnavailableException` interface, implemented by `BinaryNotFoundException`, `RemoteUnavailableException` and `NoRendererAvailableException`.
- Bundle: `d2.renderer` (`binary`, `kroki` or `chain`), `d2.chain` (default `[binary, kroki]`), `d2.kroki.base_url` and `d2.kroki.http_client`. The selected renderer is available as `d2.renderer.inner`, and the Kroki renderer logs on the `d2` channel.

### Changed

- Deptrac (`deptrac.yaml`, `composer deptrac`, dedicated CI job) enforces that `src/Core` depends only on `symfony/process`, `symfony/http-client-contracts`, `psr/cache` and `psr/log`, and never on the bundle code.
- `psr/log` and `symfony/http-client-contracts` are now required.

## [0.3.0] - 2026-11-27

### Added

- `bin/console d2:download` (`--force`) downloads the d2 binary for the current platform (Linux, macOS, Windows × amd64, arm64) from the terrastruct/d2 GitHub releases, verifies its SHA-256 checksum against the published `SHA256SUMS` and installs it in `var/d2/<version>/`.
- `Downloader\Platform` (OS and architecture detection, release asset names) and `Downloader\BinaryDownloader`; `UnsupportedPlatformException`, `DownloadException` and `ChecksumMismatchException` implement `D2Exception`.
- Bundle: `d2.binary_version` (pinned to `v0.9.0`, the version tested in CI) and `d2.install_dir` (default `%kernel.project_dir%/var/d2`).
- Core: `BinaryRenderer` accepts a list of candidate binaries tried in order, and a hint shown when none is found; `BinaryNotFoundException::getCandidates()`.

### Changed

- `d2.binary_path` now defaults to `null`: the binary installed by `d2:download` is used, then `d2` from the `PATH` (as before). An explicit `binary_path` takes precedence.
- The "binary not found" error suggests running `bin/console d2:download`.
- `symfony/http-client` and `symfony/console` are now required.

## [0.2.0] - 2026-11-26

### Added

- Core: `CachedRenderer`, a decorator caching rendered SVG in any PSR-6 pool; the key covers the source, the options and the engine version, and failed renders are never cached.
- Core: `VersionAwareRenderer` interface; `BinaryRenderer::getVersion()` runs `d2 --version` once per instance.
- Core: readable D2 errors: `RenderException::getD2Line()`, `getD2Column()` and `getD2Errors()` parse the location reported by d2, and the message reads `D2 syntax error at line 2, column 4: …`.
- Bundle: `d2.cache.enabled` (default `true`) and `d2.cache.pool` (default `cache.d2`, created automatically with FrameworkBundle); `d2.renderer` now points to the cached renderer.
- Twig: `{% d2 %}…{% endd2 %}` tag with optional options (`{% d2 with { theme: 200 } %}`); Twig variables and tags are supported in the body and not HTML-escaped.
- Twig: errors in a `{% d2 %}` tag point to the template line of the faulty D2 line.
- Twig: `{% d2 %}` tags with a constant body and options are pre-rendered when templates are compiled (and so during `cache:warmup`); configurable with `d2.twig.prerender_static` (default: enabled when `kernel.debug` is false).

### Changed

- Rendered SVG is now cached by default; set `d2.cache: false` to render on every call as in 0.1.
- `RenderException` messages for located D2 errors now read `D2 syntax error at line L, column C: …`; the raw d2 output is still available through `getD2Error()`.
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

[Unreleased]: https://github.com/aymericcucherousset/d2-symfony/compare/v0.5.0...HEAD
[0.5.0]: https://github.com/aymericcucherousset/d2-symfony/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/aymericcucherousset/d2-symfony/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/aymericcucherousset/d2-symfony/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/aymericcucherousset/d2-symfony/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/aymericcucherousset/d2-symfony/releases/tag/v0.1.0
