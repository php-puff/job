<?php

/*
 * PHP Fiber Framework
 * https://github.com/php-puff/job
 * https://github.com/php-puff/job/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Job\Tests;

use PHPUnit\Framework\TestCase;
use Puff\Application\Application;
use Puff\Application\Exception;
use Puff\Config\Config;
use Puff\Di\Container;
use Puff\Job\JobInterface;
use Puff\Job\Schedule;
use Puff\Job\ServiceProvider;

final class ServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Exception::restore();
    }

    public function testLoadsFlatJobConfigurationThroughContainer(): void
    {
        $job = new WorkerTestJob();
        $config = new Config(['job' => [WorkerTestJob::class]]);
        $container = new Container();
        $container->instance(Config::class, $config);
        $container->instance(WorkerTestJob::class, $job);
        $app = new Application($container);
        $worker = new ServiceProvider();

        $worker->boot($app);

        self::assertSame(1, $worker->workers());
        self::assertSame(1, $worker->info()['jobs']);
        self::assertSame($job, $worker->scheduler()->jobs()[0]->job);
    }

    public function testRejectsDuplicateJobs(): void
    {
        $app = $this->application([WorkerTestJob::class, WorkerTestJob::class]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('configured more than once');
        (new ServiceProvider())->boot($app);
    }

    public function testBootDoesNotAccumulateJobs(): void
    {
        $app = $this->application([WorkerTestJob::class]);
        $worker = new ServiceProvider();

        $worker->boot($app);
        $worker->boot($app);

        self::assertSame(1, $worker->info()['jobs']);
        self::assertCount(1, $worker->scheduler()->jobs());
    }

    public function testRejectsClassesThatDoNotImplementContract(): void
    {
        $app = $this->application([\stdClass::class]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(JobInterface::class);
        (new ServiceProvider())->boot($app);
    }

    public function testComposerManifestDiscoversServiceProvider(): void
    {
        $manifest = \json_decode((string) \file_get_contents(\dirname(__DIR__) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([ServiceProvider::class], $manifest['extra']['puff']['apps']);
    }

    /** @param list<class-string> $jobs */
    private function application(array $jobs): Application
    {
        $container = new Container();
        $container->instance(Config::class, new Config(['job' => []]));
        $app = new Application($container);
        $container->instance(Config::class, new Config(['job' => $jobs]));
        return $app;
    }
}

#[Schedule('* * * * * *')]
final class WorkerTestJob implements JobInterface
{
    public function run(): void
    {
    }
}
