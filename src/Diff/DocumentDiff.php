<?php

declare(strict_types=1);

namespace Korin\Gedcom\Cli\Diff;

use Korin\Gedcom\Syntax\IndexedDocument;
use Korin\Gedcom\Syntax\Node;

/**
 * Compares two files record by record, as trees: records are matched by xref (records without
 * one, such as HEAD, by tag and position), and the lines of a record by level, tag and value.
 * Encodings, line endings, CONC/CONT splitting and the order of records do not count; the
 * order of lines in a record does. Of records with the same xref, the first is compared.
 */
final class DocumentDiff
{
    /** Above this many line pairs, a changed record is shown as all its lines removed and added. */
    private const MAX_PAIRS = 4_000_000;

    /** @var array<string, true> */
    private array $ignore;

    /** @param list<string> $ignore tags whose records and substructures are not compared (CHAN, HEAD) */
    public function __construct(array $ignore = [])
    {
        $this->ignore = array_fill_keys(array_map(strtoupper(...), $ignore), true);
    }

    /** @return \Generator<int, Change> the changes, in the order of the first file, then the added records */
    public function compare(IndexedDocument $a, IndexedDocument $b): \Generator
    {
        // Only the keys of the second file are kept, and the lines of its few records without an xref.
        $others = [];
        $counts = [];
        foreach ($b->records() as $record) {
            $key = $this->key($record, $counts);
            if ($key !== null && !isset($others[$key])) {
                $others[$key] = [$record->tag(), $record->xref() === null ? $this->lines($record) : null];
            }
        }
        $seen = [];
        $counts = [];
        foreach ($a->records() as $record) {
            $key = $this->key($record, $counts);
            if ($key === null || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if (!isset($others[$key])) {
                yield new Change($key, $record->tag(), ChangeKind::Removed);

                continue;
            }
            $other = $others[$key][1] ?? $this->lines($b->record($key) ?? throw new \LogicException("{$key} is not in the second file"));
            unset($others[$key]);
            $lines = self::difference($this->lines($record), $other);
            if ($lines !== []) {
                yield new Change($key, $record->tag(), ChangeKind::Changed, $lines);
            }
        }
        foreach ($others as $key => [$tag]) {
            yield new Change($key, $tag, ChangeKind::Added);
        }
    }

    /** @param array<string, int> $counts */
    private function key(Node $record, array &$counts): ?string
    {
        $tag = $record->tag();
        if (isset($this->ignore[$tag]) || $tag === '') {
            return null;
        }
        if ($record->xref() !== null) {
            return $record->xref();
        }
        $n = $counts[$tag] = ($counts[$tag] ?? 0) + 1;

        return $n === 1 ? $tag : "{$tag}[{$n}]";
    }

    /** @return list<array{string, string, string}> the compared form of each line, its path and value */
    private function lines(Node $record): array
    {
        $lines = [];
        $this->walk($record, 0, $lines);

        return $lines;
    }

    /** @param list<array{string, string, string}> $lines */
    private function walk(Node $node, int $level, array &$lines): void
    {
        $value = $node->pointer() ?? $node->value();
        $value = $value === null ? '' : str_replace(["\r\n", "\r", "\n"], '\n', $value);
        $xref = $level === 0 ? $node->xref() : null;
        $lines[] = [
            "{$level} " . ($xref === null ? '' : "{$xref} ") . $node->tag() . ' ' . $value,
            $level === 0 ? ($xref === null ? $node->tag() : "{$xref} {$node->tag()}") : $node->path(),
            $value,
        ];
        foreach ($node->children() as $child) {
            if (!isset($this->ignore[$child->tag()])) {
                $this->walk($child, $level + 1, $lines);
            }
        }
    }

    /**
     * The lines of a longest common subsequence left out, in order.
     *
     * @param list<array{string, string, string}> $a
     * @param list<array{string, string, string}> $b
     *
     * @return list<array{'-'|'+', string, string}>
     */
    private static function difference(array $a, array $b): array
    {
        $start = 0;
        $endA = count($a);
        $endB = count($b);
        while ($start < $endA && $start < $endB && $a[$start][0] === $b[$start][0]) {
            ++$start;
        }
        while ($endA > $start && $endB > $start && $a[$endA - 1][0] === $b[$endB - 1][0]) {
            --$endA;
            --$endB;
        }
        $x = array_slice($a, $start, $endA - $start);
        $y = array_slice($b, $start, $endB - $start);
        $n = count($x);
        $m = count($y);
        if ($n === 0 || $m === 0 || $n * $m > self::MAX_PAIRS) {
            return self::rest($x, 0, $y, 0);
        }
        // $length[$i][$j]: the longest common subsequence of $x from $i and $y from $j.
        $length = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; --$i) {
            for ($j = $m - 1; $j >= 0; --$j) {
                $length[$i][$j] = $x[$i][0] === $y[$j][0] ? $length[$i + 1][$j + 1] + 1 : max($length[$i + 1][$j], $length[$i][$j + 1]);
            }
        }
        $lines = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($x[$i][0] === $y[$j][0]) {
                ++$i;
                ++$j;
            } elseif ($length[$i + 1][$j] >= $length[$i][$j + 1]) {
                $lines[] = ['-', $x[$i][1], $x[$i][2]];
                ++$i;
            } else {
                $lines[] = ['+', $y[$j][1], $y[$j][2]];
                ++$j;
            }
        }

        return [...$lines, ...self::rest($x, $i, $y, $j)];
    }

    /**
     * The lines of $x from $i as removed and of $y from $j as added.
     *
     * @param list<array{string, string, string}> $x
     * @param list<array{string, string, string}> $y
     *
     * @return list<array{'-'|'+', string, string}>
     */
    private static function rest(array $x, int $i, array $y, int $j): array
    {
        $lines = [];
        for ($n = count($x); $i < $n; ++$i) {
            $lines[] = ['-', $x[$i][1], $x[$i][2]];
        }
        for ($m = count($y); $j < $m; ++$j) {
            $lines[] = ['+', $y[$j][1], $y[$j][2]];
        }

        return $lines;
    }
}
