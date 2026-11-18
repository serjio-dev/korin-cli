<?php

declare(strict_types=1);

namespace Korin\Gedcom\Cli;

use Korin\Gedcom\Cli\Command\ConvertCommand;
use Korin\Gedcom\Cli\Command\DiffCommand;
use Korin\Gedcom\Cli\Command\InfoCommand;
use Korin\Gedcom\Cli\Command\ValidateCommand;
use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    public const VERSION = '0.1.0';

    public function __construct()
    {
        parent::__construct('korin', self::VERSION);
        $this->addCommands([new InfoCommand(), new ValidateCommand(), new ConvertCommand(), new DiffCommand()]);
    }
}
