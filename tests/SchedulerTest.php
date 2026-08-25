<?php

/*
 * PHP Fiber Framework
 * https://github.com/pffphp/job
 * https://github.com/pffphp/job/issue
 * Copyright (c) PFF
 */

declare(strict_types=1);

namespace Puff\Job\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Puff\Async\DeferredFuture;
use Puff\Async\EventLoop;
use Puff\Job\JobInterface;
use Puff\Job\Schedule;
use Puff\Job\Scheduler;

final class SchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        EventLoop::reset();
    }

    public function testRunsOnlyDueJobs(): void
    {
        $due = new EverySecondTestJob();
        $notDue = new NotDueTestJob();
        $scheduler = new Scheduler([$due, $notDue], new NullLogger());

        $scheduler->tick(new \DateTimeImmutable('2026-08-10 10:15:20', new \DateTimeZone('UTC')));
        EventLoop::get()->tick();
        EventLoop::get()->tick();

        self::assertSame(1, $due->executions);
        self::assertSame(0, $notDue->executions);
    }

    public function testEvaluatesScheduleUsingProvidedTimeTimezone(): void
    {
        $job = new MidnightTestJob();
        $scheduler = new Scheduler([$job], new NullLogger());

        $scheduler->tick(new \DateTimeImmutable('2026-08-10 00:00:00', new \DateTimeZone('Asia/Shanghai')));
        EventLoop::get()->tick();

        self::assertSame(1, $job->executions);
    }

    public function testInvalidScheduleIdentifiesJobClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(InvalidScheduleTestJob::class);

        new Scheduler([new InvalidScheduleTestJob()], new NullLogger());
    }

    public function testMissingScheduleIdentifiesJobClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(MissingScheduleTestJob::class);

        new Scheduler([new MissingScheduleTestJob()], new NullLogger());
    }

    public function testPreventsOverlapAndReleasesJobAfterCompletion(): void
    {
        $gate = new DeferredFuture();
        $job = new EverySecondTestJob(static fn () => $gate->getFuture()->await());
        $scheduler = new Scheduler([$job], new NullLogger());
        $time = new \DateTimeImmutable('2026-08-10 10:15:20', new \DateTimeZone('UTC'));

        $scheduler->tick($time);
        EventLoop::get()->tick();
        $scheduler->tick($time);
        EventLoop::get()->tick();
        self::assertSame(1, $job->executions);
        self::assertTrue($scheduler->isRunning(EverySecondTestJob::class));

        $gate->complete();
        EventLoop::get()->tick();
        EventLoop::get()->tick();
        self::assertFalse($scheduler->isRunning(EverySecondTestJob::class));

        $scheduler->tick($time);
        EventLoop::get()->tick();
        self::assertSame(2, $job->executions);
    }

    public function testFailureDoesNotPreventNextExecution(): void
    {
        $job = new EverySecondTestJob(static fn () => throw new \RuntimeException('expected'));
        $scheduler = new Scheduler([$job], new NullLogger());
        $time = new \DateTimeImmutable('2026-08-10 10:15:20', new \DateTimeZone('UTC'));

        $scheduler->tick($time);
        EventLoop::get()->tick();
        EventLoop::get()->tick();
        $scheduler->tick($time->modify('+1 second'));
        EventLoop::get()->tick();

        self::assertSame(2, $job->executions);
    }

    public function testStopCancelsFutureTick(): void
    {
        $scheduler = new Scheduler([], new NullLogger());
        $scheduler->start();
        self::assertTrue(EventLoop::get()->hasWork());

        $scheduler->stop();

        self::assertFalse(EventLoop::get()->hasWork());
    }
}

abstract class TestJob implements JobInterface
{
    public int $executions = 0;

    public function __construct(private readonly ?\Closure $callback = null)
    {
    }

    public function run(): void
    {
        ++$this->executions;
        if ($this->callback !== null) {
            ($this->callback)();
        }
    }
}

#[Schedule('* * * * * *')]
final class EverySecondTestJob extends TestJob
{
}

#[Schedule('1 0 0 1 1 *')]
final class NotDueTestJob extends TestJob
{
}

#[Schedule('0 0 0 * * *')]
final class MidnightTestJob extends TestJob
{
}

#[Schedule('invalid')]
final class InvalidScheduleTestJob extends TestJob
{
}

final class MissingScheduleTestJob extends TestJob
{
}
