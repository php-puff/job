<?php

/*
 * PHP Fiber Framework
 * https://github.com/pffphp/job
 * https://github.com/pffphp/job/issue
 * Copyright (c) PFF
 */

declare(strict_types=1);

namespace Puff\Job;

final readonly class ScheduledJob
{
    public CronExpression $expression;

    public function __construct(public JobInterface $job)
    {
        try {
            $attributes = (new \ReflectionClass($job))->getAttributes(Schedule::class);
            if (\count($attributes) !== 1) {
                throw new \InvalidArgumentException('Exactly one #[Schedule] attribute is required.');
            }
            $schedule = $attributes[0]->newInstance();
            $this->expression = new CronExpression($schedule->expression);
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException(
                \sprintf('Invalid job [%s]: %s', $job::class, $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    public function matches(\DateTimeImmutable $time): bool
    {
        return $this->expression->matches($time);
    }
}
