<?php

/*
 * PHP Fiber Framework
 * https://github.com/pffphp/job
 * https://github.com/pffphp/job/issue
 * Copyright (c) PFF
 */

declare(strict_types=1);

namespace Puff\Job;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Schedule
{
    public function __construct(public string $expression)
    {
        if (\trim($expression) === '') {
            throw new \InvalidArgumentException('Schedule expression cannot be empty.');
        }
    }
}
