<?php

/*
 * PHP Fiber Framework
 * https://github.com/pffphp/job
 * https://github.com/pffphp/job/issue
 * Copyright (c) PFF
 */

declare(strict_types=1);

namespace Puff\Job;

use Psr\Log\LoggerInterface;
use Puff\Async\EventLoop;
use Puff\Async\Runtime;

final class Scheduler
{
    /** @var list<ScheduledJob> */
    private array $jobs;

    /** @var array<class-string, true> */
    private array $running = [];

    private ?string $timer = null;

    private bool $started = false;

    /** @param list<JobInterface> $jobs */
    public function __construct(array $jobs, private readonly LoggerInterface $logger)
    {
        $this->jobs = \array_map(static fn (JobInterface $job): ScheduledJob => new ScheduledJob($job), $jobs);
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;
        $this->scheduleNextTick();
    }

    public function stop(): void
    {
        $this->started = false;
        if ($this->timer !== null) {
            EventLoop::get()->cancel($this->timer);
            $this->timer = null;
        }
    }

    public function tick(?\DateTimeImmutable $time = null): void
    {
        $time ??= new \DateTimeImmutable('now');
        foreach ($this->jobs as $scheduledJob) {
            if ($scheduledJob->matches($time)) {
                $this->run($scheduledJob->job);
            }
        }
    }

    /** @return list<ScheduledJob> */
    public function jobs(): array
    {
        return $this->jobs;
    }

    public function isRunning(string $job): bool
    {
        return isset($this->running[$job]);
    }

    private function scheduleNextTick(): void
    {
        if (!$this->started) {
            return;
        }

        $now = \microtime(true);
        $delay = \max(0.001, \ceil($now) - $now);
        $this->timer = EventLoop::get()->delay($delay, function (): void {
            $this->timer = null;
            if (!$this->started) {
                return;
            }
            $this->tick();
            $this->scheduleNextTick();
        });
    }

    private function run(JobInterface $job): void
    {
        $class = $job::class;
        if (isset($this->running[$class])) {
            $this->logger->warning('Scheduled job skipped because it is already running.', ['job' => $class]);
            return;
        }

        $this->running[$class] = true;
        $startedAt = \hrtime(true);
        $this->logger->info('Scheduled job started.', ['job' => $class]);
        $task = Runtime::async(static function () use ($job): void {
            $job->run();
        });
        $task->future()->onComplete(function (?\Throwable $error) use ($class, $startedAt): void {
            unset($this->running[$class]);
            $context = [
                'job' => $class,
                'elapsed_ms' => \round((\hrtime(true) - $startedAt) / 1_000_000, 2),
            ];
            if ($error !== null) {
                $this->logger->error('Scheduled job failed.', [...$context, 'exception' => $error]);
                return;
            }
            $this->logger->info('Scheduled job completed.', $context);
        });
        $task->ignore();
    }
}
