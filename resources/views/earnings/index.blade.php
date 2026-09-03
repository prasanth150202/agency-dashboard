<x-app-layout title="My Earnings">
    <div x-data="{ selected: null }">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">My Earnings</h2>
                <p class="mt-1 text-sm text-ink-500">Track your BRIX commission earnings across all your stores.</p>
            </div>

            <div x-data="requestPayoutModal({{ $metrics['available'] }})">
                <button
                    type="button"
                    x-on:click="open()"
                    @disabled(! $canRequestPayout)
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brix-700 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <x-lucide-wallet class="h-4 w-4" />
                    Request Payout
                </button>
                @unless ($canRequestPayout)
                    <p class="mt-1.5 max-w-[220px] text-right text-xs text-ink-400 sm:max-w-none">
                        Your balance has not reached the minimum payout amount.
                    </p>
                @endunless

                <x-request-payout-modal :minimum-payout="$minimumPayout" :available-balance="$metrics['available']" />
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-metric-card label="This Month" :value="'₹' . number_format($metrics['this_month'], 2)" icon="calendar-days" />
            <x-metric-card
                label="Available to Withdraw"
                :value="'₹' . number_format($metrics['available'], 2)"
                icon="wallet"
                prominent
            />
            <x-metric-card label="Pending Commission" :value="'₹' . number_format($metrics['pending'], 2)" icon="clock" />
            <x-metric-card label="Lifetime Earnings" :value="'₹' . number_format($metrics['lifetime'], 2)" icon="trending-up" />
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('earnings') }}" class="mt-8 flex flex-col gap-3 lg:flex-row lg:items-center lg:flex-wrap">
            <div class="relative flex-1 lg:min-w-[220px]">
                <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                <input
                    type="search"
                    name="search"
                    value="{{ $filters['search'] }}"
                    placeholder="Search store..."
                    x-data
                    x-on:input.debounce.500ms="$el.form.submit()"
                    class="w-full rounded-lg border border-ink-200 bg-white py-2.5 pl-9 pr-3 text-sm text-ink-900 placeholder-ink-400 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                >
            </div>

            <select name="range" x-data x-on:change="$el.form.submit()" class="rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm font-medium text-ink-700 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                <option value="this_month" @selected($filters['range'] === 'this_month')>This Month</option>
                <option value="last_month" @selected($filters['range'] === 'last_month')>Last Month</option>
                <option value="last_7_days" @selected($filters['range'] === 'last_7_days')>Last 7 Days</option>
                <option value="all_time" @selected($filters['range'] === 'all_time')>All Time</option>
            </select>

            <select name="status" x-data x-on:change="$el.form.submit()" class="rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm font-medium text-ink-700 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                <option value="all" @selected($filters['status'] === 'all')>All Statuses</option>
                <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                <option value="available" @selected($filters['status'] === 'available')>Available</option>
                <option value="paid" @selected($filters['status'] === 'paid')>Paid</option>
                <option value="refunded" @selected($filters['status'] === 'refunded')>Refunded</option>
            </select>

            <select name="plan" x-data x-on:change="$el.form.submit()" class="rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm font-medium text-ink-700 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                <option value="all" @selected($filters['plan'] === 'all')>All Plans</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan }}" @selected($filters['plan'] === $plan)>{{ $plan }}</option>
                @endforeach
            </select>

            <select name="rate" x-data x-on:change="$el.form.submit()" class="rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm font-medium text-ink-700 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                <option value="all" @selected($filters['rate'] === 'all')>All Rates</option>
                <option value="agency" @selected($filters['rate'] === 'agency')>Agency Rate</option>
                <option value="custom" @selected($filters['rate'] === 'custom')>Custom Rate</option>
            </select>

            <a
                href="{{ route('earnings.export', ['range' => $filters['range']]) }}"
                class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3.5 py-2.5 text-sm font-medium text-ink-700 hover:bg-ink-50"
            >
                <x-lucide-download class="h-4 w-4" />
                Export CSV
            </a>
        </form>

        {{-- Store Earnings --}}
        <div class="mt-6 overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
            <div class="border-b border-ink-100 px-5 py-4">
                <h3 class="text-sm font-semibold text-ink-900">Store Earnings</h3>
            </div>

            @if ($storeEarnings->isEmpty())
                <div class="px-5 py-16 text-center">
                    <p class="text-sm font-medium text-ink-700">No earnings yet</p>
                    <p class="mt-1 text-sm text-ink-400">
                        Once your stores generate eligible BRIX revenue, your commissions will appear here.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                                <th class="px-5 py-3">Store</th>
                                <th class="px-5 py-3">Plan</th>
                                <th class="px-5 py-3 text-right">Gross Revenue</th>
                                <th class="px-5 py-3">Commission Rate</th>
                                <th class="px-5 py-3 text-right">Commission Earned</th>
                                <th class="px-5 py-3 text-right">Pending</th>
                                <th class="px-5 py-3 text-right">Available</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($storeEarnings as $row)
                                <tr
                                    x-on:click="selected = @js([
                                        'store' => $row->store->name,
                                        'domain' => $row->store->shop_domain,
                                        'plan' => $row->store->plan,
                                        'gross' => number_format((float) $row->latest_commission->gross_amount, 2),
                                        'rate' => (float) $row->commission_rate,
                                        'source_label' => $row->commission_source_label,
                                        'commission' => number_format((float) $row->latest_commission->commission_amount, 2),
                                        'brix' => number_format((float) $row->latest_commission->brix_amount, 2),
                                        'status' => $row->status,
                                        'date' => $row->latest_commission->transaction_date->format('M j, Y'),
                                    ])"
                                    class="cursor-pointer hover:bg-ink-50"
                                >
                                    <td class="px-5 py-3.5 font-medium text-ink-900">{{ $row->store->name }}</td>
                                    <td class="px-5 py-3.5 text-ink-600">{{ $row->store->plan }}</td>
                                    <td class="px-5 py-3.5 text-right text-ink-700">₹{{ number_format($row->gross_revenue, 2) }}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="font-medium text-ink-900">{{ (float) $row->commission_rate }}%</span>
                                        <span class="ml-1 text-xs text-ink-400">{{ $row->commission_source_label }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-medium text-ink-900">₹{{ number_format($row->commission_earned, 2) }}</td>
                                    <td class="px-5 py-3.5 text-right text-amber-700">₹{{ number_format($row->pending_amount, 2) }}</td>
                                    <td class="px-5 py-3.5 text-right font-medium text-emerald-700">₹{{ number_format($row->available_amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Commission detail modal --}}
        <div
            x-show="selected"
            x-cloak
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="fixed inset-0 z-50 overflow-y-auto"
        >
            <div class="fixed inset-0 bg-ink-950/40" x-on:click="selected = null"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div
                    x-show="selected"
                    x-transition:enter="ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="relative w-full max-w-md rounded-2xl border border-ink-200/70 bg-white p-6 shadow-panel"
                    x-on:click.stop
                    x-cloak
                >
                    <template x-if="selected">
                        <div>
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-base font-semibold text-ink-900" x-text="selected.store"></h3>
                                    <p class="mt-0.5 text-xs text-ink-500" x-text="selected.domain"></p>
                                </div>
                                <button type="button" x-on:click="selected = null" class="text-ink-400 hover:text-ink-700">
                                    <x-lucide-x class="h-4 w-4" />
                                </button>
                            </div>

                            <dl class="mt-5 space-y-3 text-sm">
                                <div class="flex items-center justify-between">
                                    <dt class="text-ink-500">Plan</dt>
                                    <dd class="font-medium text-ink-900" x-text="selected.plan"></dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-ink-500">Gross Revenue</dt>
                                    <dd class="font-medium text-ink-900" x-text="'₹' + selected.gross"></dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-ink-500">Commission Rate</dt>
                                    <dd class="font-medium text-ink-900" x-text="selected.rate + '%'"></dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-ink-500">Commission Source</dt>
                                    <dd class="font-medium text-ink-900" x-text="selected.source_label"></dd>
                                </div>
                                <div class="flex items-center justify-between border-t border-ink-100 pt-3">
                                    <dt class="text-ink-500">Agency Commission</dt>
                                    <dd class="font-semibold text-emerald-600" x-text="'₹' + selected.commission"></dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-ink-500">BRIX Revenue</dt>
                                    <dd class="font-medium text-ink-900" x-text="'₹' + selected.brix"></dd>
                                </div>
                                <div class="flex items-center justify-between border-t border-ink-100 pt-3">
                                    <dt class="text-ink-500">Commission Status</dt>
                                    <dd>
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                                            :class="selected.status === 'available' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full" :class="selected.status === 'available' ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                            <span x-text="selected.status"></span>
                                        </span>
                                    </dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-ink-500">Transaction Date</dt>
                                    <dd class="font-medium text-ink-900" x-text="selected.date"></dd>
                                </div>
                            </dl>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
