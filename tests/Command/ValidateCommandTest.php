<?php

declare(strict_types=1);

namespace Korin\Gedcom\Cli\Tests\Command;

use Korin\Gedcom\Cli\Tests\CommandTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ValidateCommandTest extends CommandTestCase
{
    private const INVALID = "0 HEAD\n1 GEDC\n2 VERS 5.5.1\n2 FORM LINEAGE-LINKED\n1 CHAR UTF-8\n0 @I1@ INDI\n1 SEX Q\n1 FAMC @F9@\n0 TRLR\n";

    #[Test]
    public function aValidFileGivesZero(): void
    {
        $tester = $this->command('validate', ['file' => self::FAMILY, '--plausibility' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('(GEDCOM 5.5.1): valid', $tester->getDisplay(true));
    }

    #[Test]
    public function errorsGiveOne(): void
    {
        $tester = $this->command('validate', ['file' => $this->file('invalid.ged', self::INVALID)]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertMatchesRegularExpression('/^KV\d{4} error line 7 \(@I1@\/SEX\)/m', $tester->getDisplay(true));
        self::assertMatchesRegularExpression('/: \d+ errors?/', $tester->getDisplay(true));
    }

    #[Test]
    public function codesCanBeDisabled(): void
    {
        $path = $this->file('invalid.ged', self::INVALID);
        $codes = array_map(strtolower(...), self::column(self::json($this->command('validate', ['file' => $path, '--format' => 'json'])), 'diagnostics', 'code'));
        self::assertNotSame([], $codes);
        $tester = $this->command('validate', ['file' => $path, '--format' => 'json', '--disable' => $codes]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame([], self::json($tester)['diagnostics']);
        self::assertTrue(self::json($tester)['valid']);
    }

    #[Test]
    public function warningsFailOnlyWhenAsked(): void
    {
        // CHAR ANSI is not a 5.5.1 value but is read: KV1101 is a warning.
        $path = $this->file('ansi.ged', "0 HEAD\n1 SOUR Korin\n1 SUBM @U1@\n1 GEDC\n2 VERS 5.5.1\n2 FORM LINEAGE-LINKED\n1 CHAR ANSI\n0 @U1@ SUBM\n1 NAME Anna\n0 TRLR\n");
        $json = self::json($this->command('validate', ['file' => $path, '--format' => 'json', '--disable' => ['KV3006']]));

        self::assertSame(['warning'], self::column($json, 'diagnostics', 'severity'));
        self::assertSame(0, $this->command('validate', ['file' => $path, '--disable' => ['KV3006']])->getStatusCode());
        self::assertSame(1, $this->command('validate', ['file' => $path, '--disable' => ['KV3006'], '--fail-on' => 'warning'])->getStatusCode());
        self::assertSame(2, $this->command('validate', ['file' => $path, '--fail-on' => 'info'])->getStatusCode());
    }
}
