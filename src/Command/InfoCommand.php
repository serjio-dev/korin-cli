<?php

declare(strict_types=1);

namespace Korin\Gedcom\Cli\Command;

use Korin\Gedcom\Cli\Files;
use Korin\Gedcom\Diagnostic;
use Korin\Gedcom\Gedcom;
use Korin\Gedcom\Severity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'info', description: 'Shows the version, encoding, program and records of a file')]
final class InfoCommand extends GedcomCommand
{
    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'A GEDCOM file, or a .gdz archive');
        $this->addFormatOption();
        $this->addReadOptions();
        $this->setHelp('The file is indexed, not read whole, so files of any size are fine. With -v the diagnostics of reading are listed.');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $path = self::argument($input, 'file');
        $json = $this->json($input);
        $document = Gedcom::open(Files::readable($path), $this->readOptions($input));
        $records = [];
        foreach ($document->records() as $record) {
            if ($record->tag() !== 'HEAD' && $record->tag() !== 'TRLR') {
                $records[$record->tag()] = ($records[$record->tag()] ?? 0) + 1;
            }
        }
        arsort($records);
        $head = $document->head();
        $system = $document->sourceSystem();
        $info = [
            'file' => $path,
            'size' => (int) filesize($path),
            'version' => $document->version()->value,
            'declaredVersion' => $head?->first('GEDC')?->first('VERS')?->value(),
            'encoding' => $document->encoding()->value,
            'declaredChar' => $head?->first('CHAR')?->value(),
            'bom' => $document->hasBom(),
            'lineEnding' => match ($document->lineEnding()) {
                "\r\n" => 'CRLF',
                "\r" => 'CR',
                default => 'LF',
            },
            'source' => $system === null ? null : [
                'identifier' => $system->identifier,
                'version' => $system->version(),
                'name' => $system->name,
                'program' => $system->program()?->value,
            ],
            'records' => $records,
            'diagnostics' => $document->diagnostics(),
        ];
        if ($json) {
            self::writeJson($output, $info);

            return self::SUCCESS;
        }

        $version = $info['version'] . ($info['declaredVersion'] !== null && trim($info['declaredVersion']) !== $info['version'] ? " (declares {$info['declaredVersion']})" : '');
        $encoding = $info['encoding']
            . ($info['declaredChar'] !== null && strcasecmp($info['declaredChar'], $info['encoding']) !== 0 ? " (CHAR {$info['declaredChar']})" : '')
            . ", {$info['lineEnding']}" . ($info['bom'] ? ', BOM' : '');
        $rows = [
            'File' => $path . ', ' . self::size($info['size']),
            'Version' => $version,
            'Encoding' => $encoding,
            'Program' => $system === null ? '-' : trim(($system->program()->value ?? $system->identifier) . ' ' . $system->version())
                . ($system->name !== null && $system->program() === null ? " ({$system->name})" : ''),
            'Records' => number_format(array_sum($records), 0, '.', ' ')
                . ($records === [] ? '' : ': ' . implode(', ', array_map(static fn(string $tag, int $n): string => "{$tag} {$n}", array_keys($records), $records))),
            'Diagnostics' => self::counts($document->diagnostics()) . ($output->isVerbose() || $document->diagnostics() === [] ? '' : ' (-v to list)'),
        ];
        foreach ($rows as $label => $value) {
            $output->writeln(sprintf('<info>%-12s</info> %s', $label, OutputFormatter::escape($value)));
        }
        if ($output->isVerbose()) {
            foreach ($document->diagnostics() as $diagnostic) {
                $output->writeln('  ' . OutputFormatter::escape((string) $diagnostic));
            }
        }

        return self::SUCCESS;
    }

    /** @param list<Diagnostic> $diagnostics */
    public static function counts(array $diagnostics): string
    {
        if ($diagnostics === []) {
            return 'none';
        }
        $parts = [];
        foreach ([Severity::Error, Severity::Warning, Severity::Info] as $severity) {
            $n = count(array_filter($diagnostics, static fn(Diagnostic $d): bool => $d->severity === $severity));
            if ($n > 0) {
                $parts[] = $n . ' ' . match ($severity) {
                    Severity::Error => $n === 1 ? 'error' : 'errors',
                    Severity::Warning => $n === 1 ? 'warning' : 'warnings',
                    Severity::Info => 'info',
                };
            }
        }

        return implode(', ', $parts);
    }

    private static function size(int $bytes): string
    {
        return match (true) {
            $bytes < 1024 => "{$bytes} B",
            $bytes < 1024 ** 2 => sprintf('%.1f KB', $bytes / 1024),
            default => sprintf('%.1f MB', $bytes / 1024 ** 2),
        };
    }
}
