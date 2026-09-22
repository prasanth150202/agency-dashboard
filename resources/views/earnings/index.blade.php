@php
    use App\Services\Referral\ReferralReporting as Money;
    use App\Support\Currency;
    $show = fn (string $bucket) => Money::money($summary[$bucket] ?: [$organisation->currency => 0]);
@endphp
<x-app-layout title="Commissions">
    <div x-data="{ selected: null }">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Commissions</h2>
                <p class="mt-1 text-sm text-ink-500">Track your earnings, view commission details and monitor your available balance.</p>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-metric-card label="Total Earned" :value="$show('total_earned')" icon="trending-up" />
            <x-metric-card label="Pending" :value="$show('pending')" icon="clock" />
            <x-metric-card label="Available" :value="$show('available')" icon="wallet" prominent />
            <x-metric-card label="Paid" :value="$show('paid')" icon="circle-check-big" />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
            {{-- Commission Ledger --}}
            <div class="rounded-2xl border border-ink-200/70 bg-white shadow-subtle lg:col-span-2">
                <div class="border-b border-ink-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-ink-900">Commission Ledger</h3>
                </div>

                <form method="GET" action="{{ route('earnings') }}" class="flex flex-col gap-3 border-b border-ink-100 p-5 lg:flex-row lg:flex-wrap lg:items-center">
                    <div class="relative flex-1 lg:min-w-[180px]">
                        <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                        <input
                            type="search"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Order ID, store, or amount..."
                            x-data
                            x-on:input.debounce.500ms="$el.form.submit()"
                            class="w-full rounded-lg border border-ink-200 bg-white py-2.5 pl-9 pr-3 text-sm text-ink-900 placeholder-ink-400 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                        >
                    </div>

                    <select name="range" x-data x-on:change="$el.form.submit()" class="rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm font-medium text-ink-700 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                        <option value="all_time" @selected($filters['range'] === 'all_time')>All Time</option>
                        <option value="this_month" @selected($filters['range'] === 'this_month')>This Month</option>
                        <option value="last_month" @selected($filters['range'] === 'last_month')>Last Month</option>
                        <option value="last_7_days" @selected($filters['range'] === 'last_7_days')>Last 7 Days</option>
                    </select>

                    <select name="store" x-data x-on:change="$el.form.submit()" class="rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm font-medium text-ink-700 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                        <option value="all" @selected($filters['store'] === 'all')>All Stores</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected((string) $filters['store'] === (string) $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>

                    <select name="source" x-data x-on:change="$el.form.submit()" class="rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm font-medium text-ink-700 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                        <option value="all" @selected($filters['source'] === 'all')>All Sources</option>
                        <option value="store" @selected($filters['source'] === 'store')>Store commissions</option>
                        <option value="referral" @selected($filters['source'] === 'referral')>Referral commissions</option>
                    </select>

                    <select name="status" x-data x-on:change="$el.form.submit()" class="rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm font-medium text-ink-700 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                        <option value="all" @selected($filters['status'] === 'all')>All Statuses</option>
                        @foreach ($statusFilters as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>

                    <a
                        href="{{ route('earnings.export', ['range' => $filters['range']]) }}"
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3.5 py-2.5 text-sm font-medium text-ink-700 hover:bg-ink-50"
                    >
                        <x-lucide-download class="h-4 w-4" />
                        Export CSV
                    </a>
                </form>

                @if ($commissions->isEmpty())
                    <div class="px-5 py-16 text-center">
                        <p class="text-sm font-medium text-ink-700">No commissions yet</p>
                        <p class="mt-1 text-sm text-ink-400">
                            Once your connected stores generate eligible BRIX commissions, they'll appear here.
                        </p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                                    <th class="px-5 py-3">Order ID</th>
                                    <th class="px-5 py-3">Source</th>
                                    <th class="px-5 py-3">Store</th>
                                    <th class="px-5 py-3 text-right">Order Amount</th>
                                    <th class="px-5 py-3">Rate</th>
                                    <th class="px-5 py-3 text-right">Commission</th>
                                    <th class="px-5 py-3">Date</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100">
                                @foreach ($commissions as $entry)
                                    <tr class="hover:bg-ink-50">
                                        <td class="whitespace-nowrap px-5 py-3.5 font-medium text-ink-900">{{ $entry->reference }}</td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-xs font-medium text-ink-500">{{ $entry->sourceLabel() }}</td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-ink-700">{{ $entry->storeName }}</td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-right text-ink-700">{{ Currency::format($entry->baseAmount, $entry->currency) }}</td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-ink-600">{{ $entry->rate }}%</td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-right font-medium text-ink-900">{{ Currency::format($entry->amount, $entry->currency) }}</td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-ink-600">{{ $entry->date->format('d M Y') }}</td>
                                        <td class="whitespace-nowrap px-5 py-3.5">
                                            <x-status-badge :status="$entry->badge" :label="$entry->statusLabel" />
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                            <button
                                                type="button"
                                                x-on:click="selected = @js([
                                                    'order_id' => $entry->reference,
                                                    'store' => $entry->storeName,
                                                    'domain' => $entry->shopDomain,
                                                    'gross' => number_format((float) $entry->baseAmount, 2),
                                                    'rate' => (float) $entry->rate,
                                                    'commission' => number_format((float) $entry->amount, 2),
                                                    'currency' => Currency::symbol($entry->currency),
                                                    'status' => $entry->statusLabel,
                                                    'badge_status' => $entry->badge,
                                                    'source' => $entry->sourceLabel(),
                                                    'via' => $entry->via,
                                                    'created_at' => $entry->date->format('M j, Y'),
                                                    'available_at' => $entry->availableAt?->format('M j, Y g:i A'),
                                                    'available_reached' => (bool) $entry->availableAt?->isPast(),
                                                    'in_payout' => in_array($entry->status, ['in_payout', 'paid'], true),
                                                    'in_payout_at' => $entry->inPayoutAt?->format('M j, Y g:i A'),
                                                    'paid' => $entry->status === 'paid',
                                                    'paid_at' => $entry->paidAt?->format('M j, Y g:i A'),
                                                ])"
                                                class="text-sm font-medium text-brix-600 hover:text-brix-700"
                                            >
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($commissions->hasPages())
                        <div class="border-t border-ink-100 px-5 py-4">
                            {{ $commissions->links() }}
                        </div>
                    @endif
                @endif
            </div>

            {{-- Right rail --}}
            <div class="space-y-4">
                <div x-data="requestPayoutModal({{ $metrics['available'] }}, '{{ Currency::symbol($organisation->currency) }}')" class="rounded-2xl border-2 border-brix-600 bg-ink-900 p-5 text-white shadow-subtle">
                    <p class="text-sm font-medium text-ink-300">Available Balance</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ Currency::format($metrics['available'], $organisation->currency) }}</p>
                    <p class="mt-2 text-xs text-ink-400">You can request a payout from your available commission balance.</p>

                    <button
                        type="button"
                        x-on:click="open()"
                        @disabled(! $canRequestPayout)
                        class="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brix-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <x-lucide-wallet class="h-4 w-4" />
                        Request Payout
                    </button>

                    <x-request-payout-modal :minimum-payout="$minimumPayout" :available-balance="$metrics['available']" />
                </div>

                <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                    <h3 class="text-sm font-semibold text-ink-900">My Stores</h3>

                    @if ($myStores->isEmpty())
                        <p class="mt-3 text-sm text-ink-400">No stores connected yet.</p>
                    @else
                        <div class="mt-3 space-y-3">
                            @foreach ($myStores as $store)
                                <a href="{{ route('stores.show', $store) }}" class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 -mx-2 hover:bg-ink-50">
                                    <span class="min-w-0 truncate text-sm font-medium text-ink-900">{{ $store->name }}</span>
                                    <span class="flex shrink-0 items-center gap-2">
                                        <span class="text-xs font-medium text-ink-600">{{ Currency::format($store->commission_total ?? 0, $organisation->currency) }}</span>
                                        <x-status-badge :status="$store->status" />
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
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
                                    <h3 class="text-base font-semibold text-ink-900" x-text="selected.order_id"></h3>
                                    <p class="mt-0.5 text-xs text-ink-500" x-text="selected.store + (selected.domain ? ' · ' + selected.domain : '') + (selected.via ? ' · via ' + selected.via : '')"></p>
                                </div>
                                <button type="button" x-on:click="selected = null" class="text-ink-400 hover:text-ink-700">
                                    <x-lucide-x class="h-4 w-4" />
                                </button>
                            </div>

                            <dl class="mt-5 space-y-2.5 text-sm">
                                <div class="flex items-center justify-between">
                                    <dt class="text-ink-500" x-text="selected.source === 'Referral' ? 'Verified Revenue' : 'Order Amount'"></dt>
                                    <dd class="font-medium text-ink-900" x-text="selected.currency + selected.gross"></dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-ink-500">Commission Rate</dt>
                                    <dd class="font-medium text-ink-900" x-text="selected.rate + '%'"></dd>
                                </div>
                                <div class="flex items-center justify-between border-t border-ink-100 pt-2.5">
                                    <dt class="text-ink-500">Commission Amount</dt>
                                    <dd class="font-semibold text-emerald-600" x-text="selected.currency + selected.commission"></dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-ink-500">Current Status</dt>
                                    <dd>
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                                            :class="{
                                                'bg-emerald-50 text-emerald-700': selected.badge_status === 'active',
                                                'bg-amber-50 text-amber-700': selected.badge_status === 'attention',
                                                'bg-rose-50 text-rose-700': selected.badge_status === 'offline',
                                                'bg-ink-100 text-ink-500': selected.badge_status === 'inactive',
                                                'bg-violet-50 text-violet-700': selected.badge_status === 'eligible',
                                                'bg-blue-50 text-blue-700': selected.badge_status === 'paid',
                                                'bg-cyan-50 text-cyan-700': selected.badge_status === 'in_payout',
                                            }"
                                            x-text="selected.status"
                                        ></span>
                                    </dd>
                                </div>
                            </dl>

                            <div class="mt-5 border-t border-ink-100 pt-4">
                                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-ink-400">Timeline</p>
                                <ol class="space-y-3">
                                    <li class="flex items-start gap-2.5">
                                        <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white">
                                            <x-lucide-check class="h-2.5 w-2.5" />
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium text-ink-900">Order Created &amp; Commission Calculated</p>
                                            <p class="text-xs text-ink-400" x-text="selected.created_at"></p>
                                        </div>
                                    </li>
                                    <li class="flex items-start gap-2.5">
                                        <span
                                            class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full"
                                            :class="selected.available_reached ? 'bg-emerald-500 text-white' : 'border-2 border-ink-200 bg-white'"
                                        >
                                            <x-lucide-check x-show="selected.available_reached" class="h-2.5 w-2.5" />
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium" :class="selected.available_reached ? 'text-ink-900' : 'text-ink-400'">Available for Payout</p>
                                            <p class="text-xs text-ink-400" x-text="selected.available_reached ? selected.available_at : 'Pending holding period'"></p>
                                        </div>
                                    </li>
                                    <li class="flex items-start gap-2.5">
                                        <span
                                            class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full"
                                            :class="selected.in_payout ? 'bg-emerald-500 text-white' : 'border-2 border-ink-200 bg-white'"
                                        >
                                            <x-lucide-check x-show="selected.in_payout" class="h-2.5 w-2.5" />
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium" :class="selected.in_payout ? 'text-ink-900' : 'text-ink-400'">Included in Payout</p>
                                            <p class="text-xs text-ink-400" x-show="selected.in_payout_at" x-text="selected.in_payout_at"></p>
                                        </div>
                                    </li>
                                    <li class="flex items-start gap-2.5">
                                        <span
                                            class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full"
                                            :class="selected.paid ? 'bg-emerald-500 text-white' : 'border-2 border-ink-200 bg-white'"
                                        >
                                            <x-lucide-check x-show="selected.paid" class="h-2.5 w-2.5" />
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium" :class="selected.paid ? 'text-ink-900' : 'text-ink-400'">Paid</p>
                                            <p class="text-xs text-ink-400" x-show="selected.paid_at" x-text="selected.paid_at"></p>
                                        </div>
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
