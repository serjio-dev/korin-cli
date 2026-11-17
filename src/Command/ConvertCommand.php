<?php

declare(strict_types=1);

namespace Korin\Gedcom\Cli\Command;

use Korin\Gedcom\Cli\Files;
use Korin\Gedcom\Cli\UsageException;
use Korin\Gedcom\Conversion\LossPolicy;
use Korin\Gedcom\Conversion\LossReport;
use Korin\Gedcom\ConvertOptions;
use Korin\Gedcom\Exception\ConversionException;
use Korin\Gedcom\Gedcom;
use Korin\Gedcom\Version;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'convert', description: 'Converts a file to another GEDCOM version and reports what it could not keep')]
final class ConvertCommand extends GedcomCommand
{
    private const POLICIES = [
        'extension' => LossPolicy::PreserveAsExtension,
        'drop' => LossPolicy::DropWithReport,
        'fail' => LossPolicy::Fail,
    ];

    protected function configure(): void
    {
        $targets = implode(', ', array_map(static fn(Version $v): string => $v->value, self::targets()));
        $this->addArgument('input', InputArgument::REQUIRED, 'A GEDCOM file, or a .gdz archive');
        $this->addArgument('output', InputArgument::REQUIRED, 'The file to write; - for the standard output');
        $this->addOption('to', 't', InputOption::VALUE_REQUIRED, "The version to convert to: {$targets}");
        $this->addOption('policy', null, InputOption::VALUE_REQUIRED, 'Data the version cannot hold: extension (kept as an extension tag), drop or fail', 'extension');
        $this->addOption('losses', null, InputOption::VALUE_REQUIRED, 'Also write the loss report to this file, as JSON');
        $this->addFormatOption();
        $this->addReadOptions();
        $this->setHelp(<<<'HELP'
            The file is converted record by record, so files of any size are fine. Every loss is
            reported with a stable code (KL*): https://github.com/serjio-dev/korin-php/blob/main/docs/losses.md.
            The output is written to a temporary file next to it and renamed when the conversion succeeds.

            Exit code: 0 converted, 1 stopped at a loss with --policy=fail, 2 the file cannot be read or written.
            HELP);
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $from = self::argument($input, 'input');
        $to = self::argument($input, 'output');
        $json = $this->json($input);
        $version = self::version($input->getOption('to'));
        $policy = $input->getOption('policy');
        $policy = is_string($policy) && isset(self::POLICIES[$policy]) ? self::POLICIES[$policy] : throw new UsageException('--policy is extension, drop or fail');
        $lossFile = $input->getOption('losses');
        if (Files::isArchive($to)) {
            throw new UsageException('Writing .gdz is not supported yet: write a .ged file');
        }
        $source = Files::readable($from);
        if ($to !== '-' && file_exists($to) && realpath($to) === realpath($from)) {
            throw new UsageException('The output is the input file');
        }
        // With the converted file on the standard output, the report goes to the standard error.
        $report = $to === '-' ? self::errors($output) : $output;

        try {
            $losses = $this->convert($source, $to, $version, new ConvertOptions($policy), $input);
        } catch (ConversionException $e) {
            self::errors($output)->writeln('<error>' . OutputFormatter::escape($e->getMessage()) . '</error>');

            return self::FOUND;
        }
        if (is_string($lossFile)) {
            if (file_put_contents($lossFile, json_encode($losses, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n") === false) {
                throw new UsageException("Cannot write {$lossFile}");
            }
        }
        if ($json) {
            self::writeJson($report, ['input' => $from, 'output' => $to, 'losses' => $losses]);
        } else {
            foreach ($losses as $loss) {
                $report->writeln(OutputFormatter::escape((string) $loss));
            }
            $count = count($losses);
            $report->writeln(sprintf(
                'Converted to GEDCOM %s%s: %s',
                $version->value,
                $to === '-' ? '' : ' in ' . OutputFormatter::escape($to),
                match ($count) {
                    0 => '<info>nothing lost</info>',
                    1 => '1 loss',
                    default => "{$count} losses",
                },
            ));
        }

        return self::SUCCESS;
    }

    private function convert(string $source, string $to, Version $version, ConvertOptions $options, InputInterface $input): LossReport
    {
        if ($to === '-') {
            $stdout = fopen('php://stdout', 'wb');
            if ($stdout === false) {
                throw new UsageException('Cannot write to the standard output');
            }

            return Gedcom::convertFile($source, $stdout, $version, $options, $this->readOptions($input));
        }
        $temporary = @tempnam(dirname($to), '.korin-');
        if ($temporary === false) {
            throw new UsageException("Cannot write {$to}");
        }
        try {
            $losses = Gedcom::convertFile($source, $temporary, $version, $options, $this->readOptions($input));
            // tempnam() makes the file readable by its owner only.
            @chmod($temporary, 0o666 & ~umask());
            if (!@rename($temporary, $to)) {
                throw new UsageException("Cannot write {$to}");
            }

            return $losses;
        } finally {
            if (file_exists($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private static function version(mixed $value): Version
    {
        if (!is_string($value)) {
            throw new UsageException('--to is missing');
        }
        foreach (self::targets() as $version) {
            if ($version->value === $value) {
                return $version;
            }
        }
        $targets = implode(', ', array_map(static fn(Version $v): string => $v->value, self::targets()));

        throw new UsageException("--to is one of {$targets}");
    }

    /** @return list<Version> */
    private static function targets(): array
    {
        return array_values(array_filter(Version::cases(), static fn(Version $v): bool => $v->isWritable()));
    }
}
