<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/job
 * https://github.com/php-puff/job/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Job;

use Puff\Console\CommandProvider;
use Puff\Console\Contract;
use Puff\Console\GenerateCommand;
use Puff\Console\Generator;

final class ConsoleProvider implements CommandProvider
{
    /** @return iterable<Contract> */
    public function commands(string $root): iterable
    {
        yield new GenerateCommand('job', 'Job', \dirname(__DIR__) . '/stub/job.stub', new Generator($root));
    }
}
