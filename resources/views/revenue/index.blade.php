@php
    use App\Services\Referral\ReferralReporting as Money;
    use App\Support\Currency;
    $rangeLabels = ['30' => '30d', '90' => '90d', '365' => '1y', 'all' => 'All time'];
@endphp
<x-app-layout title="Revenue">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Revenue</h2>
            <p class="mt-1 text-sm text-ink-500">Verified BRIX billing earned by the stores you referred.</p>
        </div>
        <div class="inline-flex rounded-lg border border-ink-200 bg-white p-0.5 text-sm">
            @foreach ($ranges as $r)
                <a href="{{ route('revenue.index', array_filter(['range' => $r, 'type' => $type, 'link' => $linkId])) }}"
                   class="rounded-md px-3 py-1.5 font-medium {{ (string) $range === (string) $r ? 'bg-ink-900 text-white' : 'text-ink-600 hover:text-ink-900' }}">{{ $rangeLabels[$r] }}</a>
            @endforeach
        </div>
    </div>

    <p class="mt-3 text-xs text-ink-500">
        Only verified billing events count, in the currency they were billed. Amounts in different currencies are never added together.
        @if ($subscriptionNotVerifiable)
            Recurring subscription revenue is not yet verifiable and is not included.
        @endif
    </p>

    <form method="GET" action="{{ route('revenue.index') }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-4">
        <input type="hidden" name="range" value="{{ $range }}" />
        <select name="type" class="rounded-lg border-ink-200 text-sm focus:border-brix-500 focus:ring-brix-500">
            <option value="">All revenue types</option>
            @foreach ($types as $t)
                <option value="{{ $t }}" @selected($type === $t)>{{ ucfirst($t) }}</option>
            @endforeach
        </select>
        <select name="link" class="rounded-lg border-ink-200 text-sm focus:border-brix-500 focus:ring-brix-500">
            <option value="">All links</option>
            @foreach ($links as $link)
                <option value="{{ $link->id }}" @selected($linkId === $link->id)>{{ $link->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg bg-ink-900 px-4 py-2 text-sm font-medium text-white hover:bg-ink-800 sm:w-28">Filter</button>
    </form>

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-metric-card label="Verified revenue" :value="Money::money($revenue)" icon="trending-up" />
        <x-metric-card label="Commission earned" :value="Money::money($commission)" icon="indian-rupee" />
        <x-metric-card label="Billing events" :value="number_format($eventCount)" icon="receipt" />
        <x-metric-card label="Revenue-earning stores" :value="number_format($storeCount)" icon="store" />
    </div>

    <section class="mt-6 overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
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

    @if ($rows->isEmpty())
        <div class="mt-6 rounded-2xl border border-dashed border-ink-200 py-16 text-center">
            <p class="text-sm font-medium text-ink-700">No verified revenue{{ $type || $linkId || $range !== 'all' ? ' for these filters' : ' yet' }}</p>
            <p class="mt-1 text-sm text-ink-500">Revenue appears once a store you referred is billed by BRIX.</p>
        </div>
    @else
        <section class="mt-6 overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
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
