<x-app-layout title="Dashboard">
    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
        $firstName = Str::of(auth()->user()->name)->before(' ');
    @endphp

    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">{{ $greeting }}, {{ $firstName }}</h2>
            <p class="mt-1 text-sm text-ink-500">Here's what's happening across your agency.</p>
        </div>

        <form method="GET" action="{{ route('dashboard') }}">
            <select
                name="range"
                x-data
                x-on:change="$el.form.submit()"
                class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm font-medium text-ink-700 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
            >
                <option value="7" @selected($range === 7)>7 days</option>
                <option value="30" @selected($range === 30)>30 days</option>
                <option value="90" @selected($range === 90)>90 days</option>
            </select>
        </form>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-metric-card
            label="Total Stores"
            :value="$metrics['total_stores']['value']"
            :delta="$metrics['total_stores']['delta']"
            :caption="$metrics['total_stores']['caption']"
            icon="store"
        />
        <x-metric-card
            label="Active Stores"
            :value="$metrics['active_stores']['value']"
            :delta="$metrics['active_stores']['delta']"
            :caption="$metrics['active_stores']['caption']"
            icon="zap"
        />
        <x-metric-card
            label="Monthly Revenue"
            :value="'₹' . number_format($metrics['monthly_revenue']['value'])"
            :delta="$metrics['monthly_revenue']['delta']"
            :caption="$metrics['monthly_revenue']['caption']"
            icon="trending-up"
        />
        <x-metric-card
            label="Pending Payout"
            :value="'₹' . number_format($metrics['pending_payout']['value'])"
            :delta="$metrics['pending_payout']['delta']"
            :caption="$metrics['pending_payout']['caption']"
            icon="wallet"
        />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Recent stores --}}
        <div class="rounded-2xl border border-ink-200/70 bg-white shadow-subtle lg:col-span-2">
            <div class="flex items-center justify-between border-b border-ink-100 px-5 py-4">
                <h3 class="text-sm font-semibold text-ink-900">Recent stores</h3>
                <a href="{{ route('stores.index') }}" class="text-xs font-medium text-brix-600 hover:text-brix-700">View all</a>
            </div>

            <ul class="divide-y divide-ink-100">
                @forelse ($recentStores as $store)
                    <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-ink-900 text-xs font-semibold text-white">
                                {{ Str::substr($store->name, 0, 1) }}
                            </div>
                            <div class="min-w-0">
                                <a href="{{ route('stores.show', $store) }}" class="block truncate text-sm font-medium text-ink-900 hover:text-brix-700">
                                    {{ $store->name }}
                                </a>
                                <p class="truncate text-xs text-ink-500">{{ $store->shop_domain }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 sm:gap-6">
                            <x-status-badge :status="$store->status" />
                            <span class="hidden text-xs text-ink-500 sm:block">
                                {{ $store->active_modules_count }} / {{ $store->total_modules_count }} modules
                            </span>
                            <span class="hidden text-xs text-ink-400 md:block">
                                {{ $store->last_active_at?->diffForHumans() ?? '—' }}
                            </span>
                            <a
                                href="{{ $store->safe_app_url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-medium text-ink-700 hover:bg-ink-50"
                            >
                                <x-lucide-eye class="h-3.5 w-3.5" />
                                Preview
                            </a>
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-ink-400">No stores connected yet.</li>
                @endforelse
            </ul>
        </div>

        {{-- Right column --}}
        <div class="flex flex-col gap-4">
            {{-- Store health --}}
            <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <h3 class="text-sm font-semibold text-ink-900">Store health</h3>
                <div class="mt-4 space-y-2.5">
                    <a href="{{ route('stores.index', ['status' => 'active']) }}" class="flex items-center justify-between rounded-lg px-2.5 py-2 hover:bg-ink-50">
                        <span class="flex items-center gap-2 text-sm text-ink-700">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            Active
                        </span>
                        <span class="text-sm font-semibold text-ink-900">{{ $storeHealth['active'] }}</span>
                    </a>
                    <a href="{{ route('stores.index', ['status' => 'attention']) }}" class="flex items-center justify-between rounded-lg px-2.5 py-2 hover:bg-ink-50">
                        <span class="flex items-center gap-2 text-sm text-ink-700">
                            <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                            Attention
                        </span>
                        <span class="text-sm font-semibold text-ink-900">{{ $storeHealth['attention'] }}</span>
                    </a>
                    <a href="{{ route('stores.index', ['status' => 'offline']) }}" class="flex items-center justify-between rounded-lg px-2.5 py-2 hover:bg-ink-50">
                        <span class="flex items-center gap-2 text-sm text-ink-700">
                            <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                            Offline
                        </span>
                        <span class="text-sm font-semibold text-ink-900">{{ $storeHealth['offline'] }}</span>
                    </a>
                </div>
            </div>

            {{-- Finance --}}
            <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <h3 class="text-sm font-semibold text-ink-900">Finance</h3>
                <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3">
                    <div>
                        <p class="text-xs text-ink-500">This Month</p>
                        <p class="mt-0.5 text-sm font-semibold text-ink-900">₹{{ number_format($financeSummary['this_month'], 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-ink-500">Available</p>
                        <p class="mt-0.5 text-sm font-semibold text-ink-900">₹{{ number_format($financeSummary['available'], 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-ink-500">Pending</p>
                        <p class="mt-0.5 text-sm font-semibold text-ink-900">₹{{ number_format($financeSummary['pending'], 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-ink-500">Last Payout</p>
                        <p class="mt-0.5 text-sm font-semibold text-ink-900">
                            {{ $financeSummary['last_payout'] ? '₹' . number_format((float) $financeSummary['last_payout']->amount, 2) : '—' }}
                        </p>
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <a href="{{ route('earnings') }}" class="flex-1 rounded-lg border border-ink-200 px-3 py-2 text-center text-xs font-medium text-ink-700 hover:bg-ink-50">
                        View Earnings
                    </a>
                    <a href="{{ route('payouts') }}" class="flex-1 rounded-lg bg-brix-600 px-3 py-2 text-center text-xs font-medium text-white hover:bg-brix-700">
                        Request Payout
                    </a>
                </div>
            </div>

            {{-- Recent activity --}}
            <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <h3 class="text-sm font-semibold text-ink-900">Recent activity</h3>
                <ul class="mt-3 space-y-3.5">
                    @forelse ($recentNotifications as $notification)
                        <li class="flex gap-2.5">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brix-500"></span>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-ink-900">{{ $notification->title }}</p>
                                <p class="text-xs text-ink-500">{{ $notification->message }}</p>
                                <p class="mt-0.5 text-[11px] text-ink-400">{{ $notification->created_at->diffForHumans() }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="text-xs text-ink-400">Nothing happened recently.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    {{-- Module adoption --}}
    <div class="mt-6 rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
        <h3 class="text-sm font-semibold text-ink-900">Module adoption</h3>
        <div class="mt-5 space-y-4">
            @foreach ($moduleAdoption as $module)
                <div>
                    <div class="mb-1.5 flex items-center justify-between text-sm">
                        <span class="font-medium text-ink-700">{{ $module['label'] }}</span>
                        <span class="text-ink-500">{{ $module['count'] }} stores</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-ink-100">
                        <div class="h-full rounded-full bg-brix-500" style="width: {{ $module['percentage'] }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Referral funnel --}}
    <div class="mt-6 rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-ink-900">Referral funnel</h3>
            <span class="text-xs text-ink-400">Last {{ $range }} days</span>
        </div>
        @php
            $funnelSteps = [
                ['label' => 'Clicks', 'value' => $referralFunnel['clicks']],
                ['label' => 'Leads', 'value' => $referralFunnel['leads']],
                ['label' => 'Installed', 'value' => $referralFunnel['installed']],
                ['label' => 'Active', 'value' => $referralFunnel['active']],
            ];
        @endphp
        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($funnelSteps as $i => $step)
                <div class="rounded-xl border border-ink-200/70 px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-ink-400">{{ $step['label'] }}</p>
                    <p class="mt-1 text-xl font-semibold text-ink-900">{{ $step['value'] }}</p>
                    @if ($i > 0)
                        <p class="mt-0.5 text-[11px] text-ink-400">
                            {{ \App\Services\Referral\ReferralFunnel::rate($step['value'], $funnelSteps[$i - 1]['value']) ?? 0 }}% of {{ strtolower($funnelSteps[$i - 1]['label']) }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
        <a href="{{ route('tracking.index') }}" class="mt-4 inline-flex items-center gap-1 text-xs font-medium text-brix-600 hover:text-brix-700">
            View full tracking <x-lucide-arrow-right class="h-3 w-3" />
        </a>
    </div>

    {{-- Milestones --}}
    <div class="mt-6 rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-ink-900">Partner milestones</h3>
            <span class="text-xs font-medium text-ink-500">{{ $milestoneProgress['achieved'] }} / {{ $milestoneProgress['total'] }}</span>
        </div>
        <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-ink-100">
            <div class="h-full rounded-full bg-brix-500" style="width: {{ $milestoneProgress['total'] > 0 ? round($milestoneProgress['achieved'] / $milestoneProgress['total'] * 100) : 0 }}%"></div>
        </div>
        <ul class="mt-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
            @foreach ($milestones as $milestone)
                <li class="flex items-center gap-2.5 rounded-xl border px-3 py-2.5 text-sm {{ $milestone['achieved'] ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-ink-200/70 text-ink-500' }}">
                    <x-dynamic-component :component="$milestone['achieved'] ? 'lucide-circle-check-big' : 'lucide-circle'" class="h-4 w-4 shrink-0" />
                    {{ $milestone['label'] }}
                </li>
            @endforeach
        </ul>
    </div>
</x-app-layout>
