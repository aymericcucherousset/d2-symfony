<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Command;

use Aymericcucherousset\D2Symfony\Core\Exception\D2Exception;
use Aymericcucherousset\D2Symfony\Downloader\BinaryDownloader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'd2:download', description: 'Download the d2 binary used to render diagrams')]
final class DownloadCommand extends Command
{
    public function __construct(private readonly BinaryDownloader $downloader)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Download the binary again even if it is already installed')
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command downloads the d2 binary for your platform from the
                terrastruct/d2 GitHub releases, verifies its SHA-256 checksum and installs it in the
                <comment>d2.install_dir</comment> directory (<comment>var/d2</comment> by default).

                The version is set by <comment>d2.binary_version</comment>. A binary configured with
                <comment>d2.binary_path</comment> takes precedence over the downloaded one.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $version = $this->downloader->getVersion();

        try {
            if (!$force && $this->downloader->isInstalled()) {
                $io->note(\sprintf('d2 %s is already installed at "%s". Use --force to download it again.', $version, $this->downloader->getBinaryPath()));

                return Command::SUCCESS;
            }

            $io->text(\sprintf('Downloading d2 %s (%s)…', $version, $this->downloader->getPlatform()));
            $path = $this->downloader->download($force);
        } catch (D2Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('d2 %s installed at "%s".', $version, $path));

        return Command::SUCCESS;
    }
}
