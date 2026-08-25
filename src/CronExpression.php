<?php

/*
 * PHP Fiber Framework
 * https://github.com/pffphp/job
 * https://github.com/pffphp/job/issue
 * Copyright (c) PFF
 */

declare(strict_types=1);

namespace Puff\Job;

final class CronExpression
{
    private const RANGES = [
        [0, 59],
        [0, 59],
        [0, 23],
        [1, 31],
        [1, 12],
        [0, 7],
    ];

    /** @var list<array<int, true>> */
    private array $fields = [];

    /** @var list<bool> */
    private array $wildcards = [];

    public function __construct(private readonly string $expression)
    {
        $parts = \preg_split('/\s+/', \trim($expression));
        if ($parts === false || \count($parts) !== 6) {
            throw new \InvalidArgumentException("Cron expression [{$expression}] must contain six fields.");
        }

        $wildcards = [];
        foreach ($parts as $index => $part) {
            [$minimum, $maximum] = self::RANGES[$index];
            $wildcards[] = $part === '*' || \preg_match('#^(?:\*|)/\d+$#', $part) === 1;
            $this->fields[$index] = $this->parseField($part, $minimum, $maximum, $index === 5);
        }
        $this->wildcards = $wildcards;
    }

    public function expression(): string
    {
        return $this->expression;
    }

    public function matches(\DateTimeInterface $time): bool
    {
        $values = [
            (int) $time->format('s'),
            (int) $time->format('i'),
            (int) $time->format('G'),
            (int) $time->format('j'),
            (int) $time->format('n'),
            (int) $time->format('w'),
        ];

        foreach ([0, 1, 2, 4] as $index) {
            if (!isset($this->fields[$index][$values[$index]])) {
                return false;
            }
        }

        $dayOfMonth = isset($this->fields[3][$values[3]]);
        $dayOfWeek = isset($this->fields[5][$values[5]]);
        if (!$this->wildcards[3] && !$this->wildcards[5]) {
            return $dayOfMonth || $dayOfWeek;
        }

        return $dayOfMonth && $dayOfWeek;
    }

    /** @return array<int, true> */
    private function parseField(string $field, int $minimum, int $maximum, bool $weekday): array
    {
        if ($field === '') {
            throw new \InvalidArgumentException("Cron expression [{$this->expression}] contains an empty field.");
        }

        $values = [];
        foreach (\explode(',', $field) as $segment) {
            if ($segment === '') {
                throw new \InvalidArgumentException("Invalid cron field [{$field}].");
            }

            [$base, $step] = $this->splitStep($segment);
            if ($base === '' || $base === '*') {
                $start = $minimum;
                $end = $maximum;
            } elseif (\str_contains($base, '-')) {
                $range = \explode('-', $base, 2);
                if (!$this->isInteger($range[0]) || !$this->isInteger($range[1])) {
                    throw new \InvalidArgumentException("Invalid cron range [{$segment}].");
                }
                $start = (int) $range[0];
                $end = (int) $range[1];
            } elseif ($this->isInteger($base)) {
                $start = (int) $base;
                $end = $step === 1 ? $start : $maximum;
            } else {
                throw new \InvalidArgumentException("Invalid cron value [{$segment}].");
            }

            if ($start < $minimum || $end > $maximum || $start > $end) {
                throw new \InvalidArgumentException("Cron value [{$segment}] is outside {$minimum}-{$maximum}.");
            }

            for ($value = $start; $value <= $end; $value += $step) {
                $values[$weekday && $value === 7 ? 0 : $value] = true;
            }
        }

        return $values;
    }

    /** @return array{string, int} */
    private function splitStep(string $segment): array
    {
        if (!\str_contains($segment, '/')) {
            return [$segment, 1];
        }

        $parts = \explode('/', $segment);
        if (\count($parts) !== 2 || !$this->isInteger($parts[1]) || (int) $parts[1] < 1) {
            throw new \InvalidArgumentException("Invalid cron step [{$segment}].");
        }

        return [$parts[0], (int) $parts[1]];
    }

    private function isInteger(string $value): bool
    {
        return \preg_match('/^\d+$/D', $value) === 1;
    }
}
