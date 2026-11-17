<?php

declare(strict_types=1);

namespace Korin\Gedcom\Cli\Command;

use Korin\Gedcom\Cli\UsageException;
use Korin\Gedcom\Exception\GedcomException;
use Korin\Gedcom\Io\Encoding;
use Korin\Gedcom\ReadOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * What the commands share: reading options, the output format and exit codes. A command returns
 * 0, or 1 for what it found (errors, differences, a conversion stopped at a loss); a file that
 * cannot be read and a wrong option give 2.
 */
abstract class GedcomCommand extends Command
{
    public const FOUND = 1;
    public const UNUSABLE = 2;

    abstract protected function handle(InputInterface $input, OutputInterface $output): int;

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            return $this->handle($input, $output);
        } catch (UsageException|GedcomException $e) {
            self::errors($output)->writeln('<error>' . $e->getMessage() . '</error>');

            return self::UNUSABLE;
        }
    }

    protected function addReadOptions(): void
    {
        $encodings = implode(', ', array_map(static fn(Encoding $e): string => $e->value, Encoding::cases()));
        $this->addOption('encoding', null, InputOption::VALUE_REQUIRED, "Read in this encoding, whatever the file declares: {$encodings}");
        $this->addOption('no-dialects', null, InputOption::VALUE_NONE, 'Do not correct documented mistakes of the program that wrote the file');
    }

    protected function readOptions(InputInterface $input): ReadOptions
    {
        $options = (new ReadOptions())->withDialects(!(bool) $input->getOption('no-dialects'));
        $name = $input->getOption('encoding');
        if (!is_string($name)) {
            return $options;
        }
        foreach (Encoding::cases() as $encoding) {
            if (strcasecmp($encoding->value, $name) === 0) {
                return $options->withEncoding($encoding);
            }
        }

        throw new UsageException("Unknown encoding {$name}");
    }

    protected function addFormatOption(): void
    {
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text');
    }

    protected function json(InputInterface $input): bool
    {
        return match ($input->getOption('format')) {
            'text' => false,
            'json' => true,
            default => throw new UsageException('--format is text or json'),
        };
    }

    /** @param array<mixed> $data */
    protected static function writeJson(OutputInterface $output, array $data): void
    {
        $output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
    }

    protected static function argument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);

        return is_string($value) ? $value : throw new UsageException("{$name} is missing");
    }

    protected static function errors(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }
}
