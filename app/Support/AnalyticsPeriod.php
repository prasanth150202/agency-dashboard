<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The reporting window a page is showing, resolved from `?range=` (plus
 * `from`/`to` for a custom range). One definition shared by every widget
 * on a page, so no widget can silently show a different window.
 *
 * Older numeric values (7, 30, 90, 365) are accepted as aliases so
 * existing links and bookmarks keep working.
 */
final class AnalyticsPeriod
{
    public const OPTIONS = [
        '7d' => '7 Days',
        '30d' => '30 Days',
        '3m' => '3 Months',
        '6m' => '6 Months',
        '12m' => '12 Months',
        'ytd' => 'This Year',
        'all' => 'All Time',
        'custom' => 'Custom',
    ];

    private const ALIASES = ['7' => '7d', '30' => '30d', '90' => '3m', '180' => '6m', '365' => '12m'];

    private const MAX_CUSTOM_DAYS = 731;

    private function __construct(
        public readonly string $key,
        public readonly ?Carbon $from,
        public readonly Carbon $to,
    ) {}

    /** @param  list<string>  $allowed  keys of OPTIONS this page offers */
    public static function fromRequest(Request $request, string $default = '30d', array $allowed = ['7d', '30d', '3m', '6m', 'ytd', 'custom']): self
    {
        $key = (string) $request->query('range', $default);
        $key = self::ALIASES[$key] ?? $key;

        if (! in_array($key, $allowed, true)) {
            $key = $default;
        }

        if ($key === 'custom') {
            $custom = self::custom($request->query('from'), $request->query('to'));

            return $custom ?? self::make($default === 'custom' ? '30d' : $default);
        }

        return self::make($key);
    }

    public static function make(string $key): self
    {
        $to = now()->endOfDay();

        $from = match ($key) {
            '7d' => now()->subDays(6)->startOfDay(),
            '30d' => now()->subDays(29)->startOfDay(),
            '3m' => now()->subDays(89)->startOfDay(),
            '6m' => now()->subMonthsNoOverflow(6)->addDay()->startOfDay(),
            '12m' => now()->subDays(364)->startOfDay(),
            'ytd' => now()->startOfYear(),
            'all' => null,
            default => throw new \InvalidArgumentException("Unknown period {$key}"),
        };

        return new self($key, $from, $to);
    }

    private static function custom(mixed $from, mixed $to): ?self
    {
        try {
            $start = Carbon::createFromFormat('!Y-m-d', (string) $from)->startOfDay();
            $end = Carbon::createFromFormat('!Y-m-d', (string) $to)->endOfDay();
        } catch (\Throwable) {
            return null;
        }

        $end = $end->min(now()->endOfDay());

        if ($start->greaterThan($end) || $start->diffInDays($end) > self::MAX_CUSTOM_DAYS) {
            return null;
        }

        return new self('custom', $start, $end);
    }

    /** The same-length window immediately before this one, or null when there is none (all time). */
    public function previous(): ?self
    {
        if ($this->from === null) {
            return null;
        }

        // Whole calendar days covered, minus one: a 30-day window compares to the 30 days before it.
        $spanDays = (int) $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay());
        $prevTo = $this->from->copy()->subDay()->endOfDay();

        return new self($this->key, $prevTo->copy()->startOfDay()->subDays($spanDays), $prevTo);
    }

    public function label(): string
    {
        return match ($this->key) {
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
            '3m' => 'Last 90 days',
            '6m' => 'Last 6 months',
            '12m' => 'Last 12 months',
            'ytd' => 'This year',
            'all' => 'All time',
            default => $this->from->format('M j, Y').' – '.$this->to->format('M j, Y'),
        };
    }

    public function comparisonLabel(): string
    {
        return $this->key === 'custom' ? 'vs previous period' : 'vs previous '.lcfirst(str_replace('Last ', '', $this->label()));
    }

    /** Query-string parameters that reproduce this period on another page. @return array<string, string> */
    public function query(): array
    {
        return $this->key === 'custom'
            ? ['range' => 'custom', 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString()]
            : ['range' => $this->key];
    }

    /**
     * Chart buckets across the period: daily up to ~3 months, weekly up to
     * ~13 months, monthly beyond. `$earliest` anchors an all-time period.
     *
     * @return list<array{key: string, label: string, start: Carbon, end: Carbon}>
     */
    public function buckets(?Carbon $earliest = null, ?string $unit = null): array
    {
        $from = ($this->from ?? $earliest ?? now()->subDays(29))->copy()->startOfDay();
        $days = (int) $from->diffInDays($this->to);
        $unit ??= $days <= 93 ? 'day' : ($days <= 400 ? 'week' : 'month');

        $cursor = match ($unit) {
            'day' => $from->copy(),
            'week' => $from->copy()->startOfWeek(),
            'month' => $from->copy()->startOfMonth(),
        };

        $buckets = [];

        while ($cursor->lte($this->to)) {
            $end = match ($unit) {
                'day' => $cursor->copy()->endOfDay(),
                'week' => $cursor->copy()->endOfWeek(),
                'month' => $cursor->copy()->endOfMonth(),
            };

            $buckets[] = [
                'key' => $cursor->toDateString(),
                'label' => match ($unit) {
                    'day' => $cursor->format('M j'),
                    'week' => 'Week of '.$cursor->max($from)->format('M j'),
                    'month' => $cursor->format('M Y'),
                },
                'start' => $cursor->copy()->max($from),
                'end' => $end->min($this->to),
            ];

            $cursor = match ($unit) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonthNoOverflow(),
            };
        }

        return $buckets;
    }

    /**
     * Real percentage change, or null when it can't honestly be computed
     * (no previous window, or nothing in it to compare against).
     */
    public static function change(int|float $current, int|float|null $previous): ?float
    {
        if ($previous === null || $previous == 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
