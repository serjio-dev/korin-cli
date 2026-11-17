<?php

declare(strict_types=1);

namespace Korin\Gedcom\Cli\Tests\Command;

use Korin\Gedcom\Cli\Tests\CommandTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\OutputInterface;

final class InfoCommandTest extends CommandTestCase
{
    #[Test]
    public function describesTheFile(): void
    {
        $tester = $this->command('info', ['file' => self::FAMILY]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertMatchesRegularExpression('/^Version +5\.5\.1$/m', $display);
        self::assertMatchesRegularExpression('/^Encoding +UTF-8, LF$/m', $display);
        self::assertMatchesRegularExpression('/^Program +Korin$/m', $display);
        self::assertMatchesRegularExpression('/^Records +4: INDI 2, SUBM 1, FAM 1$/m', $display);
        self::assertMatchesRegularExpression('/^Diagnostics +none$/m', $display);
    }

    #[Test]
    public function showsDialectsAndDiagnostics(): void
    {
        $path = $this->file('paf.ged', "0 HEAD\r\n1 SOUR PAF\r\n2 VERS 5.2.18.0\r\n1 GEDC\r\n2 VERS 5.5\r\n1 CHAR ANSI\r\n0 @I1@ INDI\r\n1 NAME Ren\xE9e\r\n0 TRLR\r\n");
        $tester = $this->command('info', ['file' => $path]);
        $verbose = $this->command('info', ['file' => $path], OutputInterface::VERBOSITY_VERBOSE);

        self::assertMatchesRegularExpression('/^Version +5\.5\.1 \(declares 5\.5\)$/m', $tester->getDisplay());
        self::assertMatchesRegularExpression('/^Encoding +Windows-1252 \(CHAR ANSI\), CRLF$/m', $tester->getDisplay());
        self::assertMatchesRegularExpression('/^Program +Personal Ancestral File 5\.2\.18\.0$/m', $tester->getDisplay());
        self::assertStringContainsString('(-v to list)', $tester->getDisplay());
        self::assertStringContainsString('KV1010 info', $verbose->getDisplay());
    }

    #[Test]
    public function writesJson(): void
    {
        $data = self::json($this->command('info', ['file' => self::FAMILY, '--format' => 'json']));

        self::assertSame('5.5.1', $data['version']);
        self::assertSame(['INDI' => 2, 'SUBM' => 1, 'FAM' => 1], $data['records']);
        self::assertSame(['identifier' => 'Korin', 'version' => null, 'name' => null, 'program' => null], $data['source']);
    }

    #[Test]
    public function readsInTheEncodingGiven(): void
    {
        $path = $this->file('cp1251.ged', "0 HEAD\n1 GEDC\n2 VERS 5.5.1\n1 CHAR ANSI\n0 @I1@ INDI\n1 NAME \xC0\xED\xED\xE0\n0 TRLR\n");
        $data = self::json($this->command('info', ['file' => $path, '--format' => 'json', '--encoding' => 'windows-1250']));

        self::assertSame('Windows-1250', $data['encoding']);
    }

    #[Test]
    public function aFileThatCannotBeReadGivesTwo(): void
    {
        $missing = $this->command('info', ['file' => "{$this->directory}/missing.ged"]);
        $encoding = $this->command('info', ['file' => self::FAMILY, '--encoding' => 'KOI8-U']);
        $format = $this->command('info', ['file' => self::FAMILY, '--format' => 'xml']);

        self::assertSame(2, $missing->getStatusCode());
        self::assertStringContainsString('Cannot read', $missing->getDisplay());
        self::assertSame(2, $encoding->getStatusCode());
        self::assertStringContainsString('Unknown encoding KOI8-U', $encoding->getDisplay());
        self::assertSame(2, $format->getStatusCode());
    }

    #[Test]
    public function readsGedcomGedOfAnArchive(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ext-zip is not loaded');
        }
        $path = "{$this->directory}/family.gdz";
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('gedcom.ged', (string) file_get_contents(self::FAMILY));
        $zip->close();
        $empty = "{$this->directory}/empty.gdz";
        $zip->open($empty, \ZipArchive::CREATE);
        $zip->addFromString('readme.txt', 'no tree');
        $zip->close();

        self::assertSame(['INDI' => 2, 'SUBM' => 1, 'FAM' => 1], self::json($this->command('info', ['file' => $path, '--format' => 'json']))['records']);
        self::assertStringContainsString('has no gedcom.ged', $this->command('info', ['file' => $empty])->getDisplay());
    }
}
