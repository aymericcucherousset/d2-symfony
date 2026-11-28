# D2 Symfony

[![CI](https://github.com/aymericcucherousset/d2-symfony/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/aymericcucherousset/d2-symfony/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](composer.json)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](phpstan.neon.dist)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Write [D2](https://d2lang.com) diagrams as text in your Twig templates and get inline SVG, rendered by Symfony.

> [!NOTE]
> Early development (0.5): the API may change between minor versions. See the [roadmap](#roadmap).

![How a diagram is rendered: a Twig template uses the |d2 filter, which sends a Diagram to the BinaryRenderer; the d2 binary reads it on stdin and writes the SVG on stdout.](docs/images/example.svg)

<sub>This diagram was rendered by the bundle itself from [`docs/example.d2`](docs/example.d2).</sub>

## Requirements

- PHP 8.2 or later
- Symfony 6.4, 7.4 or 8.x
- Twig 3.21 or later (`symfony/twig-bundle`), for the `|d2` filter and `{% d2 %}` tag
- The [`d2` binary](https://d2lang.com/tour/install), downloaded for you by `bin/console d2:download` (Linux, macOS and Windows on amd64 or arm64)

## Installation

1. Install the bundle:

   ```bash
   composer require aymericcucherousset/d2-symfony
   ```

   With [Symfony Flex](https://symfony.com/doc/current/setup/flex.html), the bundle is registered automatically in `config/bundles.php`.

2. Download the `d2` binary:

   ```bash
   bin/console d2:download
   ```

   The command downloads d2 for your platform from the [terrastruct/d2 releases](https://github.com/terrastruct/d2/releases), verifies its SHA-256 checksum and installs it in `var/d2/<version>/`. Run it again with `--force` to reinstall. Add `/var/d2/` to your `.gitignore`, and run the command in your deployment (or Docker build) too.

   The version is pinned (`d2.binary_version`, a version tested with the bundle). Prefer your own installation? [Install d2](https://d2lang.com/tour/install) in your `PATH` or set `d2.binary_path`: the bundle uses, in order, `d2.binary_path`, the downloaded binary, then `d2` from the `PATH`.

### Without Symfony Flex

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Aymericcucherousset\D2Symfony\D2SymfonyBundle::class => ['all' => true],
];
```

## Configuration

No configuration is required. Here is the full reference with the default values, to put in `config/packages/d2.yaml` if you need to change something:

```yaml
d2:
    # How diagrams are rendered: binary, kroki or chain (see "Choosing a renderer").
    renderer: binary

    # Renderers tried in order when renderer is "chain".
    chain: [binary, kroki]

    kroki:
        # Kroki server: the public instance or a self-hosted one.
        base_url: https://kroki.io
        # HTTP client service, e.g. a scoped client; a default client is used when it does not exist.
        http_client: http_client

    # Name of the d2 executable looked up in the PATH, or path to it.
    # ~ uses the binary installed by d2:download, then "d2" from the PATH.
    binary_path: ~

    # Version installed by bin/console d2:download.
    binary_version: v0.9.0

    # Where bin/console d2:download installs the binary.
    install_dir: '%kernel.project_dir%/var/d2'

    # Maximum rendering time in seconds, for the binary and Kroki; ~ disables the limit
    # (Kroki then uses the HTTP client defaults).
    timeout: 60

    # Options applied to every diagram unless overridden.
    # ~ means "use the d2 default".
    defaults:
        theme: ~         # d2 theme ID, e.g. 200 (Dark Mauve); see the Theme enum
        dark_theme: ~    # theme ID used when the viewer's browser is in dark mode
        layout: ~        # layout engine: dagre (d2 default), elk or tala (TALA plugin)
        pad: ~           # padding around the diagram, in pixels (d2 default: 100)
        sketch: false    # hand-drawn look
        center: false    # center the SVG in its viewbox (local binary only)
        scale: ~         # scale factor, e.g. 0.5; ~ fits SVG to the screen
        font_regular: ~  # path to a .ttf file (local binary only); also
        font_italic: ~   # font_italic, font_bold and font_semibold
        font_bold: ~
        font_semibold: ~

    # Cache rendered SVG (set to false to disable).
    cache:
        enabled: true
        pool: cache.d2 # PSR-6 pool service, created automatically

    twig:
        # Render static {% d2 %} tags when templates are compiled; ~ = when kernel.debug is false.
        prerender_static: ~
```

Invalid values (unknown theme ID or layout engine, negative padding, a scale of 0, unknown keys…) are rejected when the container is built. In PHP configuration files, `theme`, `dark_theme` and `layout` also accept the `Theme` and `Layout` enums.

## Usage

### Twig

Pipe D2 source into the `|d2` filter; it outputs the SVG markup, ready to inline in your HTML:

```twig
{{ 'shop -> payment: checkout'|d2 }}
```

Multi-line diagrams read best with a `{% set %}` block, and options override the configured defaults:

```twig
{% set architecture %}
direction: right
user -> app: HTTPS
app -> db: SQL
db: { shape: cylinder }
{% endset %}

<figure>
    {{ architecture|d2({ theme: 200, sketch: true }) }}
</figure>
```

Available options: `theme` and `dark_theme` (theme ID), `layout` (`dagre`, `elk` or `tala`), `pad` (int), `scale` (number), `sketch` and `center` (bool), and `font_regular`, `font_italic`, `font_bold` and `font_semibold` (paths to .ttf files); `null` resets an option to the d2 default. Twig variables work as usual: `{{ diagram.source|d2 }}`.

The `{% d2 %}` tag lets you write the diagram directly in the template, with the same options:

```twig
{% d2 with { layout: 'elk' } %}
user -> app: {{ protocol }}
{% for service in services %}
app -> {{ service }}
{% endfor %}
{% endd2 %}
```

Twig variables and tags work inside the body; their output is **not** HTML-escaped, since it is D2 source (d2 escapes labels in the generated SVG).

### PHP

Autowire the `Renderer` service anywhere in your application:

```php
use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Layout;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Symfony\Component\HttpFoundation\Response;

final class DiagramController
{
    public function __construct(private Renderer $renderer)
    {
    }

    public function __invoke(): Response
    {
        $svg = $this->renderer->render(new Diagram(
            'a -> b',
            new RenderOptions(layout: Layout::Elk, sketch: true),
        ));

        return new Response($svg, headers: ['Content-Type' => 'image/svg+xml']);
    }
}
```

`RenderOptions` is immutable and validated on construction. Themes and layouts are typed with the `Theme` (every official d2 theme, `isDark()`) and `Layout` enums; their IDs and names (`theme: 200`, `layout: 'elk'`) are accepted too:

```php
use Aymericcucherousset\D2Symfony\Core\Layout;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Core\Theme;

$options = new RenderOptions(theme: Theme::NeutralDefault, darkTheme: Theme::DarkMauve, layout: Layout::Elk, scale: 0.75);
$options = $options->withCenter()->withFonts(regular: '/path/to/Inter-Regular.ttf', bold: '/path/to/Inter-Bold.ttf');
```

Cache keys include the font paths but not the font files: clear `cache.d2` after replacing a font file in place.

### Command line

`bin/console d2:render` renders a `.d2` file with the configured renderer (binary, Kroki or chain) and cache. The SVG goes to stdout unless `-o` is given:

```bash
bin/console d2:render diagram.d2 > diagram.svg
bin/console d2:render diagram.d2 -o public/diagram.svg --theme=0 --dark-theme=200 --layout=elk --sketch
cat diagram.d2 | bin/console d2:render -
```

`--theme`, `--dark-theme`, `--layout`, `--pad`, `--scale`, `--sketch` and `--center` are available; options that aren't given use `d2.defaults`, and `--no-sketch` / `--no-center` turn off a default. D2 syntax errors are reported as `file:line:column: message`.

### Choosing a renderer

| `renderer` | Renders with | Use it when |
| --- | --- | --- |
| `binary` (default) | the local `d2` binary | you can run binaries: fastest, works offline, no data leaves the server |
| `kroki` | a [Kroki](https://kroki.io) server over HTTP | the hosting platform forbids running binaries (some PaaS, serverless, shared hosting) |
| `chain` | each renderer of `chain`, in order | you want a fallback, e.g. the binary in production and Kroki on machines where d2 is not installed |

```yaml
# config/packages/d2.yaml
d2:
    renderer: chain
    chain: [binary, kroki] # default order
    kroki:
        base_url: https://kroki.example.com # optional, defaults to https://kroki.io
```

In `chain` mode, the next renderer is only tried when the current one is **unavailable**: the d2 binary is missing or not executable, or the Kroki server cannot be reached or returns a server error (HTTP 5xx). D2 syntax errors and timeouts are thrown right away, since another renderer would not do better. When every renderer is unavailable, a `NoRendererAvailableException` lists each error.

Kroki receives the `theme`, `dark_theme`, `layout`, `pad`, `sketch` and `scale` options. Options it does not support (`center`, fonts) are ignored, and a warning is logged on the `d2` channel. To authenticate against a self-hosted instance, point `d2.kroki.http_client` to a [scoped client](https://symfony.com/doc/current/http_client.html#scoping-client) with the right headers.

> [!NOTE]
> With `kroki` or `chain`, the diagram source may be sent to the Kroki server. Use a self-hosted instance for private diagrams.

Outside the bundle, the core classes can be used directly:

```php
use Aymericcucherousset\D2Symfony\Core\BinaryRenderer;
use Aymericcucherousset\D2Symfony\Core\ChainRenderer;
use Aymericcucherousset\D2Symfony\Core\KrokiRenderer;
use Symfony\Component\HttpClient\HttpClient;

$renderer = new ChainRenderer([
    new BinaryRenderer(),
    new KrokiRenderer(HttpClient::create(), 'https://kroki.io', timeout: 10.0, logger: $logger),
]);
```

### Caching

Rendered SVG is cached in the `cache.d2` pool (backed by `cache.app`), so each diagram is rendered by d2 only once. The cache key covers the D2 source, the options and the d2 version: diagrams are rendered again after a d2 upgrade (the version is checked every 5 minutes). To use another backend, point `d2.cache.pool` to any PSR-6 pool, or configure `cache.d2` under `framework.cache.pools`.

Clear it with:

```bash
bin/console cache:pool:clear cache.d2
```

#### Pre-rendering static diagrams

When `kernel.debug` is false (production), `{% d2 %}` tags whose body and options are constant are rendered once, when the template is compiled, and the SVG is embedded in the compiled template. Since `bin/console cache:warmup` compiles every template, static diagrams then never call d2 at runtime, even with an empty cache.

A diagram is static when its body contains no Twig expression or tag and its options are a literal hash (`{% d2 with { theme: 200 } %}`). Diagrams that cannot be rendered at compile time (syntax error, d2 missing on the build machine…) are rendered at runtime as usual.

Pre-rendering is disabled in debug mode, as compiled templates outlive changes to the `d2` configuration there. Force it either way with:

```yaml
d2:
    twig:
        prerender_static: true # or false; ~ (default) = enabled when kernel.debug is false
```

### Web Profiler

With the Symfony profiler enabled (`symfony/web-profiler-bundle`) and `kernel.debug` on, a **D2** panel lists the diagrams rendered during the request: a preview of each SVG, its collapsible D2 source and options, the rendering time, cache hits and misses, and the renderer that produced it (with the renderers skipped by a `chain`). The toolbar shows the number of diagrams and their total rendering time, in red when a render failed. Renders also appear in the Performance panel timeline (`d2` category).

Nothing is decorated in production or when the profiler is disabled.

### Errors

Every exception thrown by the bundle implements `Aymericcucherousset\D2Symfony\Core\Exception\D2Exception`:

| Exception | Thrown when |
| --- | --- |
| `RenderException` | d2 rejects the source or rendering fails (timeout, Kroki HTTP error…); `getD2Line()` / `getD2Column()` locate the first D2 error, `getD2Errors()` lists them all, `getD2Error()` returns the raw d2 output |
| `BinaryNotFoundException` | the `d2` binary is missing or not executable (run `bin/console d2:download`) |
| `RemoteUnavailableException` | the Kroki server cannot be reached or returns a server error (HTTP 5xx) |
| `NoRendererAvailableException` | in `chain` mode, every renderer is unavailable; `getFailures()` returns each error |
| `InvalidArgumentException` | a diagram or an option is invalid (empty source, unknown option…) |

`BinaryNotFoundException`, `RemoteUnavailableException` and `NoRendererAvailableException` implement `RendererUnavailableException`: catch it to handle "no renderer could run" separately from D2 errors.

In templates, Twig wraps them in a `Twig\Error\RuntimeError` pointing to the template line. For the `{% d2 %}` tag, a D2 syntax error points to the template line of the faulty D2 line, so the Symfony error page highlights it:

```
D2 syntax error at line 2, column 4: maps must be terminated with } in "page.html.twig" at line 14
```

(The mapping is exact for static bodies; Twig expressions printing several lines shift the lines after them.)

## Roadmap

| Milestone | Goal |
| --- | --- |
| [v0.1.0 — Working core](https://github.com/aymericcucherousset/d2-symfony/milestone/1) | Render a D2 diagram to SVG from Twig using a manually installed binary. |
| [v0.2.0 — Caching & Twig ergonomics](https://github.com/aymericcucherousset/d2-symfony/milestone/2) | Cached rendering, `{% d2 %}` tag, readable errors. |
| [v0.3.0 — Frictionless installation](https://github.com/aymericcucherousset/d2-symfony/milestone/3) | The d2 binary is downloaded automatically. |
| [v0.4.0 — Kroki renderer](https://github.com/aymericcucherousset/d2-symfony/milestone/4) | HTTP renderer for environments where binaries cannot run. |
| [v0.5.0 — Developer experience](https://github.com/aymericcucherousset/d2-symfony/milestone/5) | Profiler panel, rendering CLI, fully typed options. |
| [v0.6.0 — Formats & integrations](https://github.com/aymericcucherousset/d2-symfony/milestone/6) | PNG/PDF output, `.d2` files, Markdown code blocks. |
| [v1.0.0 — Stabilization](https://github.com/aymericcucherousset/d2-symfony/milestone/7) | Frozen API, complete documentation, release. |

## Development

```bash
composer install
composer ci        # PHP-CS-Fixer + PHPStan + Deptrac + PHPUnit
composer cs-fix    # apply coding standards
```

[Deptrac](deptrac.yaml) keeps `src/Core` isolated so it can become a standalone library: Core may only depend on `symfony/process`, `symfony/http-client-contracts`, `psr/cache` and `psr/log`, and never on the bundle code (`composer deptrac`).

Integration tests need the [`d2` binary](https://d2lang.com/tour/install) on your `PATH`; they are skipped otherwise (set `D2_REQUIRED=1` to make them fail instead).

To regenerate the README diagram after editing [`docs/example.d2`](docs/example.d2):

```bash
d2 --pad=20 docs/example.d2 docs/images/example.svg
```

## License

MIT — see [LICENSE](LICENSE).
