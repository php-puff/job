<?php

/*
 * PHP Fiber Framework
 * https://github.com/php-puff/job
 * https://github.com/php-puff/job/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Job;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Puff\Application\Application;
use Puff\Application\Contract;
use Puff\Di\Container;

final class ServiceProvider implements Contract
{
    private const CONFIG_KEY = 'job';

    private ?Scheduler $scheduler = null;

    /** @var list<JobInterface> */
    private array $jobs = [];

    public function name(): string
    {
        return 'Jobs';
    }

    public function boot(Application $app): void
    {
        $container = $app->container();
        $config = $container->make('config');
        if (!\is_object($config) || !\method_exists($config, 'get')) {
            throw new \InvalidArgumentException('The config service must provide a get() method.');
        }
        $classes = $config->get(self::CONFIG_KEY, []);
        if (!\is_array($classes)) {
            throw new \InvalidArgumentException('Job configuration must be a list of job class names.');
        }

        $jobs = [];
        $seen = [];
        foreach ($classes as $class) {
            if (!\is_string($class) || $class === '') {
                throw new \InvalidArgumentException('Job configuration entries must be non-empty class names.');
            }
            if (isset($seen[$class])) {
                throw new \InvalidArgumentException("Job [{$class}] is configured more than once.");
            }
            $seen[$class] = true;
            $job = $container->make($class);
            if (!$job instanceof JobInterface) {
                throw new \InvalidArgumentException("Job [{$class}] must implement " . JobInterface::class . '.');
            }
            $jobs[] = $job;
        }

        $this->scheduler?->stop();
        $this->jobs = $jobs;
        $this->scheduler = new Scheduler($jobs, $this->logger($container));
    }

    public function start(): void
    {
        $this->scheduler()->start();
    }

    public function stop(): void
    {
        $this->scheduler?->stop();
    }

    public function workers(): int
    {
        return 1;
    }

    /** @return array{name: string, addr: string, workers: int, jobs: int} */
    public function info(): array
    {
        return [
            'name' => $this->name(),
            'addr' => \sprintf('%d scheduled job(s)', \count($this->jobs)),
            'workers' => $this->workers(),
            'jobs' => \count($this->jobs),
        ];
    }

    public function scheduler(): Scheduler
    {
        return $this->scheduler ?? throw new \LogicException('Job worker has not been booted.');
    }

    private function logger(Container $container): LoggerInterface
    {
        foreach ([LoggerInterface::class, 'log'] as $service) {
            if (!$container->bound($service)) {
                continue;
            }
            $logger = $container->make($service);
            if ($logger instanceof LoggerInterface) {
                return $logger;
            }
        }

        return new NullLogger();
    }
}
