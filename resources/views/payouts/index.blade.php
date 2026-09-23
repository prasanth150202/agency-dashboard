@php use App\Support\Currency; @endphp
<x-app-layout title="Payouts">
    <div x-data="requestPayoutModal({{ $metrics['available'] }}, '{{ Currency::symbol($organisation->currency) }}')">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Payouts</h2>
                <p class="mt-1 text-sm text-ink-500">Request and track your agency payouts.</p>
            </div>

            <div>
                <button
                    type="button"
                    x-on:click="open()"
                    @disabled(! $canRequestPayout)
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brix-700 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <x-lucide-wallet class="h-4 w-4" />
                    Request Payout
                </button>
            </div>
        </div>

        @if (! $canRequestPayout && ! $hasActiveRequest)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Available balance: {{ Currency::format($metrics['available'], $organisation->currency) }} · Minimum payout amount: {{ Currency::format($minimumPayout, $organisation->currency) }}
                @php $remaining = max(0, $minimumPayout - $metrics['available']); @endphp
                @if ($remaining > 0)
                    · Earn {{ Currency::format($remaining, $organisation->currency) }} more to request a payout.
                @endif
            </div>
        @elseif ($hasActiveRequest)
            <div class="mt-4 rounded-xl border border-ink-200 bg-ink-50 px-4 py-3 text-sm text-ink-600">
                You already have a payout request in progress. You can request another once it's resolved.
            </div>
        @endif

        <section class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" aria-label="Payout summary">
            <x-kpi-card label="Available for Payout" :value="Currency::format($metrics['available'], $organisation->currency)" context="ready to request" icon="wallet" />
            <x-kpi-card label="Pending Review" :value="Currency::format($metrics['awaiting_review'], $organisation->currency)" context="requested, awaiting review"
                icon="clock" :href="route('payouts', ['filter' => 'pending'])" />
            <x-kpi-card label="Approved" :value="Currency::format($metrics['approved'], $organisation->currency)" context="approved or processing"
                icon="hand-coins" :href="route('payouts', ['filter' => 'approved'])" />
            <x-kpi-card label="Paid" :value="Currency::format($metrics['paid'], $organisation->currency)" context="settled to your account"
                icon="circle-check-big" :href="route('payouts', ['filter' => 'paid'])" />
            <x-kpi-card label="Total Earned" :value="Currency::format($metrics['total_earned'], $organisation->currency)" context="lifetime store commission"
                icon="trending-up" :href="route('earnings')" />
        </section>

        {{-- Payout account summary --}}
        <div class="mt-6 rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-ink-400">Payout Account</p>
                    @if (! $account)
                        <p class="mt-1 flex items-center gap-1.5 text-sm font-medium text-amber-700">
                            <x-lucide-circle-alert class="h-4 w-4" />
                            Not configured
                        </p>
                    @elseif ($account->is_verified)
                        <p class="mt-1 flex items-center gap-1.5 text-sm font-medium text-emerald-700">
                            <x-lucide-circle-check-big class="h-4 w-4" />
                            Verified — {{ $account->method_label }} {{ $account->masked_account }}
                        </p>
                    @else
                        <p class="mt-1 flex items-center gap-1.5 text-sm font-medium text-amber-700">
                            <x-lucide-circle-alert class="h-4 w-4" />
                            Verification required
                        </p>
                    @endif
                </div>
                <a href="{{ route('payout-settings') }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-ink-200 px-3.5 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50">
                    <x-lucide-landmark class="h-4 w-4" />
                    Manage Payout Account
                </a>
            </div>
        </div>

        {{-- Payout History --}}
        <div class="mt-6 overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
            <div class="flex flex-col gap-3 border-b border-ink-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-sm font-semibold text-ink-900">Payout History</h3>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex gap-1.5">
                        @foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected'] as $key => $label)
                            <a
                                href="{{ route('payouts', array_filter(['filter' => $key, 'search' => $search])) }}"
                                class="rounded-lg px-3 py-1.5 text-xs font-medium {{ $filter === $key ? 'bg-ink-900 text-white' : 'bg-ink-50 text-ink-600 hover:bg-ink-100' }}"
                            >
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                    <form method="GET" action="{{ route('payouts') }}" class="flex gap-1.5">
                        <input type="hidden" name="filter" value="{{ $filter }}">
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Search request ID or reference…"
                            class="w-48 rounded-lg border border-ink-200 px-3 py-1.5 text-xs outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                        >
                    </form>
                </div>
            </div>

            @if ($transactions->isEmpty())
                <div class="px-5 py-16 text-center">
                    <p class="text-sm font-medium text-ink-700">No payouts yet</p>
                    <p class="mt-1 text-sm text-ink-400">Your payout history will appear here after you request your first payout.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                                <th class="px-5 py-3">Payout ID</th>
                                <th class="px-5 py-3 text-right">Amount</th>
                                <th class="px-5 py-3">Method</th>
                                <th class="px-5 py-3">Requested</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Reference</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($transactions as $payout)
                                <tr class="hover:bg-ink-50" x-data="{ cancelling: false }">
                                    <td class="whitespace-nowrap px-5 py-3.5 font-medium">
                                        <a href="{{ route('payouts.show', $payout) }}" class="text-brix-600 hover:text-brix-700">
                                            {{ $payout->payout_code ?? '—' }}
                                        </a>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right font-medium text-ink-900">{{ Currency::format($payout->amount, $payout->currency) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-ink-600">{{ $payout->payment_method_label }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-ink-600">{{ $payout->requested_at?->format('M j') ?? $payout->date->format('M j') }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        <x-status-badge
                                            :status="match($payout->status) {
                                                'paid' => 'active',
                                                'pending', 'under_review', 'approved', 'processing' => 'attention',
                                                'rejected', 'cancelled', 'failed' => 'offline',
                                                default => 'inactive',
                                            }"
                                            :label="$payout->status === 'under_review' ? 'Under Review' : $payout->status_label"
                                        />
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-ink-500">{{ $payout->provider_payout_id ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                        @if ($payout->status === 'pending')
                                            <form
                                                method="POST"
                                                action="{{ route('payouts.cancel', $payout) }}"
                                                x-on:submit="cancelling = true"
                                            >
                                                @csrf
                                                <button
                                                    type="submit"
                                                    x-bind:disabled="cancelling"
                                                    class="text-sm font-medium text-rose-600 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    Cancel
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($transactions->hasPages())
                    <div class="border-t border-ink-100 px-5 py-4">
                        {{ $transactions->links() }}
                    </div>
                @endif
            @endif
        </div>

        @php $requestCount = $distribution->sum('count'); @endphp
        @if ($requestCount > 0)
            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <section class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle" aria-labelledby="dist-heading">
                    <h3 id="dist-heading" class="text-sm font-semibold text-ink-900">Requests by status <span class="font-normal text-ink-400">· all time</span></h3>
                    <div class="mt-3 flex h-2.5 overflow-hidden rounded-full bg-ink-100" aria-hidden="true">
                        @foreach ($distribution as $d)
                            @if ($d['count'] > 0)<span class="{{ $d['color'] }}" style="width: {{ $d['count'] / $requestCount * 100 }}%"></span>@endif
                        @endforeach
                    </div>
                    <ul class="mt-3 space-y-0.5">
                        @foreach ($distribution as $key => $d)
                            <li>
                                @php $href = in_array($key, ['pending', 'approved', 'paid', 'rejected'], true) ? route('payouts', ['filter' => $key]) : null; @endphp
                                <{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" @endif class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-sm {{ $href ? 'transition hover:bg-ink-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brix-600' : '' }}">
                                    <span class="flex items-center gap-2 text-ink-700"><span class="h-2 w-2 rounded-full {{ $d['color'] }}" aria-hidden="true"></span>{{ $d['label'] }}</span>
                                    <span class="text-right tabular-nums">
                                        <span class="font-semibold text-ink-900">{{ $d['count'] }}</span>
                                        <span class="block text-[11px] text-ink-400">{{ Currency::format($d['amount'], $organisation->currency) }}</span>
                                    </span>
                                </{{ $href ? 'a' : 'div' }}>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle lg:col-span-2" aria-labelledby="paid-heading">
                    <div class="mb-3 flex items-baseline justify-between">
                        <h3 id="paid-heading" class="text-sm font-semibold text-ink-900">Paid out over time</h3>
                        <span class="text-[11px] text-ink-400">Last 12 months · by month paid</span>
                    </div>
                    <x-trend-chart :chart="$paidChart" height="h-48" empty-title="Nothing paid out in the last 12 months"
                        empty-text="Paid amounts appear here once a payout is marked paid." />
                </section>
            </div>
        @endif

        <x-request-payout-modal :minimum-payout="$minimumPayout" :available-balance="$metrics['available']" />
    </div>
</x-app-layout>
