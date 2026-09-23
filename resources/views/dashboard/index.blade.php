@php
    use App\Services\Analytics\TrendChart;

    $q = $period->query();
    $money = fn (array $byCurrency) => $byCurrency ? TrendChart::money($byCurrency) : '—';
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = Str::of(auth()->user()->name)->before(' ');
@endphp
<x-app-layout title="Dashboard">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">{{ $greeting }}, {{ $firstName }}</h2>
            <p class="mt-1 text-sm text-ink-500">
                Showing <span class="font-medium text-ink-700">{{ strtolower($period->label()) }}</span>
                <span class="text-ink-400">· {{ $period->from?->format('M j') }} – {{ $period->to->format('M j, Y') }}</span>
            </p>
        </div>
        <x-period-filter :period="$period" />
    </div>

    {{-- KPIs --}}
    <section aria-label="Key metrics" class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4 2xl:grid-cols-7">
        <x-kpi-card label="New Leads" :value="number_format($kpis['leads']['value'])" :change="$kpis['leads']['change']"
            :comparison="$period->comparisonLabel()" :context="$kpis['leads']['context']" icon="users" :href="route('leads.index', $q)" />
        <x-kpi-card label="Stores Installed" :value="number_format($kpis['installed']['value'])" :change="$kpis['installed']['change']"
            :comparison="$period->comparisonLabel()" :context="$kpis['installed']['context']" icon="download" :href="route('stores.index')" />
        <x-kpi-card label="Active Stores" :value="number_format($kpis['active']['value'])" :context="$kpis['active']['context']"
            icon="circle-check-big" :href="route('stores.index', ['status' => 'active'])" />
        <x-kpi-card label="Revenue" :value="$money($kpis['revenue']['value'])" :change="$kpis['revenue']['change']"
            :comparison="$period->comparisonLabel()" :context="$kpis['revenue']['context']" icon="trending-up" :href="route('revenue.index', $q)" />
        <x-kpi-card label="Commission" :value="$money($kpis['commission']['value'])" :change="$kpis['commission']['change']"
            :comparison="$period->comparisonLabel()" :context="$kpis['commission']['context']" icon="hand-coins" :href="route('earnings')" />
        <x-kpi-card label="Available Payout" :value="$money($kpis['available']['value'])" :context="$kpis['available']['context']"
            icon="wallet" :href="route('payouts')" />
        <x-kpi-card label="Pending Payout" :value="$money($kpis['pending_payout']['value'])" :context="$kpis['pending_payout']['context']"
            icon="clock" :href="route('payouts')" />
    </section>

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
        {{-- Trend --}}
        <section class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle xl:col-span-2" aria-labelledby="trend-heading">
            <div class="mb-3 flex items-baseline justify-between gap-2">
                <h3 id="trend-heading" class="text-sm font-semibold text-ink-900">Revenue &amp; referral trend</h3>
                <span class="text-[11px] text-ink-400">{{ $period->label() }}</span>
            </div>
            <x-trend-chart :chart="$chart" height="h-64"
                empty-title="No revenue or referral activity yet"
                :empty-text="$hasLinks ? 'Nothing happened in this period — try a longer range.' : 'Share a referral link to start tracking installs and revenue.'"
                :empty-action="$hasLinks ? null : 'Create Referral Link'" :empty-href="$hasLinks ? null : route('referral-links.index')" />
        </section>

        {{-- Funnel --}}
        <section class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle" aria-labelledby="funnel-heading">
            <div class="mb-3 flex items-baseline justify-between gap-2">
                <h3 id="funnel-heading" class="text-sm font-semibold text-ink-900">Referral funnel</h3>
                <a href="{{ route('tracking.index', $q) }}" class="text-[11px] font-medium text-ink-500 hover:text-ink-900">Full tracking →</a>
            </div>
            @if (array_sum($funnel) === 0)
                <div class="rounded-lg border border-dashed border-ink-200 px-4 py-8 text-center">
                    <p class="text-sm font-medium text-ink-700">No referral activity yet</p>
                    <p class="mt-0.5 text-xs text-ink-500">Clicks, leads and installs from your links appear here.</p>
                    <div class="mt-3 flex justify-center gap-2">
                        <a href="{{ route('referral-links.index') }}" class="rounded-lg bg-ink-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-ink-800">Create Referral Link</a>
                        <a href="{{ route('leads.index') }}" class="rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-medium text-ink-700 hover:bg-ink-50">Add Lead</a>
                    </div>
                </div>
            @else
                <x-funnel :steps="[
                    ['label' => 'Clicks', 'value' => $funnel['clicks'], 'href' => route('tracking.index', $q), 'hint' => $funnel['qr_scans'] ? number_format($funnel['qr_scans']).' from QR scans' : null],
                    ['label' => 'Leads', 'value' => $funnel['leads'], 'href' => route('leads.index', $q)],
                    ['label' => 'Installed', 'value' => $funnel['installed'], 'href' => route('leads.index', $q + ['reached' => 'installed'])],
                    ['label' => 'Active', 'value' => $funnel['active'], 'href' => route('leads.index', $q + ['reached' => 'active'])],
                ]" caption="Each stage counts what happened inside the period: clicks made, leads created, leads that installed, leads that activated." />
            @endif
        </section>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Activity --}}
        <section class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle lg:col-span-2" aria-labelledby="activity-heading">
            <div class="mb-2 flex items-baseline justify-between">
                <h3 id="activity-heading" class="text-sm font-semibold text-ink-900">Recent activity</h3>
                <span class="text-[11px] text-ink-400">{{ $period->label() }}</span>
            </div>
            <x-activity-feed :items="$activity" empty="No leads, installs, revenue or payouts in this period." />
        </section>

        <div class="flex flex-col gap-4">
            {{-- Weekday activity --}}
            <section class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle" aria-labelledby="weekday-heading">
                <h3 id="weekday-heading" class="text-sm font-semibold text-ink-900">Activity by weekday</h3>
                <p class="text-[11px] text-ink-400">Referral clicks + new leads, {{ strtolower($period->label()) }}</p>
                @if ($weekdayMax === 0)
                    <p class="mt-4 text-xs text-ink-400">No clicks or leads in this period.</p>
                @else
                    <ul class="mt-3 grid grid-cols-7 gap-1.5">
                        @foreach ($weekday as $day => $count)
                            @php $level = $count === 0 ? 0 : (int) ceil($count / $weekdayMax * 4); @endphp
                            <li class="text-center" title="{{ $day }}: {{ $count }}">
                                <span @class(['flex h-9 items-center justify-center rounded-md text-[11px] font-semibold tabular-nums',
                                    'bg-ink-50 text-ink-300' => $level === 0, 'bg-blue-100 text-blue-800' => $level === 1, 'bg-blue-200 text-blue-900' => $level === 2,
                                    'bg-blue-400 text-white' => $level === 3, 'bg-blue-600 text-white' => $level === 4])>{{ $count }}</span>
                                <span class="mt-1 block text-[10px] text-ink-500">{{ $day }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Store health --}}
            <section class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle" aria-labelledby="health-heading">
                <h3 id="health-heading" class="text-sm font-semibold text-ink-900">Store health <span class="font-normal text-ink-400">· now</span></h3>
                <div class="mt-2 space-y-0.5">
                    @foreach (['active' => ['Active', 'bg-emerald-500'], 'attention' => ['Attention', 'bg-amber-500'], 'offline' => ['Offline', 'bg-rose-500']] as $status => [$label, $dot])
                        <a href="{{ route('stores.index', ['status' => $status]) }}" class="flex items-center justify-between rounded-lg px-2 py-1.5 text-sm transition hover:bg-ink-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brix-600">
                            <span class="flex items-center gap-2 text-ink-700"><span class="h-2 w-2 rounded-full {{ $dot }}" aria-hidden="true"></span>{{ $label }}</span>
                            <span class="font-semibold tabular-nums text-ink-900">{{ $storeHealth[$status] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Recent stores --}}
        <section class="rounded-xl border border-ink-200/70 bg-white shadow-subtle lg:col-span-2">
            <div class="flex items-center justify-between border-b border-ink-100 px-4 py-3">
                <h3 class="text-sm font-semibold text-ink-900">Recent stores</h3>
                <a href="{{ route('stores.index') }}" class="text-[11px] font-medium text-ink-500 hover:text-ink-900">View all →</a>
            </div>
            <ul class="divide-y divide-ink-100">
                @forelse ($recentStores as $store)
                    <li>
                        <a href="{{ route('stores.show', $store) }}" class="flex items-center justify-between gap-3 px-4 py-3 transition hover:bg-ink-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brix-600">
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-ink-900 text-xs font-semibold text-white" aria-hidden="true">{{ Str::substr($store->name, 0, 1) }}</span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-ink-900">{{ $store->name }}</span>
                                    <span class="block truncate text-[11px] text-ink-500">{{ $store->shop_domain }}</span>
                                </span>
                            </span>
                            <span class="flex shrink-0 items-center gap-4">
                                <span class="hidden text-[11px] text-ink-500 sm:inline">{{ $store->active_modules_count }}/{{ $store->total_modules_count }} modules</span>
                                <x-status-badge :status="$store->status" />
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-xs text-ink-400">No stores connected yet.</li>
                @endforelse
            </ul>
        </section>

        {{-- Module adoption --}}
        <section class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle">
            <h3 class="text-sm font-semibold text-ink-900">Module adoption <span class="font-normal text-ink-400">· now</span></h3>
            <div class="mt-3 space-y-3">
                @foreach ($moduleAdoption as $module)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-xs">
                            <span class="font-medium text-ink-700">{{ $module['label'] }}</span>
                            <span class="tabular-nums text-ink-500">{{ $module['count'] }} stores</span>
                        </div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-ink-100" role="progressbar" aria-valuenow="{{ $module['percentage'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $module['label'] }} adoption">
                            <div class="h-full rounded-full bg-ink-800" style="width: {{ $module['percentage'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    {{-- Milestones --}}
    <section class="mt-4 rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-ink-900">Partner milestones</h3>
            <span class="text-xs font-medium text-ink-500">{{ $milestoneProgress['achieved'] }} / {{ $milestoneProgress['total'] }}</span>
        </div>
        <ul class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($milestones as $milestone)
                <li class="flex items-center gap-2 rounded-lg border px-3 py-2 text-xs {{ $milestone['achieved'] ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-ink-200/70 text-ink-500' }}">
                    <x-dynamic-component :component="$milestone['achieved'] ? 'lucide-circle-check-big' : 'lucide-circle'" class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                    <span>{{ $milestone['label'] }}<span class="sr-only">{{ $milestone['achieved'] ? ' — achieved' : ' — not yet' }}</span></span>
                </li>
            @endforeach
        </ul>
    </section>
</x-app-layout>
