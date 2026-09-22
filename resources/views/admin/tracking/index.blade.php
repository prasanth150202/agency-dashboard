<x-admin-layout title="Tracking">
    <div class="mb-5 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-ink-900">Referral tracking</h2>
            <p class="text-sm text-ink-500">Click-to-active funnel across every agency.</p>
        </div>
        <form method="GET" class="flex items-center gap-3">
            <select name="agency" onchange="this.form.submit()" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                <option value="">All agencies</option>
                @foreach ($agencies as $agency)
                    <option value="{{ $agency->id }}" @selected($agencyId === $agency->id)>{{ $agency->name }}</option>
                @endforeach
            </select>
            <div class="flex overflow-hidden rounded-lg border border-ink-200">
                @foreach ($ranges as $r)
                    <a href="{{ request()->fullUrlWithQuery(['range' => $r]) }}" class="px-3 py-2 text-sm font-medium {{ $range === $r ? 'bg-ink-900 text-white' : 'bg-white text-ink-600 hover:bg-ink-50' }}">{{ $r }}d</a>
                @endforeach
            </div>
        </form>
    </div>

    @php
        $steps = [
            ['label' => 'Clicks', 'value' => $totals['clicks']],
            ['label' => 'Leads', 'value' => $totals['leads']],
            ['label' => 'Installed', 'value' => $totals['installed']],
            ['label' => 'Active', 'value' => $totals['active']],
        ];
    @endphp
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach ($steps as $i => $step)
            <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <p class="text-xs font-medium uppercase tracking-wide text-ink-400">{{ $step['label'] }}</p>
                <p class="mt-1 text-2xl font-semibold text-ink-900">{{ $step['value'] }}</p>
                @if ($i > 0)
                    <p class="mt-1 text-xs text-ink-400">
                        {{ \App\Services\Referral\ReferralFunnel::rate($step['value'], $steps[$i - 1]['value']) ?? 0 }}% of {{ strtolower($steps[$i - 1]['label']) }}
                    </p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
        <h3 class="text-sm font-semibold text-ink-900">Clicks by agency</h3>
        <div class="mt-3 divide-y divide-ink-100">
            @forelse ($byAgency as $row)
                <div class="flex items-center justify-between py-2.5 text-sm">
                    <a href="{{ route('admin.tracking.index', ['agency' => $row->agency_id, 'range' => $range]) }}" class="font-medium text-ink-900 hover:text-brix-600">{{ $row->agency_name }}</a>
                    <p class="text-ink-600">{{ $row->clicks }} clicks</p>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-ink-400">No clicks in this period.</p>
            @endforelse
        </div>
    </div>
</x-admin-layout>
