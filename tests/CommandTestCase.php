<?php

declare(strict_types=1);

namespace Korin\Gedcom\Cli\Tests;

use Korin\Gedcom\Cli\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

abstract class CommandTestCase extends TestCase
{
    protected const FAMILY = __DIR__ . '/Fixtures/family.ged';

    protected string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/korin-cli-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach ($this->files() as $file) {
            unlink("{$this->directory}/{$file}");
        }
        rmdir($this->directory);
    }

    /** @param array<string, mixed> $input */
    protected function command(string $command, array $input, int $verbosity = OutputInterface::VERBOSITY_NORMAL): CommandTester
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new CommandTester($application->find($command));
        $tester->execute($input, ['decorated' => false, 'verbosity' => $verbosity]);

        return $tester;
    }

    protected function file(string $name, string $bytes): string
    {
        $path = "{$this->directory}/{$name}";
        file_put_contents($path, $bytes);

        return $path;
    }

    /** @return array<mixed> */
    protected static function json(CommandTester $tester): array
    {
        $data = json_decode($tester->getDisplay(true), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /** @return list<string> the files in the temporary directory */
    protected function files(): array
    {
        $files = scandir($this->directory);
        self::assertNotFalse($files);

        return array_values(array_diff($files, ['.', '..']));
    }

    /**
     * One field of each object of a list in the JSON output: the codes of its diagnostics.
     *
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    protected static function column(array $data, string $list, string $field): array
    {
        self::assertIsArray($data[$list] ?? null);
        $values = [];
        foreach ($data[$list] as $item) {
            self::assertIsArray($item);
            self::assertIsString($item[$field] ?? null);
            $values[] = $item[$field];
        }

        return $values;
    }
}
