@php
    use App\Services\Analytics\TrendChart;

    $q = $period->query();
@endphp
<x-app-layout title="{{ $link->name }}">
    <a href="{{ route('referral-links.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-800">
        <x-lucide-arrow-left class="h-3.5 w-3.5" aria-hidden="true" /> Referral Links
    </a>

    <div class="mt-2 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="truncate text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">{{ $link->name }}</h2>
                <x-status-badge :status="$link->is_active ? 'active' : 'inactive'" :label="$link->is_active ? 'Active' : 'Inactive'" />
            </div>
            <p class="mt-1 text-sm text-ink-500">{{ $link->channel }} · <span class="font-mono">{{ $link->code }}</span> · created {{ $link->created_at->format('M j, Y') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2" x-data="copyText(@js($link->referral_url))">
            <span class="max-w-[16rem] truncate rounded-lg border border-ink-200 bg-white px-3 py-2 font-mono text-xs text-ink-700">{{ $link->referral_url }}</span>
            <button type="button" x-on:click="copy()" class="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-2 text-xs font-medium text-ink-700 hover:bg-ink-50">
                <x-lucide-copy class="h-3.5 w-3.5" x-show="!copied" aria-hidden="true" />
                <x-lucide-check class="h-3.5 w-3.5 text-emerald-600" x-show="copied" x-cloak aria-hidden="true" />
                <span x-text="copied ? 'Copied' : 'Copy link'">Copy link</span>
            </button>
            <a href="{{ route('qr.index', ['link' => $link->id]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-2 text-xs font-medium text-ink-700 hover:bg-ink-50">
                <x-lucide-qr-code class="h-3.5 w-3.5" aria-hidden="true" /> QR code
            </a>
        </div>
    </div>

    {{-- All-time totals for the link --}}
    <section class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7" aria-label="All-time totals">
        <x-kpi-card label="Clicks" :value="number_format($totals['clicks'])" :context="$totals['qr_scans'] ? number_format($totals['qr_scans']).' from QR' : 'all time'" icon="mouse-pointer-click" />
        <x-kpi-card label="Leads" :value="number_format($totals['leads'])" context="all time" icon="users" :href="route('referral-links.leads', $link)" />
        <x-kpi-card label="Installed" :value="number_format($totals['installed'])" context="all time" icon="download" />
        <x-kpi-card label="Active" :value="number_format($totals['active'])" context="all time" icon="circle-check-big" />
        <x-kpi-card label="Install rate" :value="($r = \App\Services\Referral\ReferralFunnel::rate($totals['installed'], $totals['leads'])) === null ? '—' : $r.'%'" context="installed ÷ leads" icon="activity" />
        <x-kpi-card label="Revenue" :value="TrendChart::money($totals['revenue'])" context="verified, all time" icon="trending-up" :href="route('revenue.index', ['link' => $link->id, 'range' => 'all'])" />
        <x-kpi-card label="Commission" :value="TrendChart::money($totals['commission'])" context="earned, all time" icon="hand-coins" />
    </section>

    <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h3 class="text-sm font-semibold text-ink-900">Performance · <span class="font-normal text-ink-500">{{ strtolower($period->label()) }}</span></h3>
        <x-period-filter :period="$period" :options="['7d', '30d', '3m', '6m', 'ytd', 'all', 'custom']" />
    </div>

    <div class="mt-3 grid grid-cols-1 gap-4 xl:grid-cols-3">
        <section class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle xl:col-span-2" aria-label="Clicks, leads and installs over time">
            <x-trend-chart :chart="$chart" height="h-60" empty-title="No clicks, leads or installs in this period"
                empty-text="Share the link or QR code — activity appears here as it happens." />
        </section>

        <section class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle" aria-label="Funnel">
            <h4 class="mb-3 text-sm font-semibold text-ink-900">Funnel</h4>
            <x-funnel :steps="[
                ['label' => 'Clicks', 'value' => $funnel['clicks'], 'hint' => $funnel['qr_scans'] ? number_format($funnel['qr_scans']).' from QR scans' : null],
                ['label' => 'Leads', 'value' => $funnel['leads'], 'href' => route('leads.index', $q + ['link' => $link->id])],
                ['label' => 'Installed', 'value' => $funnel['installed'], 'href' => route('leads.index', $q + ['link' => $link->id, 'reached' => 'installed'])],
                ['label' => 'Active', 'value' => $funnel['active'], 'href' => route('leads.index', $q + ['link' => $link->id, 'reached' => 'active'])],
            ]" caption="Counts are what happened inside the period for this link." />
        </section>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-ink-200/70 bg-white shadow-subtle">
            <div class="flex items-center justify-between border-b border-ink-100 px-4 py-3">
                <h4 class="text-sm font-semibold text-ink-900">Recent leads</h4>
                <a href="{{ route('referral-links.leads', $link) }}" class="text-[11px] font-medium text-ink-500 hover:text-ink-900">All leads →</a>
            </div>
            <ul class="divide-y divide-ink-100">
                @forelse ($recentLeads as $lead)
                    <li>
                        <a href="{{ route('leads.show', $lead) }}" class="flex items-center justify-between gap-3 px-4 py-2.5 transition hover:bg-ink-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brix-600">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-ink-900">{{ $lead->store?->store_name ?? $lead->company_name ?? $lead->shop_domain ?? 'Lead #'.$lead->id }}</span>
                                <span class="block text-[11px] text-ink-500">{{ $lead->created_at->format('M j, Y') }}</span>
                            </span>
                            <span class="flex shrink-0 items-center gap-2">
                                <x-lead-stage-badge :stage="$lead->lead_stage" />
                                <x-lead-install-badge :lead="$lead" />
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-xs text-ink-400">No leads from this link yet.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-xl border border-ink-200/70 bg-white shadow-subtle">
            <div class="border-b border-ink-100 px-4 py-3">
                <h4 class="text-sm font-semibold text-ink-900">Recent clicks</h4>
            </div>
            <ul class="divide-y divide-ink-100">
                @forelse ($recentClicks as $click)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                        <span class="min-w-0">
                            <span class="block truncate text-ink-800">{{ $click->shop_domain ?? 'Store not entered' }}</span>
                            <span class="block text-[11px] text-ink-500">{{ $click->created_at->format('M j, Y g:i A') }}</span>
                        </span>
                        <span @class(['shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium', 'bg-blue-50 text-blue-700' => $click->source === 'qr', 'bg-ink-100 text-ink-600' => $click->source !== 'qr'])>
                            {{ $click->source === 'qr' ? 'QR scan' : 'Link click' }}
                        </span>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-xs text-ink-400">No clicks yet.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-app-layout>
