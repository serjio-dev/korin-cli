<?php

declare(strict_types=1);

namespace Korin\Gedcom\Cli\Diff;

/** A record that is in one file only, or differs between them. */
final readonly class Change implements \JsonSerializable
{
    /**
     * @param string                             $key   the xref, or the tag of a record without one (HEAD; NOTE[2] for the second)
     * @param list<array{'-'|'+', string, string}> $lines for a changed record: the lines only in the first file (-) or the second (+), as path and value
     */
    public function __construct(
        public string $key,
        public string $tag,
        public ChangeKind $kind,
        public array $lines = [],
    ) {}

    /** @return array{key: string, tag: string, change: string, lines?: list<array{op: string, path: string, value: string}>} */
    public function jsonSerialize(): array
    {
        return ['key' => $this->key, 'tag' => $this->tag, 'change' => $this->kind->value]
            + ($this->lines === [] ? [] : ['lines' => array_map(static fn(array $l): array => ['op' => $l[0], 'path' => $l[1], 'value' => $l[2]], $this->lines)]);
    }
}
