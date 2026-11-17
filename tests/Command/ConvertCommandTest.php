<?php

declare(strict_types=1);

namespace Korin\Gedcom\Cli\Tests\Command;

use Korin\Gedcom\Cli\Tests\CommandTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ConvertCommandTest extends CommandTestCase
{
    private const SEX_X = "0 HEAD\n1 GEDC\n2 VERS 7.0\n0 @I1@ INDI\n1 SEX X\n0 TRLR\n";

    #[Test]
    public function convertsAndReportsNothingLost(): void
    {
        $output = "{$this->directory}/family-7.ged";
        $tester = $this->command('convert', ['input' => self::FAMILY, 'output' => $output, '--to' => '7.0']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('nothing lost', $tester->getDisplay());
        self::assertStringContainsString("1 GEDC\n2 VERS 7.0\n", (string) file_get_contents($output));
        self::assertSame(['family-7.ged'], $this->files(), 'No temporary file is left');
    }

    #[Test]
    public function reportsLosses(): void
    {
        $input = $this->file('x.ged', self::SEX_X);
        $output = "{$this->directory}/x-551.ged";
        $losses = "{$this->directory}/losses.json";
        $tester = $this->command('convert', ['input' => $input, 'output' => $output, '--to' => '5.5.1', '--losses' => $losses]);
        $json = self::json($this->command('convert', ['input' => $input, 'output' => $output, '--to' => '5.5.1', '--format' => 'json']));

        self::assertSame(0, $tester->getStatusCode());
        self::assertMatchesRegularExpression('/^KL\d{4} warning line 5 \(@I1@\/SEX\)/m', $tester->getDisplay());
        self::assertStringContainsString('Converted to GEDCOM 5.5.1 in ' . $output . ': 1 loss', $tester->getDisplay());
        $report = json_decode((string) file_get_contents($losses), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        self::assertSame($report, $json['losses']);
        self::assertSame('7.0', $report['from']);
        self::assertSame(['@I1@/SEX'], self::column($report, 'entries', 'path'));
    }

    #[Test]
    public function policyFailStopsAndLeavesNoOutput(): void
    {
        $input = $this->file('x.ged', self::SEX_X);
        $output = "{$this->directory}/x-551.ged";
        $tester = $this->command('convert', ['input' => $input, 'output' => $output, '--to' => '5.5.1', '--policy' => 'fail']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertSame(['x.ged'], $this->files());
    }

    #[Test]
    public function wrongArgumentsGiveTwo(): void
    {
        $output = "{$this->directory}/out.ged";
        $copy = $this->file('copy.ged', (string) file_get_contents(self::FAMILY));

        foreach ([
            [['--to' => '6.0'], '--to is one of 5.5, 5.5.1, 5.5.5, 7.0'],
            [['--to' => '4.0'], '--to is one of'],
            [[], '--to is missing'],
            [['--to' => '7.0', '--policy' => 'keep'], '--policy is extension, drop or fail'],
            [['--to' => '7.0', 'output' => "{$this->directory}/out.gdz"], 'Writing .gdz is not supported yet'],
            [['--to' => '7.0', 'input' => $copy, 'output' => $copy], 'The output is the input file'],
        ] as [$options, $message]) {
            $tester = $this->command('convert', $options + ['input' => self::FAMILY, 'output' => $output]);
            self::assertSame(2, $tester->getStatusCode(), $message);
            self::assertStringContainsString($message, $tester->getDisplay());
        }
        self::assertFileDoesNotExist($output);
    }
}
