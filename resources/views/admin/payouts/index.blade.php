@php use App\Support\Currency; @endphp
<x-admin-layout title="Payouts">
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <x-metric-card label="Pending Requests" :value="$kpis['pending_requests']" icon="clock" />
        <x-metric-card label="Under Review" :value="$kpis['under_review']" icon="eye" />
        <x-metric-card label="Awaiting Transfer" :value="$kpis['approved_awaiting_transfer']" icon="circle-check" />
        <x-metric-card label="Paid This Month" :value="Currency::format($kpis['paid_this_month'])" icon="calendar" />
        <x-metric-card label="Total Paid" :value="Currency::format($kpis['total_paid'])" icon="circle-check-big" />
        <x-metric-card label="Total Requested" :value="Currency::format($kpis['total_requested'])" icon="wallet" />
    </div>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap gap-2">
            @foreach (['queue' => 'Queue', 'pending' => 'Requested', 'under_review' => 'Under Review', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
                <a
                    href="{{ route('admin.payouts.index', array_filter(['status' => $key, 'search' => $search, 'agency_id' => $agencyId])) }}"
                    class="rounded-lg px-3.5 py-2 text-sm font-medium {{ $status === $key ? 'bg-ink-900 text-white' : 'bg-white text-ink-600 border border-ink-200 hover:bg-ink-50' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.payouts.index') }}" class="flex gap-2">
            <input type="hidden" name="status" value="{{ $status }}">
            <select name="agency_id" onchange="this.form.submit()" class="rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-700">
                <option value="">All Agencies</option>
                @foreach ($agencies as $agency)
                    <option value="{{ $agency->id }}" @selected((string) $agencyId === (string) $agency->id)>{{ $agency->name }}</option>
                @endforeach
            </select>
            <input
                type="text"
                name="search"
                value="{{ $search }}"
                placeholder="Search request ID, agency, transfer ref…"
                class="w-64 rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
            >
            <button type="submit" class="rounded-lg bg-ink-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-ink-800">Search</button>
        </form>
    </div>

    <div class="rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-100 text-left text-xs font-medium uppercase tracking-wide text-ink-400">
                        <th class="px-5 py-3">Request ID</th>
                        <th class="px-5 py-3">Agency</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Commissions</th>
                        <th class="px-5 py-3">Requested</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Method</th>
                        <th class="px-5 py-3">Age</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($payouts as $payout)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3"><a href="{{ route('admin.payouts.show', $payout) }}" class="font-medium text-ink-900 hover:text-brix-600">{{ $payout->payout_code }}</a></td>
                            <td class="px-5 py-3 text-ink-600">{{ $payout->partner->name ?? '—' }}</td>
                            <td class="px-5 py-3 font-medium text-ink-900">{{ Currency::format((float) $payout->amount, $payout->currency) }}</td>
                            <td class="px-5 py-3 text-ink-500">{{ $payout->commissions_count + $payout->referral_commissions_count }}</td>
                            <td class="px-5 py-3 text-ink-400">{{ $payout->requested_at?->format('M j, Y') }}</td>
                            <td class="px-5 py-3">
                                <x-status-badge :status="match($payout->status) { 'paid' => 'paid', 'pending', 'under_review', 'approved' => 'attention', 'processing' => 'in_payout', 'rejected', 'failed' => 'offline', default => 'inactive' }" :label="$payout->status_label" />
                            </td>
                            <td class="px-5 py-3 text-ink-500">{{ $payout->payment_method_label }}</td>
                            <td class="px-5 py-3 text-ink-400">{{ $payout->requested_at?->diffForHumans(null, true) }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.payouts.show', $payout) }}" class="text-xs font-medium text-brix-600 hover:text-brix-700">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-10 text-center text-ink-400">No payouts here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-ink-100 p-5">
            {{ $payouts->links() }}
        </div>
    </div>
</x-admin-layout>
