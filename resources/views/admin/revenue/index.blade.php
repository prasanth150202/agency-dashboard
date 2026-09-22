@php use App\Support\Currency; @endphp
<x-admin-layout title="Revenue">
    <div class="mb-5">
        <h2 class="text-lg font-semibold text-ink-900">Referral revenue</h2>
        <p class="text-sm text-ink-500">Verified BRIX revenue earned by referred stores, across every agency.</p>
    </div>

    <form method="GET" class="mb-5 flex flex-wrap items-center gap-3">
        <select name="range" onchange="this.form.submit()" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
            @foreach ($ranges as $r)
                <option value="{{ $r }}" @selected($range === $r)>{{ $r === 'all' ? 'All time' : "Last {$r} days" }}</option>
            @endforeach
        </select>
        <select name="type" onchange="this.form.submit()" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
            <option value="">All types</option>
            @foreach ($types as $t)
                <option value="{{ $t }}" @selected($type === $t)>{{ ucfirst($t) }}</option>
            @endforeach
        </select>
        <select name="agency" onchange="this.form.submit()" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
            <option value="">All agencies</option>
            @foreach ($agencies as $agency)
                <option value="{{ $agency->id }}" @selected($agencyId === $agency->id)>{{ $agency->name }}</option>
            @endforeach
        </select>
    </form>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-metric-card
            label="Total Revenue"
            :value="collect($totalRevenue)->map(fn ($v, $c) => \App\Support\Currency::format((float) $v, $c))->implode(' + ') ?: '—'"
            icon="trending-up"
        />
        <x-metric-card label="Revenue Events" :value="$eventCount" icon="database" />
        <x-metric-card label="Subscription Revenue" value="Not yet verifiable" icon="circle-alert" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
            <h3 class="text-sm font-semibold text-ink-900">By revenue type</h3>
            <div class="mt-3 divide-y divide-ink-100">
                @forelse ($byType as $revenueType => $typeRows)
                    <div class="flex items-center justify-between py-2.5 text-sm">
                        <p class="font-medium text-ink-900">{{ ucfirst($revenueType) }}</p>
                        <p class="text-ink-600">
                            @foreach ($typeRows as $row)
                                {{ Currency::format((float) $row->total, $row->currency) }}
                            @endforeach
                        </p>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-ink-400">No revenue recorded yet.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
            <h3 class="text-sm font-semibold text-ink-900">By agency</h3>
            <div class="mt-3 max-h-72 divide-y divide-ink-100 overflow-y-auto">
                @forelse ($byAgency as $agencyId => $agencyRows)
                    <div class="flex items-center justify-between py-2.5 text-sm">
                        <a href="{{ route('admin.revenue.index', ['agency' => $agencyId]) }}" class="font-medium text-ink-900 hover:text-brix-600">{{ $agencyRows->first()->agency_name }}</a>
                        <p class="text-ink-600">
                            @foreach ($agencyRows as $row)
                                {{ Currency::format((float) $row->total, $row->currency) }}
                            @endforeach
                        </p>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-ink-400">No revenue recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
        <div class="border-b border-ink-100 p-5">
            <h3 class="text-sm font-semibold text-ink-900">Revenue events</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-100 text-left text-xs font-medium uppercase tracking-wide text-ink-400">
                        <th class="px-5 py-3">Agency</th>
                        <th class="px-5 py-3">Shop</th>
                        <th class="px-5 py-3">Type</th>
                        <th class="px-5 py-3">Referral link</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3">Occurred</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($rows as $event)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3 text-ink-600">{{ $event->agency->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-ink-900">{{ $event->shop_domain }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ ucfirst($event->revenue_type) }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ $event->lead?->trackingLink?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-right font-medium text-ink-900">{{ Currency::format((float) $event->revenue_amount, $event->currency) }}</td>
                            <td class="px-5 py-3 text-ink-400">{{ $event->occurred_at->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-ink-400">No revenue events found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-ink-100 p-5">{{ $rows->links() }}</div>
    </div>
</x-admin-layout>
