<?php

declare(strict_types=1);

namespace Korin\Gedcom\Cli\Diff;

enum ChangeKind: string
{
    case Added = 'added';
    case Removed = 'removed';
    case Changed = 'changed';
}
