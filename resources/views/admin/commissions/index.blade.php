@php use App\Support\Currency; @endphp
<x-admin-layout title="Commissions">
    <div class="mb-5">
        <h2 class="text-lg font-semibold text-ink-900">Referral commissions</h2>
        <p class="text-sm text-ink-500">Every agency's earned commission from verified referral revenue.</p>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
        @foreach (['pending' => 'Pending', 'eligible' => 'Eligible', 'in_payout' => 'In Payout', 'paid' => 'Paid'] as $key => $label)
            <div class="rounded-2xl border border-ink-200/70 bg-white p-4 shadow-subtle">
                <p class="text-xs font-medium uppercase tracking-wide text-ink-400">{{ $label }}</p>
                <p class="mt-1 text-lg font-semibold text-ink-900">
                    @forelse ($summary[$key] ?? [] as $row)
                        {{ Currency::format((float) $row->total, $row->currency) }}
                    @empty
                        —
                    @endforelse
                </p>
            </div>
        @endforeach
    </div>

    <form method="GET" class="mb-5 flex flex-wrap items-center gap-3">
        <select name="agency" onchange="this.form.submit()" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
            <option value="">All agencies</option>
            @foreach ($agencies as $agency)
                <option value="{{ $agency->id }}" @selected(($filters['agency'] ?? '') == $agency->id)>{{ $agency->name }}</option>
            @endforeach
        </select>
        <select name="status" onchange="this.form.submit()" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
            @endforeach
        </select>
    </form>

    <div class="rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-100 text-left text-xs font-medium uppercase tracking-wide text-ink-400">
                        <th class="px-5 py-3">Reference</th>
                        <th class="px-5 py-3">Agency</th>
                        <th class="px-5 py-3">Store</th>
                        <th class="px-5 py-3">Referral link</th>
                        <th class="px-5 py-3 text-right">Revenue</th>
                        <th class="px-5 py-3">Rate</th>
                        <th class="px-5 py-3 text-right">Commission</th>
                        <th class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($commissions as $commission)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3 text-ink-500">REF-{{ $commission->id }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ $commission->agency->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-ink-900">{{ $commission->store->store_name ?? $commission->store->shop_domain ?? '—' }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ $commission->trackingLink->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-right text-ink-600">{{ Currency::format((float) $commission->revenue_amount, $commission->currency) }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ (float) $commission->commission_rate }}%</td>
                            <td class="px-5 py-3 text-right font-medium text-ink-900">{{ Currency::format((float) $commission->commission_amount, $commission->currency) }}</td>
                            <td class="px-5 py-3">
                                <x-status-badge
                                    :status="match($commission->status) { 'paid' => 'paid', 'in_payout' => 'in_payout', 'eligible' => 'active', 'reversed', 'cancelled' => 'offline', default => 'attention' }"
                                    :label="ucfirst(str_replace('_', ' ', $commission->status))"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-10 text-center text-ink-400">No commissions found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-ink-100 p-5">{{ $commissions->links() }}</div>
    </div>
</x-admin-layout>
