# Puff Job

Fiber-based, second-level cron scheduling for PHP Unison Fiber Framework. The package is discovered automatically and runs in one dedicated worker process.

## Installation

```bash
composer require puff/job:dev-main
```

Register job classes directly in the application configuration:

```php
'job' => [
    App\Job\CleanupJob::class,
],
```

Each class is resolved through the shared dependency injection container and must implement `JobInterface`:

```php
use Puff\Job\JobInterface;
use Puff\Job\Schedule;

#[Schedule('0 */5 * * * *')]
final class CleanupJob implements JobInterface
{
    public function run(): void
    {
        // Perform scheduled work.
    }
}
```

## Cron syntax

Expressions contain six fields:

```text
second minute hour day-of-month month day-of-week
```

Fields support `*`, comma-separated values, ranges, and steps. Both `*/5` and the legacy `/5` shorthand mean every five units. Sunday may be written as `0` or `7`. When both day-of-month and day-of-week are restricted, either field may match, following traditional cron behavior.

Every job class must declare exactly one `#[Schedule('...')]` attribute. Expressions are validated when the worker boots. Missing or invalid attributes stop startup with the affected job class in the error message. Cron matching uses the application's PHP default timezone.

## Runtime behavior

- Jobs run in separate Fibers, so one job does not block other due jobs.
- A job is skipped when a previous invocation of the same class is still running.
- Exceptions are logged and isolated; the job is eligible again at its next scheduled time.
- Missed times are not replayed after a pause or restart.
- The component always uses one worker to prevent duplicate local execution. Cross-host locking, retries, persistent history, and forced timeouts are intentionally outside this version.
- A bound PSR-3 logger is used when available. Otherwise the scheduler uses `NullLogger`.
