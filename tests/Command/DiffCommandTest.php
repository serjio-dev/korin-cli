<?php

declare(strict_types=1);

namespace Korin\Gedcom\Cli\Tests\Command;

use Korin\Gedcom\Cli\Tests\CommandTestCase;
use PHPUnit\Framework\Attributes\Test;

final class DiffCommandTest extends CommandTestCase
{
    #[Test]
    public function aCopyInAnotherEncodingIsTheSame(): void
    {
        // Windows-1251, CRLF, the families before the individuals, the note split with CONC.
        $utf8 = str_replace(['1 CHAR UTF-8', "2 CONT другий рядок\n"], ['1 CHAR WINDOWS-1251', "2 CONT друг\n2 CONC ий рядок\n"], (string) file_get_contents(self::FAMILY));
        [$head, $rest] = explode("0 @U1@ SUBM\n", $utf8, 2);
        [$individuals, $family] = explode("0 @F1@ FAM\n", $rest, 2);
        $copy = $head . "0 @F1@ FAM\n" . str_replace("0 TRLR\n", '', $family) . "0 @U1@ SUBM\n" . $individuals . "0 TRLR\n";
        $path = $this->file('copy.ged', str_replace("\n", "\r\n", (string) iconv('UTF-8', 'CP1251', $copy)));

        $tester = $this->command('diff', ['first' => self::FAMILY, 'second' => $path, '--ignore' => ['char']]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('No differences', $tester->getDisplay());
        self::assertStringContainsString('5.5.1, Windows-1251', $tester->getDisplay());
    }

    #[Test]
    public function showsChangedAddedAndRemovedRecords(): void
    {
        $edited = str_replace(
            ['1 NAME Анна /Коваль/', "2 DATE 1 JAN 2026\n", "0 @I2@ INDI\n1 NAME Петро /Коваль/\n1 SEX M\n1 FAMS @F1@\n", "0 TRLR\n"],
            ['1 NAME Анна /Ковальова/', "2 DATE 17 NOV 2026\n", '', "0 @I3@ INDI\n1 NAME Іван /Коваль/\n0 TRLR\n"],
            (string) file_get_contents(self::FAMILY),
        );
        $path = $this->file('edited.ged', $edited);

        $tester = $this->command('diff', ['first' => self::FAMILY, 'second' => $path]);
        $ignored = $this->command('diff', ['first' => self::FAMILY, 'second' => $path, '--ignore' => ['CHAN']]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString(<<<'TEXT'
            ~ @I1@ INDI
                - @I1@/NAME Анна /Коваль/
                + @I1@/NAME Анна /Ковальова/
                - @I1@/CHAN/DATE 1 JAN 2026
                + @I1@/CHAN/DATE 17 NOV 2026
            - @I2@ INDI
            + @I3@ INDI
            1 changed, 1 removed, 1 added
            TEXT, $tester->getDisplay());
        self::assertStringNotContainsString('CHAN', $ignored->getDisplay());
    }

    #[Test]
    public function writesJson(): void
    {
        $path = $this->file('edited.ged', str_replace('1 SEX F', "1 SEX F\n1 OCCU Лікарка", (string) file_get_contents(self::FAMILY)));
        $data = self::json($this->command('diff', ['first' => self::FAMILY, 'second' => $path, '--format' => 'json']));

        self::assertSame(['file' => self::FAMILY, 'version' => '5.5.1', 'encoding' => 'UTF-8'], $data['first']);
        self::assertSame([['key' => '@I1@', 'tag' => 'INDI', 'change' => 'changed', 'lines' => [['op' => '+', 'path' => '@I1@/OCCU', 'value' => 'Лікарка']]]], $data['changes']);
    }

    #[Test]
    public function aMissingFileGivesTwo(): void
    {
        self::assertSame(2, $this->command('diff', ['first' => self::FAMILY, 'second' => "{$this->directory}/missing.ged"])->getStatusCode());
    }
}
