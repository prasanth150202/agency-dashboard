<x-admin-layout title="Payouts">
    <div class="mb-4 flex gap-2">
        @foreach (['queue' => 'Queue', 'paid' => 'Paid', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
            <a
                href="{{ route('admin.payouts.index', ['status' => $key]) }}"
                class="rounded-lg px-3.5 py-2 text-sm font-medium {{ $status === $key ? 'bg-ink-900 text-white' : 'bg-white text-ink-600 border border-ink-200 hover:bg-ink-50' }}"
            >
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-100 text-left text-xs font-medium uppercase tracking-wide text-ink-400">
                        <th class="px-5 py-3">Code</th>
                        <th class="px-5 py-3">Partner</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Requested</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($payouts as $payout)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3"><a href="{{ route('admin.payouts.show', $payout) }}" class="font-medium text-ink-900 hover:text-brix-600">{{ $payout->payout_code }}</a></td>
                            <td class="px-5 py-3 text-ink-600">{{ $payout->partner->name ?? '—' }}</td>
                            <td class="px-5 py-3 font-medium text-ink-900">{{ \App\Support\Currency::format((float) $payout->amount, $payout->currency) }}</td>
                            <td class="px-5 py-3">
                                <x-status-badge :status="match($payout->status) { 'paid' => 'paid', 'pending', 'approved' => 'attention', 'processing' => 'in_payout', 'rejected', 'failed' => 'offline', default => 'inactive' }" :label="$payout->status_label" />
                            </td>
                            <td class="px-5 py-3 text-ink-400">{{ $payout->requested_at?->format('M j, Y') }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.payouts.show', $payout) }}" class="text-xs font-medium text-brix-600 hover:text-brix-700">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-ink-400">No payouts here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-ink-100 p-5">
            {{ $payouts->links() }}
        </div>
    </div>
</x-admin-layout>
