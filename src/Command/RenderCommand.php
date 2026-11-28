<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Command;

use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\D2Exception;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'd2:render', description: 'Render a D2 file to SVG with the configured renderer')]
final class RenderCommand extends Command
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly RenderOptions $defaults = new RenderOptions(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('input', InputArgument::REQUIRED, 'Path to the .d2 file, or "-" to read from stdin')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write the SVG to this file instead of stdout')
            ->addOption('theme', 't', InputOption::VALUE_REQUIRED, 'd2 theme ID')
            ->addOption('layout', 'l', InputOption::VALUE_REQUIRED, 'Layout engine, e.g. "dagre" or "elk"')
            ->addOption('sketch', 's', InputOption::VALUE_NEGATABLE, 'Render with a hand-drawn look (--no-sketch disables it)')
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command renders a D2 file to SVG and prints it on stdout:

                  <info>php %command.full_name% diagram.d2 > diagram.svg</info>
                  <info>php %command.full_name% diagram.d2 -o public/diagram.svg --theme=200 --sketch</info>
                  <info>cat diagram.d2 | php %command.full_name% -</info>

                Diagrams are rendered with the configured renderer (<comment>d2.renderer</comment>: binary,
                Kroki or chain) and cache. Options not given on the command line use
                <comment>d2.defaults</comment>.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Keep stdout for the SVG: messages go to stderr.
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        /** @var string $path */
        $path = $input->getArgument('input');
        /** @var string|null $target */
        $target = $input->getOption('output');

        try {
            $diagram = new Diagram($this->readSource($input, $path), $this->options($input));
            $svg = $this->renderer->render($diagram);
        } catch (RenderException $e) {
            $io->error($e->getMessage());
            foreach ($e->getD2Errors() as $error) {
                $io->text(\sprintf('%s:%d:%d: %s', '-' === $path ? 'stdin' : $path, $error['line'], $error['column'], $error['message']));
            }

            return Command::FAILURE;
        } catch (D2Exception|InvalidOptionException|\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (null === $target) {
            $output->write($svg, false, OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        }

        if (false === @file_put_contents($target, $svg)) {
            $io->error(\sprintf('Unable to write the SVG to "%s": %s', $target, error_get_last()['message'] ?? 'unknown error'));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Diagram rendered to "%s".', $target));

        return Command::SUCCESS;
    }

    /**
     * @throws \RuntimeException when the file cannot be read
     */
    private function readSource(InputInterface $input, string $path): string
    {
        if ('-' === $path) {
            $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
            $source = stream_get_contents($stream ?? \STDIN);
        } elseif (!is_file($path)) {
            throw new \RuntimeException(\sprintf('The D2 file "%s" does not exist.', $path));
        } else {
            $source = @file_get_contents($path);
        }

        if (false === $source) {
            throw new \RuntimeException(\sprintf('Unable to read the D2 source from "%s".', '-' === $path ? 'stdin' : $path));
        }

        return $source;
    }

    /**
     * @throws D2Exception            on invalid option values
     * @throws InvalidOptionException when --theme is not an integer
     */
    private function options(InputInterface $input): RenderOptions
    {
        $options = [];

        /** @var string|null $theme */
        $theme = $input->getOption('theme');
        if (null !== $theme) {
            if (1 !== preg_match('/^\d+$/', $theme)) {
                throw new InvalidOptionException(\sprintf('The "--theme" option must be a theme ID (a positive integer), "%s" given.', $theme));
            }
            $options['theme'] = (int) $theme;
        }

        if (null !== $layout = $input->getOption('layout')) {
            $options['layout'] = $layout;
        }

        if (null !== $sketch = $input->getOption('sketch')) {
            $options['sketch'] = (bool) $sketch;
        }

        return $this->defaults->merge($options);
    }
}
