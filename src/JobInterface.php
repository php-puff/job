<?php

/*
 * PHP Fiber Framework
 * https://github.com/pffphp/job
 * https://github.com/pffphp/job/issue
 * Copyright (c) PFF
 */

declare(strict_types=1);

namespace Puff\Job;

interface JobInterface
{
    public function run(): void;
}
