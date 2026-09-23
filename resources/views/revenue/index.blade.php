@php
    use App\Services\Analytics\TrendChart;
    use App\Services\Referral\ReferralReporting as Money;
    use App\Support\Currency;

    $breakdowns = ['By type' => $byType, 'By store' => $byStore, 'By referral link' => $byLink];
@endphp
<x-app-layout title="Revenue">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Revenue</h2>
            <p class="mt-1 text-sm text-ink-500">Verified BRIX billing earned by the stores you referred.</p>
        </div>
        <x-period-filter :period="$period" :options="['7d', '30d', '3m', '6m', '12m', 'ytd', 'all', 'custom']" :keep="['type' => $type, 'link' => $linkId]" />
    </div>

    <p class="mt-3 text-xs text-ink-500">
        Only verified billing events count, in the currency they were billed. Amounts in different currencies are never added together.
        @if ($subscriptionNotVerifiable)
            <span class="mt-1 flex items-center gap-1.5 font-medium text-amber-700"><x-lucide-circle-alert class="h-3.5 w-3.5" aria-hidden="true" /> Recurring subscription revenue is not yet verifiable and is not included — it is not estimated.</span>
        @endif
    </p>

    <form method="GET" action="{{ route('revenue.index') }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
        @foreach ($period->query() as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}" />@endforeach
        <label class="sr-only" for="rev-type">Revenue type</label>
        <select id="rev-type" name="type" class="rounded-lg border-ink-200 text-sm focus:border-brix-500 focus:ring-brix-500">
            <option value="">All revenue types</option>
            @foreach ($types as $t)
                <option value="{{ $t }}" @selected($type === $t)>{{ ucfirst($t) }}</option>
            @endforeach
        </select>
        <label class="sr-only" for="rev-link">Referral link</label>
        <select id="rev-link" name="link" class="rounded-lg border-ink-200 text-sm focus:border-brix-500 focus:ring-brix-500">
            <option value="">All links</option>
            @foreach ($links as $link)
                <option value="{{ $link->id }}" @selected($linkId === $link->id)>{{ $link->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg bg-ink-900 px-4 py-2 text-sm font-medium text-white hover:bg-ink-800 sm:w-28">Filter</button>
    </form>

    <section class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-5" aria-label="Revenue summary">
        <x-kpi-card label="Revenue · {{ strtolower($period->label()) }}" :value="Money::money($revenue)" :change="$growth" :comparison="$period->comparisonLabel()" icon="trending-up"
            :context="number_format($eventCount).' billing '.Str::plural('event', $eventCount)" />
        <x-kpi-card label="Previous period" :value="$previousRevenue === null ? 'n/a' : Money::money($previousRevenue)"
            :context="$previousRevenue === null ? 'no earlier window for all time' : 'same length, just before'" icon="history" />
        <x-kpi-card label="Growth" :value="$growth === null ? '—' : ($growth >= 0 ? '+' : '').$growth.'%'"
            :context="$growth === null ? 'not calculable (no base, or mixed currencies)' : 'vs previous period'" icon="activity" />
        <x-kpi-card label="Commission earned" :value="Money::money($commission)" context="on these events" icon="hand-coins" :href="route('earnings')" />
        <x-kpi-card label="Revenue-earning stores" :value="number_format($storeCount)" context="in this period" icon="store" />
    </section>

    <section class="mt-4 rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle" aria-labelledby="rev-chart-heading">
        <div class="mb-3 flex items-baseline justify-between">
            <h3 id="rev-chart-heading" class="text-sm font-semibold text-ink-900">Revenue over time</h3>
            <span class="text-[11px] text-ink-400">{{ $period->label() }}</span>
        </div>
        <x-trend-chart :chart="$chart" empty-title="No verified revenue in this period" empty-text="Revenue appears once a store you referred is billed by BRIX." />
    </section>

    <section class="mt-4 overflow-hidden rounded-xl border border-ink-200/70 bg-white shadow-subtle">
        <h3 class="px-5 pt-5 text-sm font-semibold text-ink-900">Last 6 months</h3>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-y border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                        <th class="px-5 py-2.5">Month</th>
                        <th class="px-5 py-2.5 text-right">Revenue</th>
                        <th class="px-5 py-2.5 text-right">Commission</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($monthly as $m)
                        <tr>
                            <td class="px-5 py-3 text-ink-700">{{ $m['label'] }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right text-ink-600">{{ Money::money($m['revenue']) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right text-ink-600">{{ Money::money($m['commission']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($eventCount > 0)
        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
            @foreach ($breakdowns as $title => $items)
                <section class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle">
                    <h3 class="text-sm font-semibold text-ink-900">{{ $title }}</h3>
                    <ul class="mt-2 divide-y divide-ink-100">
                        @forelse ($items as $item)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <span class="min-w-0 truncate text-ink-700">{{ $title === 'By type' ? ucfirst($item['label']) : $item['label'] }}
                                    <span class="text-[11px] text-ink-400">· {{ $item['count'] }}</span></span>
                                <span class="shrink-0 whitespace-nowrap font-medium tabular-nums text-ink-900">{{ TrendChart::money($item['revenue']) }}</span>
                            </li>
                        @empty
                            <li class="py-4 text-center text-xs text-ink-400">Nothing yet.</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>
    @endif

    @if ($rows->isEmpty())
        <div class="mt-6 rounded-2xl border border-dashed border-ink-200 py-16 text-center">
            <p class="text-sm font-medium text-ink-700">No verified revenue{{ $type || $linkId || $period->key !== 'all' ? ' for these filters' : ' yet' }}</p>
            <p class="mt-1 text-sm text-ink-500">Revenue appears once a store you referred is billed by BRIX.</p>
        </div>
    @else
        <section class="mt-4 overflow-hidden rounded-xl border border-ink-200/70 bg-white shadow-subtle">
            <h3 class="px-5 pt-5 text-sm font-semibold text-ink-900">Billing events</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-y border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                            <th class="px-5 py-2.5">Date</th>
                            <th class="px-5 py-2.5">Store</th>
                            <th class="px-5 py-2.5">Referred via</th>
                            <th class="px-5 py-2.5">Type</th>
                            <th class="px-5 py-2.5 text-right">Revenue</th>
                            <th class="px-5 py-2.5 text-right">Commission</th>
                            <th class="px-5 py-2.5">Commission status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($rows as $event)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3 text-ink-600">{{ $event->occurred_at->format('M j, Y') }}</td>
                                <td class="px-5 py-3 font-medium text-ink-900">{{ $event->shop_domain }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $event->lead?->trackingLink?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-ink-600">{{ ucfirst($event->revenue_type) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-ink-700">{{ Currency::format($event->revenue_amount, $event->currency) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-ink-700">{{ $event->commission ? Currency::format($event->commission->commission_amount, $event->commission->currency) : '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-ink-500">{{ $event->commission ? ucfirst($event->commission->effective_status) : 'Not commissioned' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($rows->hasPages())<div class="border-t border-ink-100 px-5 py-4">{{ $rows->links() }}</div>@endif
        </section>
    @endif
</x-app-layout>
