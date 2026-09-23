@php
    use App\Services\Analytics\TrendChart;
    use App\Services\Referral\ReferralFunnel as Funnel;

    $q = $period->query();
@endphp
<x-app-layout title="Tracking">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Referral Tracking</h2>
            <p class="mt-1 text-sm text-ink-500">How visitors from your links become installed, active stores.</p>
        </div>
        <x-period-filter :period="$period" />
    </div>

    @if (! $hasAnyLinks)
        <div class="mt-8 rounded-2xl border border-dashed border-ink-200 py-16 text-center">
            <p class="text-sm font-medium text-ink-700">Nothing to track yet</p>
            <p class="mt-1 text-sm text-ink-500">Create a referral link and share it — clicks and leads will appear here.</p>
            <a href="{{ route('referral-links.index') }}" class="mt-4 inline-flex rounded-lg bg-brix-600 px-4 py-2 text-sm font-medium text-white hover:bg-brix-700">Create Referral Link</a>
        </div>
    @else
        @php
            $stages = [
                ['key' => 'clicks', 'label' => 'Clicks', 'href' => null],
                ['key' => 'install_started', 'label' => 'Install started', 'href' => null],
                ['key' => 'leads', 'label' => 'Leads', 'href' => route('leads.index', $q)],
                ['key' => 'installed', 'label' => 'Installed', 'href' => route('leads.index', $q + ['reached' => 'installed'])],
                ['key' => 'active', 'label' => 'Active', 'href' => route('leads.index', $q + ['reached' => 'active'])],
            ];
        @endphp

        <section class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" aria-label="Funnel totals, {{ strtolower($period->label()) }}">
            @foreach ($stages as $i => $stage)
                @php $rate = $i > 0 ? Funnel::rate($funnel[$stage['key']], $funnel[$stages[$i - 1]['key']]) : null; @endphp
                <x-kpi-card :label="$stage['label']" :value="number_format($funnel[$stage['key']])" :href="$stage['href']"
                    :context="$i === 0 ? ($funnel['qr_scans'] ? number_format($funnel['qr_scans']).' from QR scans' : strtolower($period->label())) : ($rate === null ? 'no '.strtolower($stages[$i - 1]['label']).' to convert' : $rate.'% of '.strtolower($stages[$i - 1]['label']))" />
            @endforeach
        </section>

        <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
            <section class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle xl:col-span-2" aria-labelledby="trend-heading">
                <div class="mb-3 flex items-baseline justify-between">
                    <h3 id="trend-heading" class="text-sm font-semibold text-ink-900">Clicks, leads &amp; installs</h3>
                    <span class="text-[11px] text-ink-400">{{ $period->label() }}</span>
                </div>
                <x-trend-chart :chart="$chart" empty-title="No referral activity in this period" empty-text="Try a longer range, or share a referral link." />
            </section>

            <section class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle" aria-labelledby="funnel-heading">
                <h3 id="funnel-heading" class="mb-3 text-sm font-semibold text-ink-900">Funnel · {{ strtolower($period->label()) }}</h3>
                <x-funnel :steps="array_map(fn ($s) => ['label' => $s['label'], 'value' => $funnel[$s['key']], 'href' => $s['href']], $stages)"
                    caption="Each stage counts what happened inside the period. Install started = the merchant's store was known and they were sent on to install." />
            </section>
        </div>

        <section class="mt-4 overflow-hidden rounded-xl border border-ink-200/70 bg-white shadow-subtle" aria-labelledby="links-heading">
            <div class="flex items-baseline justify-between px-4 pt-4">
                <h3 id="links-heading" class="text-sm font-semibold text-ink-900">Referral link performance <span class="font-normal text-ink-400">· by clicks, {{ strtolower($period->label()) }}</span></h3>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-y border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                            <th class="px-4 py-2.5">Referral link</th>
                            <th class="px-4 py-2.5 text-right">Clicks</th>
                            <th class="px-4 py-2.5 text-right">Leads</th>
                            <th class="px-4 py-2.5 text-right">Install rate</th>
                            <th class="px-4 py-2.5 text-right">Active</th>
                            <th class="px-4 py-2.5 text-right">Revenue</th>
                            <th class="px-4 py-2.5 text-right">Commission</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($links as $row)
                            @php $rate = Funnel::rate($row['installed'], $row['leads']); @endphp
                            <tr class="relative transition hover:bg-ink-50 motion-reduce:transition-none">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('referral-links.show', ['trackingLink' => $row['link']->id] + $q) }}"
                                        class="font-medium text-ink-900 after:absolute after:inset-0 after:content-[''] hover:underline focus:outline-none focus-visible:underline">{{ $row['link']->name }}</a>
                                    <span class="block text-xs text-ink-400">{{ $row['link']->channel }} · <span class="font-mono">{{ $row['link']->code }}</span></span>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-ink-700">{{ number_format($row['clicks']) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-ink-700">{{ number_format($row['leads']) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-ink-700">{{ $rate === null ? '—' : $rate.'%' }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-ink-700">{{ number_format($row['active']) }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-ink-700">{{ TrendChart::money($row['revenue']) }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-ink-700">{{ TrendChart::money($row['commission']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="px-4 py-2 text-[11px] text-ink-400">Revenue and commission are verified referral amounts recorded in the period, per currency. Install rate = installed ÷ leads.</p>
        </section>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <section class="overflow-hidden rounded-xl border border-ink-200/70 bg-white shadow-subtle" aria-labelledby="channel-heading">
                <h3 id="channel-heading" class="px-4 pt-4 text-sm font-semibold text-ink-900">By channel</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-y border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                                <th class="px-4 py-2.5">Channel</th>
                                <th class="px-4 py-2.5 text-right">Clicks</th>
                                <th class="px-4 py-2.5 text-right">Leads</th>
                                <th class="px-4 py-2.5 text-right">Installed</th>
                                <th class="px-4 py-2.5 text-right">Active</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($channels as $channel => $c)
                                <tr>
                                    <td class="px-4 py-2.5 font-medium text-ink-900">{{ $channel }}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums text-ink-600">{{ number_format($c['clicks']) }}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums text-ink-600">{{ number_format($c['leads']) }}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums text-ink-600">{{ number_format($c['installed']) }}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums text-ink-600">{{ number_format($c['active']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border border-ink-200/70 bg-white shadow-subtle" aria-labelledby="recent-heading">
                <h3 id="recent-heading" class="border-b border-ink-100 px-4 py-3 text-sm font-semibold text-ink-900">Recent referral activity</h3>
                <ul class="divide-y divide-ink-100">
                    @forelse ($recentClicks as $click)
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                            <span class="min-w-0">
                                <span class="block truncate text-ink-800">{{ $click->shop_domain ?? 'Store not entered yet' }}</span>
                                <span class="block truncate text-[11px] text-ink-500">{{ $click->trackingLink?->name }} · {{ $click->created_at->format('M j, g:i A') }}</span>
                            </span>
                            <span @class(['shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium', 'bg-blue-50 text-blue-700' => $click->source === 'qr', 'bg-ink-100 text-ink-600' => $click->source !== 'qr'])>
                                {{ $click->source === 'qr' ? 'QR scan' : 'Link click' }}
                            </span>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-xs text-ink-400">No clicks in this period.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    @endif
</x-app-layout>
