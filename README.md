# D2 Symfony

[![CI](https://github.com/aymericcucherousset/d2-symfony/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/aymericcucherousset/d2-symfony/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](composer.json)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](phpstan.neon.dist)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Render [D2](https://d2lang.com) diagrams in Symfony & Twig: local binary or Kroki, cached, profiler-ready.

> Work in progress — not released yet.

## Development

```bash
composer install
composer ci        # PHP-CS-Fixer + PHPStan + PHPUnit
composer cs-fix    # apply coding standards
```

Integration tests need the [`d2` binary](https://d2lang.com/tour/install) on your `PATH`; they are skipped otherwise.

## License

MIT — see [LICENSE](LICENSE).
