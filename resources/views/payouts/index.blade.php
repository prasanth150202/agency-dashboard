<x-app-layout title="Payout">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Payout</h2>
            <p class="mt-1 text-sm text-ink-500">Earnings and payout history for {{ $organisation->name }}.</p>
        </div>

        <form method="POST" action="{{ route('payouts.request') }}">
            @csrf
            <button
                type="submit"
                @disabled((float) $settings->available_balance <= 0)
                class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brix-700 disabled:cursor-not-allowed disabled:opacity-40"
            >
                <x-lucide-wallet class="h-4 w-4" />
                Request payout
            </button>
        </form>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
            <p class="text-sm font-medium text-ink-500">Available balance</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-ink-900">₹{{ number_format((float) $settings->available_balance) }}</p>
        </div>
        <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
            <p class="text-sm font-medium text-ink-500">Pending</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-ink-900">₹{{ number_format((float) $pending) }}</p>
        </div>
        <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
            <p class="text-sm font-medium text-ink-500">Lifetime earnings</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-ink-900">₹{{ number_format((float) $settings->lifetime_earnings) }}</p>
        </div>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
        <div class="border-b border-ink-100 px-5 py-4">
            <h3 class="text-sm font-semibold text-ink-900">Transactions</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3">Store</th>
                        <th class="px-5 py-3">Description</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3.5 text-ink-700">{{ $transaction->date->format('M j, Y') }}</td>
                            <td class="px-5 py-3.5 text-ink-700">{{ $transaction->store->name ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-ink-500">{{ $transaction->description }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-right font-medium text-ink-900">₹{{ number_format((float) $transaction->amount) }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                <x-status-badge
                                    :status="$transaction->status === 'paid' ? 'active' : ($transaction->status === 'processing' ? 'attention' : 'inactive')"
                                    :label="ucfirst($transaction->status)"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-ink-400">No transactions yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($transactions->hasPages())
            <div class="border-t border-ink-100 px-5 py-4">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
