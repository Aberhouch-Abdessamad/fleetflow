<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Resolves dashboard/report presets in the company's timezone; stores UTC bounds. */
final class DateRange
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $preset,
        public readonly string $timezone,
    ) {}

    public static function fromRequest(Request $request, string $timezone, string $default = 'last_30_days'): self
    {
        $preset = (string) $request->query('range', $default);
        $now = CarbonImmutable::now($timezone);

        [$from, $to] = match ($preset) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'last_7_days' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'this_month' => [$now->startOfMonth(), $now->endOfDay()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'last_90_days' => [$now->subDays(89)->startOfDay(), $now->endOfDay()],
            'this_year' => [$now->startOfYear(), $now->endOfDay()],
            'custom' => [
                CarbonImmutable::parse((string) $request->query('from', $now->subDays(29)->toDateString()), $timezone)->startOfDay(),
                CarbonImmutable::parse((string) $request->query('to', $now->toDateString()), $timezone)->endOfDay(),
            ],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };
        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }
        if ($from->diffInDays($to) > 731) {
            $from = $to->subDays(731)->startOfDay();
        }

        return new self($from->utc(), $to->utc(), $preset === 'custom' || in_array($preset, ['today', 'yesterday', 'last_7_days', 'this_month', 'last_month', 'last_90_days', 'this_year'], true) ? $preset : 'last_30_days', $timezone);
    }

    public function bucket(): string
    {
        return $this->from->diffInDays($this->to) > 92 ? 'month' : 'day';
    }

    /** @return list<string> bucket keys covering the range */
    public function keys(): array
    {
        $keys = [];
        $c = $this->from->setTimezone($this->timezone);
        $end = $this->to->setTimezone($this->timezone);
        $fmt = $this->bucket() === 'month' ? 'Y-m' : 'Y-m-d';
        while ($c->lte($end)) {
            $keys[] = $c->format($fmt);
            $c = $this->bucket() === 'month' ? $c->startOfMonth()->addMonth() : $c->addDay();
        }

        return array_values(array_unique($keys));
    }

    public function keyFor(\DateTimeInterface $dt): string
    {
        return CarbonImmutable::instance($dt)->setTimezone($this->timezone)->format($this->bucket() === 'month' ? 'Y-m' : 'Y-m-d');
    }

    public function toArray(): array
    {
        return ['preset' => $this->preset, 'from' => $this->from->toIso8601String(), 'to' => $this->to->toIso8601String(), 'timezone' => $this->timezone, 'bucket' => $this->bucket()];
    }
}
