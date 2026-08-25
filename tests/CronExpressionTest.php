<?php

/*
 * PHP Fiber Framework
 * https://github.com/pffphp/job
 * https://github.com/pffphp/job/issue
 * Copyright (c) PFF
 */

declare(strict_types=1);

namespace Puff\Job\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Puff\Job\CronExpression;

final class CronExpressionTest extends TestCase
{
    /** @return iterable<string, array{string, string, bool}> */
    public static function expressions(): iterable
    {
        yield 'exact match' => ['30 15 10 10 8 *', '2026-08-10 10:15:30', true];
        yield 'exact mismatch' => ['31 15 10 10 8 *', '2026-08-10 10:15:30', false];
        yield 'list' => ['0,30 * * * * *', '2026-08-10 10:15:30', true];
        yield 'range' => ['10-20 * * * * *', '2026-08-10 10:15:15', true];
        yield 'step' => ['*/5 * * * * *', '2026-08-10 10:15:20', true];
        yield 'legacy step' => ['/5 * * * * *', '2026-08-10 10:15:20', true];
        yield 'sunday zero' => ['0 0 0 * * 0', '2026-08-09 00:00:00', true];
        yield 'sunday seven' => ['0 0 0 * * 7', '2026-08-09 00:00:00', true];
        yield 'day fields use or' => ['0 0 0 11 * 1', '2026-08-10 00:00:00', true];
    }

    #[DataProvider('expressions')]
    public function testMatchesExpressions(string $expression, string $time, bool $expected): void
    {
        $cron = new CronExpression($expression);

        self::assertSame($expected, $cron->matches(new \DateTimeImmutable($time, new \DateTimeZone('UTC'))));
    }

    #[DataProvider('invalidExpressions')]
    public function testRejectsInvalidExpressions(string $expression): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CronExpression($expression);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidExpressions(): iterable
    {
        yield 'five fields' => ['* * * * *'];
        yield 'out of range' => ['60 * * * * *'];
        yield 'backwards range' => ['10-5 * * * * *'];
        yield 'zero step' => ['*/0 * * * * *'];
        yield 'invalid token' => ['nope * * * * *'];
    }
}
