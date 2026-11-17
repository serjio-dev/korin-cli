<?php

declare(strict_types=1);

namespace Korin\Gedcom\Cli\Command;

use Korin\Gedcom\Cli\Files;
use Korin\Gedcom\Cli\UsageException;
use Korin\Gedcom\Diagnostic;
use Korin\Gedcom\Gedcom;
use Korin\Gedcom\Severity;
use Korin\Gedcom\Validation\ValidationOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'validate', description: 'Checks a file against the rules of its version')]
final class ValidateCommand extends GedcomCommand
{
    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'A GEDCOM file, or a .gdz archive');
        $this->addOption('plausibility', 'p', InputOption::VALUE_NONE, 'Also check dates and the pedigree: births after deaths, cycles (KV5xxx)');
        $this->addOption('disable', 'd', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A diagnostic code not to report, e.g. KV1101');
        $this->addOption('fail-on', null, InputOption::VALUE_REQUIRED, 'Exit with 1 on diagnostics of this severity or worse: error or warning', 'error');
        $this->addFormatOption();
        $this->addReadOptions();
        $this->setHelp(<<<'HELP'
            Diagnostics of reading (KV1xxx), structure (KV2xxx), values (KV3xxx) and references (KV4xxx)
            are reported; the codes are listed in https://github.com/serjio-dev/korin-php/blob/main/docs/diagnostics.md.
            The file is read record by record, so files of any size are fine.

            Exit code: 0 valid, 1 errors (or warnings with --fail-on=warning), 2 the file cannot be read.
            HELP);
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $path = self::argument($input, 'file');
        $json = $this->json($input);
        $failOn = match ($input->getOption('fail-on')) {
            'error' => [Severity::Error],
            'warning' => [Severity::Error, Severity::Warning],
            default => throw new UsageException('--fail-on is error or warning'),
        };
        $options = (new ValidationOptions())->withPlausibility((bool) $input->getOption('plausibility'));
        /** @var list<string> $disabled */
        $disabled = $input->getOption('disable');
        if ($disabled !== []) {
            $options = $options->withDisabled(...array_map(strtoupper(...), $disabled));
        }
        $document = Gedcom::open(Files::readable($path), $this->readOptions($input));
        $diagnostics = Gedcom::validate($document, $options)->diagnostics();
        $failed = array_filter($diagnostics, static fn(Diagnostic $d): bool => in_array($d->severity, $failOn, true)) !== [];

        if ($json) {
            self::writeJson($output, ['file' => $path, 'version' => $document->version()->value, 'valid' => !$failed, 'diagnostics' => $diagnostics]);
        } else {
            foreach ($diagnostics as $diagnostic) {
                $style = match ($diagnostic->severity) {
                    Severity::Error => 'fg=red',
                    Severity::Warning => 'fg=yellow',
                    Severity::Info => 'fg=default',
                };
                $output->writeln("<{$style}>" . OutputFormatter::escape((string) $diagnostic) . '</>');
            }
            $summary = OutputFormatter::escape($path) . " (GEDCOM {$document->version()->value}): ";
            $output->writeln($summary . ($diagnostics === [] ? '<info>valid</info>' : InfoCommand::counts($diagnostics)));
        }

        return $failed ? self::FOUND : self::SUCCESS;
    }
}
