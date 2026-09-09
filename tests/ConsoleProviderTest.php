<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/job
 * https://github.com/php-puff/job/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Job\Tests;

use PHPUnit\Framework\TestCase;
use Puff\Job\ConsoleProvider;

final class ConsoleProviderTest extends TestCase
{
    public function testRegistersJobGenerator(): void
    {
        $commands = \iterator_to_array((new ConsoleProvider())->commands(__DIR__));

        self::assertCount(1, $commands);
        self::assertSame('job', $commands[0]->name());
    }
}
