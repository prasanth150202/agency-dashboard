<x-admin-layout title="Payout {{ $payout->payout_code }}">
    <div class="mb-4">
        <a href="{{ route('admin.payouts.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink-700">&larr; All payouts</a>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-ink-900">{{ $payout->payout_code }}</h2>
                        <p class="text-sm text-ink-500">{{ $payout->partner->name ?? '—' }}</p>
                    </div>
                    <x-status-badge :status="match($payout->status) { 'paid' => 'paid', 'pending', 'approved' => 'attention', 'processing' => 'in_payout', 'rejected', 'failed' => 'offline', default => 'inactive' }" :label="$payout->status_label" />
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-ink-400">Amount</dt><dd class="mt-0.5 font-medium text-ink-900">{{ \App\Support\Currency::format((float) $payout->amount, $payout->currency) }}</dd></div>
                    <div><dt class="text-ink-400">Method</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $payout->payment_method_label }}</dd></div>
                    <div><dt class="text-ink-400">Requested</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $payout->requested_at?->format('M j, Y') }}</dd></div>
                    @if ($payout->approved_at)
                        <div><dt class="text-ink-400">Approved</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $payout->approved_at->format('M j, Y') }}</dd></div>
                    @endif
                    @if ($payout->paid_at)
                        <div><dt class="text-ink-400">Paid</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $payout->paid_at->format('M j, Y') }}</dd></div>
                    @endif
                    @if ($payout->rejection_reason)
                        <div class="col-span-2"><dt class="text-ink-400">Rejection Reason</dt><dd class="mt-0.5 font-medium text-rose-700">{{ $payout->rejection_reason }}</dd></div>
                    @endif
                </dl>

                @if ($payout->payoutAccount)
                    <div class="mt-4 border-t border-ink-100 pt-4 text-sm">
                        <p class="text-ink-400">Payout Account</p>
                        <p class="mt-0.5 font-medium text-ink-900">{{ $payout->payoutAccount->method_label }} — {{ $payout->payoutAccount->masked_account ?? '—' }}</p>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <h3 class="text-sm font-semibold text-ink-900">Claimed Commissions</h3>
                <div class="mt-3 divide-y divide-ink-100">
                    @forelse ($payout->commissions as $commission)
                        <div class="flex items-center justify-between py-2.5 text-sm">
                            <div>
                                <p class="font-medium text-ink-900">{{ $commission->store->name ?? '—' }}</p>
                                <p class="text-xs text-ink-400">TXN-{{ $commission->id }}</p>
                            </div>
                            <p class="font-medium text-ink-900">{{ \App\Support\Currency::format((float) $commission->pivot->amount) }}</p>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-ink-400">No commissions linked.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div>
            @auth('admin')
                @if (auth('admin')->user()->isFinance())
                    <div class="space-y-3 rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                        <h3 class="text-sm font-semibold text-ink-900">Actions</h3>

                        @if ($payout->status === 'pending')
                            <form method="POST" action="{{ route('admin.payouts.approve', $payout) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
                                    Approve
                                </button>
                            </form>
                        @endif

                        @if (in_array($payout->status, ['approved', 'processing']))
                            <form method="POST" action="{{ route('admin.payouts.mark-paid', $payout) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700">
                                    Mark Paid
                                </button>
                            </form>
                        @endif

                        @if (! in_array($payout->status, ['paid', 'rejected', 'cancelled']))
                            <form method="POST" action="{{ route('admin.payouts.reject', $payout) }}" x-data="{ open: false }">
                                @csrf
                                <div x-show="!open">
                                    <button type="button" x-on:click="open = true" class="w-full rounded-lg border border-rose-200 px-4 py-2.5 text-sm font-medium text-rose-700 hover:bg-rose-50">
                                        Reject
                                    </button>
                                </div>
                                <div x-show="open" x-cloak class="space-y-2">
                                    <textarea name="reason" required placeholder="Reason for rejection..." class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-rose-400 focus:ring-2 focus:ring-rose-100"></textarea>
                                    <button type="submit" class="w-full rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-rose-700">
                                        Confirm Rejection
                                    </button>
                                </div>
                            </form>
                        @endif

                        @if (in_array($payout->status, ['paid', 'rejected', 'cancelled']))
                            <p class="text-sm text-ink-400">No further action available.</p>
                        @endif
                    </div>
                @endif
            @endauth
        </div>
    </div>
</x-admin-layout>
