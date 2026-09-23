<?php

namespace App\Services\Analytics;

use App\Support\Currency;
use App\Support\DecimalMoney;

/**
 * Turns AgencyAnalytics::series() into the payload the `trendChart` Alpine
 * component renders, plus a plain-text summary and table rows so the same
 * numbers are available without the chart. All money is formatted here,
 * per currency, from integer cents — the browser never does money math.
 */
final class TrendChart
{
    private const SERIES = [
        'revenue' => ['label' => 'Revenue', 'kind' => 'bar', 'axis' => 'money', 'color' => '#1b1b21'],
        'commission' => ['label' => 'Commission', 'kind' => 'line', 'axis' => 'money', 'color' => '#059669'],
        'paid' => ['label' => 'Paid out', 'kind' => 'bar', 'axis' => 'money', 'color' => '#059669'],
        'clicks' => ['label' => 'Clicks', 'kind' => 'line', 'axis' => 'count', 'color' => '#8d8d9a'],
        'leads' => ['label' => 'New leads', 'kind' => 'line', 'axis' => 'count', 'color' => '#2563eb'],
        'installed' => ['label' => 'Installed', 'kind' => 'line', 'axis' => 'count', 'color' => '#0ea5e9'],
    ];

    private const MONEY = ['revenue', 'commission', 'paid'];

    /**
     * @param  array{buckets: list<array<string, mixed>>, currencies: list<string>}  $series
     * @param  list<string>  $metrics
     * @return array{config: array<string, mixed>, empty: bool, summary: string, table: array<string, mixed>}
     */
    public static function build(array $series, array $metrics, ?string $preferredCurrency = null): array
    {
        $buckets = $series['buckets'];
        $moneyMetrics = array_values(array_intersect($metrics, self::MONEY));
        $countMetrics = array_values(array_diff($metrics, self::MONEY));

        $currencies = $moneyMetrics ? $series['currencies'] : [];
        $currencies = $currencies ?: ($moneyMetrics && $preferredCurrency ? [$preferredCurrency] : $currencies);

        if ($preferredCurrency && in_array($preferredCurrency, $currencies, true)) {
            $currencies = array_values(array_unique([$preferredCurrency, ...$currencies]));
        }

        $chartSeries = [];

        foreach ($metrics as $metric) {
            $meta = self::SERIES[$metric];

            if (in_array($metric, self::MONEY, true)) {
                $values = [];
                foreach ($currencies as $currency) {
                    $values[$currency] = array_map(fn (array $b) => DecimalMoney::format($b[$metric][$currency] ?? 0) + 0, $buckets);
                }
            } else {
                $values = array_map(fn (array $b) => (int) $b[$metric], $buckets);
            }

            $chartSeries[] = ['key' => $metric] + $meta + ['values' => $values];
        }

        $tooltips = [];
        foreach ($currencies ?: ['_'] as $currency) {
            $tooltips[$currency] = array_map(fn (array $b) => self::tooltipLines($b, $metrics, $currency === '_' ? null : $currency), $buckets);
        }

        $empty = true;
        foreach ($buckets as $bucket) {
            foreach ($countMetrics as $metric) {
                if ($bucket[$metric] > 0) {
                    $empty = false;
                }
            }
            foreach ($moneyMetrics as $metric) {
                if (array_filter($bucket[$metric])) {
                    $empty = false;
                }
            }
        }

        return [
            'config' => [
                'labels' => array_column($buckets, 'label'),
                'series' => $chartSeries,
                'currencies' => $currencies,
                'tooltips' => $tooltips,
            ],
            'empty' => $empty,
            'summary' => self::summary($buckets, $metrics, $currencies),
            'table' => [
                'metrics' => array_map(fn (string $m) => self::SERIES[$m]['label'], $metrics),
                'rows' => array_map(fn (array $b) => [
                    'label' => $b['label'],
                    'cells' => array_map(fn (string $m) => in_array($m, self::MONEY, true)
                        ? self::money($b[$m])
                        : number_format($b[$m]), $metrics),
                ], array_values(array_filter($buckets, fn (array $b) => self::bucketHasData($b, $metrics)))),
            ],
        ];
    }

    /** @param  array<string, int>|null  $byCurrency */
    public static function money(?array $byCurrency): string
    {
        if (! $byCurrency) {
            return '—';
        }

        ksort($byCurrency);

        return collect($byCurrency)
            ->map(fn (int $cents, string $currency) => Currency::format(DecimalMoney::format($cents), $currency))
            ->implode(' + ');
    }

    /** @return list<string> Only metrics that actually have a value in this bucket. */
    private static function tooltipLines(array $bucket, array $metrics, ?string $currency): array
    {
        $lines = [];

        foreach ($metrics as $metric) {
            $label = self::SERIES[$metric]['label'];

            if (in_array($metric, self::MONEY, true)) {
                $cents = $currency ? ($bucket[$metric][$currency] ?? 0) : 0;
                if ($cents !== 0) {
                    $lines[] = $label.': '.Currency::format(DecimalMoney::format($cents), $currency);
                }
            } elseif ($bucket[$metric] > 0) {
                $lines[] = $label.': '.number_format($bucket[$metric]);
            }
        }

        return $lines ?: ['No activity'];
    }

    private static function bucketHasData(array $bucket, array $metrics): bool
    {
        foreach ($metrics as $metric) {
            if (in_array($metric, self::MONEY, true) ? array_filter($bucket[$metric]) : $bucket[$metric] > 0) {
                return true;
            }
        }

        return false;
    }

    private static function summary(array $buckets, array $metrics, array $currencies): string
    {
        $parts = [];

        foreach ($metrics as $metric) {
            $label = strtolower(self::SERIES[$metric]['label']);

            if (in_array($metric, self::MONEY, true)) {
                foreach ($currencies as $currency) {
                    $values = array_map(fn (array $b) => $b[$metric][$currency] ?? 0, $buckets);
                    $total = array_sum($values);

                    if ($total > 0) {
                        $peak = array_search(max($values), $values, true);
                        $withData = count(array_filter($values));
                        $parts[] = ucfirst($label).' '.Currency::format(DecimalMoney::format($total), $currency)
                            .($withData > 1 ? ' (highest '.$buckets[$peak]['label'].')' : '');
                    }
                }
            } else {
                $total = array_sum(array_column($buckets, $metric));
                if ($total > 0) {
                    $parts[] = number_format($total).' '.$label;
                }
            }
        }

        return $parts ? implode('; ', $parts).'.' : 'No activity in this period.';
    }
}
