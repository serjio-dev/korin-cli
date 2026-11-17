<?php

declare(strict_types=1);

namespace Korin\Gedcom\Cli\Command;

use Korin\Gedcom\Cli\Diff\Change;
use Korin\Gedcom\Cli\Diff\ChangeKind;
use Korin\Gedcom\Cli\Diff\DocumentDiff;
use Korin\Gedcom\Cli\Files;
use Korin\Gedcom\Gedcom;
use Korin\Gedcom\Syntax\IndexedDocument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'diff', description: 'Compares two files record by record')]
final class DiffCommand extends GedcomCommand
{
    protected function configure(): void
    {
        $this->addArgument('first', InputArgument::REQUIRED, 'A GEDCOM file, or a .gdz archive');
        $this->addArgument('second', InputArgument::REQUIRED, 'A GEDCOM file, or a .gdz archive');
        $this->addOption('ignore', 'i', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A tag whose records and substructures are not compared, e.g. CHAN or HEAD');
        $this->addFormatOption();
        $this->addReadOptions();
        $this->setHelp(<<<'HELP'
            Records are matched by xref (HEAD, TRLR and other records without one by tag and position),
            and their lines by level, tag and value. Encodings, line endings, CONC/CONT splitting and
            the order of records do not count, so a file and its copy saved by another program, or
            converted, can be compared. The files may be of any size.

            Exit code: 0 no differences, 1 differences, 2 a file cannot be read.
            HELP);
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $paths = [self::argument($input, 'first'), self::argument($input, 'second')];
        $json = $this->json($input);
        $options = $this->readOptions($input);
        $documents = array_map(static fn(string $path): IndexedDocument => Gedcom::open(Files::readable($path), $options), $paths);
        /** @var list<string> $ignore */
        $ignore = $input->getOption('ignore');
        $changes = (new DocumentDiff($ignore))->compare(...$documents);

        if ($json) {
            $files = array_map(static fn(string $path, IndexedDocument $d): array => ['file' => $path, 'version' => $d->version()->value, 'encoding' => $d->encoding()->value], $paths, $documents);
            $list = iterator_to_array($changes, false);
            self::writeJson($output, ['first' => $files[0], 'second' => $files[1], 'changes' => $list]);

            return $list === [] ? self::SUCCESS : self::FOUND;
        }

        foreach (['---' => 0, '+++' => 1] as $mark => $i) {
            $output->writeln(sprintf('<comment>%s %s</comment>  %s, %s', $mark, OutputFormatter::escape($paths[$i]), $documents[$i]->version()->value, $documents[$i]->encoding()->value));
        }
        $counts = ['changed' => 0, 'removed' => 0, 'added' => 0];
        foreach ($changes as $change) {
            ++$counts[$change->kind->value];
            $this->write($output, $change);
        }
        $found = array_filter($counts, static fn(int $n): bool => $n > 0);
        $output->writeln($found === [] ? '<info>No differences</info>' : implode(', ', array_map(
            static fn(string $kind, int $n): string => "{$n} {$kind}",
            array_keys($found),
            $found,
        )));

        return $found === [] ? self::SUCCESS : self::FOUND;
    }

    private function write(OutputInterface $output, Change $change): void
    {
        $record = OutputFormatter::escape(str_starts_with($change->key, '@') ? "{$change->key} {$change->tag}" : $change->key);
        $output->writeln(match ($change->kind) {
            ChangeKind::Added => "<fg=green>+ {$record}</>",
            ChangeKind::Removed => "<fg=red>- {$record}</>",
            ChangeKind::Changed => "<fg=yellow>~ {$record}</>",
        });
        foreach ($change->lines as [$op, $path, $value]) {
            $line = OutputFormatter::escape(rtrim("{$op} {$path} {$value}"));
            $output->writeln($op === '-' ? "    <fg=red>{$line}</>" : "    <fg=green>{$line}</>");
        }
    }
}
