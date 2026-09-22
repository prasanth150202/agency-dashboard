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
                        <p class="text-xs text-ink-400">{{ $payout->partner->owner_email ?? '' }}</p>
                    </div>
                    <x-status-badge :status="match($payout->status) { 'paid' => 'paid', 'pending', 'under_review', 'approved' => 'attention', 'processing' => 'in_payout', 'rejected', 'failed' => 'offline', default => 'inactive' }" :label="$payout->status_label" />
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-ink-400">Amount Requested</dt><dd class="mt-0.5 font-medium text-ink-900">{{ \App\Support\Currency::format((float) $payout->amount, $payout->currency) }}</dd></div>
                    <div><dt class="text-ink-400">Currency</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $payout->currency }}</dd></div>
                    <div><dt class="text-ink-400">Method</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $payout->payment_method_label }}</dd></div>
                    <div><dt class="text-ink-400">Commissions</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $payout->commissions->count() + $payout->referralCommissions->count() }}</dd></div>
                    <div><dt class="text-ink-400">Requested</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $payout->requested_at?->format('M j, Y, g:i A') }}</dd></div>
                    @if ($payout->reviewed_at)
                        <div><dt class="text-ink-400">Reviewed</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $payout->reviewed_at->format('M j, Y') }} by {{ $payout->reviewedByAdmin->name ?? '—' }}</dd></div>
                    @endif
                    @if ($payout->approved_at)
                        <div><dt class="text-ink-400">Approved</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $payout->approved_at->format('M j, Y') }} by {{ $payout->approvedByAdmin->name ?? '—' }}</dd></div>
                    @endif
                    @if ($payout->paid_at)
                        <div><dt class="text-ink-400">Paid</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $payout->paid_at->format('M j, Y') }} by {{ $payout->paidByAdmin->name ?? '—' }}</dd></div>
                    @endif
                    @if ($payout->rejected_at)
                        <div><dt class="text-ink-400">Rejected</dt><dd class="mt-0.5 font-medium text-rose-700">{{ $payout->rejected_at->format('M j, Y') }} by {{ $payout->rejectedByAdmin->name ?? '—' }}</dd></div>
                    @endif
                    @if ($payout->notes)
                        <div class="col-span-2 sm:col-span-3"><dt class="text-ink-400">Payment Request Notes</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $payout->notes }}</dd></div>
                    @endif
                    @if ($payout->rejection_reason)
                        <div class="col-span-2 sm:col-span-3"><dt class="text-ink-400">Rejection Reason</dt><dd class="mt-0.5 font-medium text-rose-700">{{ $payout->rejection_reason }}</dd></div>
                    @endif
                </dl>

                @if ($payout->payoutAccount)
                    <div class="mt-4 border-t border-ink-100 pt-4 text-sm">
                        <p class="text-ink-400">Bank Account</p>
                        <dl class="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <div><dt class="text-xs text-ink-400">Account Holder</dt><dd class="font-medium text-ink-900">{{ $payout->payoutAccount->account_holder_name ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-ink-400">Bank</dt><dd class="font-medium text-ink-900">{{ $payout->payoutAccount->bank_name ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-ink-400">Account No.</dt><dd class="font-medium text-ink-900">{{ $payout->payoutAccount->masked_account ?? '—' }}</dd></div>
                            @if ($payout->payoutAccount->ifsc_code)
                                <div><dt class="text-xs text-ink-400">IFSC</dt><dd class="font-medium text-ink-900">{{ $payout->payoutAccount->ifsc_code }}</dd></div>
                            @endif
                            @if ($payout->payoutAccount->account_type_label)
                                <div><dt class="text-xs text-ink-400">Account Type</dt><dd class="font-medium text-ink-900">{{ $payout->payoutAccount->account_type_label }}</dd></div>
                            @endif
                            @if ($payout->payoutAccount->upi_id)
                                <div><dt class="text-xs text-ink-400">UPI</dt><dd class="font-medium text-ink-900">{{ $payout->payoutAccount->upi_id }}</dd></div>
                            @endif
                        </dl>
                    </div>
                @endif

                @if ($payout->status === 'paid')
                    <div class="mt-4 border-t border-ink-100 pt-4 text-sm">
                        <p class="text-ink-400">Manual Transfer</p>
                        <dl class="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <div><dt class="text-xs text-ink-400">Transfer Reference</dt><dd class="font-medium text-ink-900">{{ $payout->transfer_reference ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-ink-400">Transfer Date</dt><dd class="font-medium text-ink-900">{{ $payout->transfer_date?->format('M j, Y') ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-ink-400">Paid Amount</dt><dd class="font-medium text-ink-900">{{ \App\Support\Currency::format((float) $payout->paid_amount, $payout->paid_currency ?? $payout->currency) }}</dd></div>
                            @if ($payout->payment_notes)
                                <div class="col-span-2 sm:col-span-3"><dt class="text-xs text-ink-400">Payment Notes</dt><dd class="font-medium text-ink-900">{{ $payout->payment_notes }}</dd></div>
                            @endif
                        </dl>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <h3 class="text-sm font-semibold text-ink-900">Claimed Commissions</h3>
                <div class="mt-3 divide-y divide-ink-100">
                    @forelse ($payout->commissions as $commission)
                        <div class="flex items-center justify-between py-2.5 text-sm">
                            <div>
                                <p class="font-medium text-ink-900">{{ $commission->store->name ?? $commission->store->store_name ?? '—' }}</p>
                                <p class="text-xs text-ink-400">TXN-{{ $commission->id }} · {{ $commission->created_at?->format('M j, Y') }} · rate {{ $commission->commission_rate }}%</p>
                            </div>
                            <div class="text-right">
                                <p class="font-medium text-ink-900">{{ \App\Support\Currency::format((float) $commission->pivot->amount, $payout->currency) }}</p>
                                <p class="text-xs text-ink-400">of {{ \App\Support\Currency::format((float) $commission->gross_amount, $payout->currency) }} revenue</p>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-ink-400">No commissions linked.</p>
                    @endforelse
                </div>
            </div>

            @if ($payout->referralCommissions->isNotEmpty())
                <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                    <h3 class="text-sm font-semibold text-ink-900">Claimed Referral Commissions</h3>
                    <div class="mt-3 divide-y divide-ink-100">
                        @foreach ($payout->referralCommissions as $commission)
                            <div class="flex items-center justify-between py-2.5 text-sm">
                                <div>
                                    <p class="font-medium text-ink-900">{{ $commission->store->store_name ?? $commission->store->shop_domain ?? '—' }}</p>
                                    <p class="text-xs text-ink-400">REF-{{ $commission->id }} · {{ $commission->revenue_type }} · rate {{ $commission->commission_rate }}% · {{ $commission->created_at?->format('M j, Y') }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium text-ink-900">{{ \App\Support\Currency::format((float) $commission->pivot->amount, $commission->currency) }}</p>
                                    <p class="text-xs text-ink-400">of {{ \App\Support\Currency::format((float) $commission->revenue_amount, $commission->currency) }} revenue</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div>
            @auth('admin')
                @if (auth('admin')->user()->isFinance())
                    <div class="space-y-3 rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                        <h3 class="text-sm font-semibold text-ink-900">Actions</h3>

                        @if ($payout->status === 'pending')
                            <form method="POST" action="{{ route('admin.payouts.review', $payout) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-lg border border-ink-200 px-4 py-2.5 text-sm font-medium text-ink-700 hover:bg-ink-50">
                                    Start Review
                                </button>
                            </form>
                        @endif

                        @if (in_array($payout->status, ['pending', 'under_review']))
                            <form method="POST" action="{{ route('admin.payouts.approve', $payout) }}" x-data="{ open: false }">
                                @csrf
                                <div x-show="!open">
                                    <button type="button" x-on:click="open = true" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
                                        Approve
                                    </button>
                                </div>
                                <div x-show="open" x-cloak class="space-y-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800">
                                    <p>Approve {{ \App\Support\Currency::format((float) $payout->amount, $payout->currency) }} for {{ $payout->partner->name ?? 'this agency' }} via {{ $payout->payment_method_label }}, covering {{ $payout->commissions->count() + $payout->referralCommissions->count() }} commission(s)?</p>
                                    <button type="submit" class="w-full rounded-lg bg-emerald-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-emerald-700">Confirm Approval</button>
                                </div>
                            </form>
                        @endif

                        @if ($payout->status === 'approved')
                            <div x-data="{ open: false }">
                                <button type="button" x-on:click="open = true" x-show="!open" class="w-full rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700">
                                    Record Manual Payment
                                </button>

                                <form x-show="open" x-cloak method="POST" action="{{ route('admin.payouts.mark-paid', $payout) }}" class="space-y-2.5">
                                    @csrf
                                    <p class="text-xs text-ink-500">Approved amount: <span class="font-medium text-ink-900">{{ \App\Support\Currency::format((float) $payout->amount, $payout->currency) }}</span></p>

                                    @error('currency')
                                        <p class="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ $message }}</p>
                                    @enderror
                                    @error('paid_amount')
                                        <p class="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ $message }}</p>
                                    @enderror

                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-ink-600">Transfer Reference / Transaction ID</label>
                                        <input type="text" name="transfer_reference" required value="{{ old('transfer_reference') }}" class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-ink-600">Transfer Date</label>
                                        <input type="date" name="transfer_date" required value="{{ old('transfer_date', now()->toDateString()) }}" class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-ink-600">Paid Amount</label>
                                        <input type="number" step="0.01" name="paid_amount" required value="{{ old('paid_amount', $payout->amount) }}" class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-ink-600">Currency</label>
                                        <input type="text" name="currency" required maxlength="3" value="{{ old('currency', $payout->currency) }}" class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm uppercase outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-ink-600">Payment Notes (optional)</label>
                                        <textarea name="payment_notes" rows="2" class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">{{ old('payment_notes') }}</textarea>
                                    </div>
                                    <label class="flex items-start gap-2 text-xs text-ink-600">
                                        <input type="checkbox" name="confirm" value="1" required class="mt-0.5">
                                        I confirm that this manual bank transfer has been completed.
                                    </label>
                                    <button type="submit" class="w-full rounded-lg bg-brix-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-brix-700">
                                        Mark Paid
                                    </button>
                                </form>
                            </div>
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
