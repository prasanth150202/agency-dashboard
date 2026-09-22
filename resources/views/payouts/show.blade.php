@php
    use App\Support\Currency;

    $steps = [
        ['label' => 'Requested', 'done' => (bool) $payout->requested_at, 'at' => $payout->requested_at],
        ['label' => 'Under Review', 'done' => in_array($payout->status, ['under_review', 'approved', 'processing', 'paid']), 'at' => $payout->reviewed_at],
        ['label' => 'Approved', 'done' => in_array($payout->status, ['approved', 'processing', 'paid']), 'at' => $payout->approved_at],
        ['label' => 'Paid', 'done' => $payout->status === 'paid', 'at' => $payout->paid_at],
    ];
@endphp

<x-app-layout :title="$payout->payout_code">
    <a href="{{ route('payouts') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-900">
        <x-lucide-arrow-left class="h-4 w-4" />
        Payouts
    </a>

    @if (session('success'))
        <div class="mx-auto mt-4 max-w-2xl rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @elseif (session('error'))
        <div class="mx-auto mt-4 max-w-2xl rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ session('error') }}</div>
    @endif

    <div class="mx-auto mt-4 max-w-2xl space-y-4">
        <div class="rounded-2xl border border-ink-200/70 bg-white p-6 shadow-subtle">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight text-ink-900">{{ $payout->payout_code }}</h2>
                    <p class="mt-1 text-2xl font-semibold text-ink-900">{{ Currency::format($payout->amount, $payout->currency) }}</p>
                </div>
                <x-status-badge
                    :status="match($payout->status) {
                        'paid' => 'active',
                        'pending', 'under_review', 'approved', 'processing' => 'attention',
                        'rejected', 'cancelled', 'failed' => 'offline',
                        default => 'inactive',
                    }"
                    :label="match($payout->status) { 'pending' => 'Pending Review', 'under_review' => 'Under Review', default => $payout->status_label }"
                />
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-ink-100 pt-6 text-sm">
                <div>
                    <dt class="text-ink-500">Requested</dt>
                    <dd class="mt-1 font-medium text-ink-900">{{ $payout->requested_at?->format('M j, Y, g:i A') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-ink-500">Payment Method</dt>
                    <dd class="mt-1 font-medium text-ink-900">{{ $payout->payment_method_label }}</dd>
                </div>
                <div>
                    <dt class="text-ink-500">Bank Account</dt>
                    <dd class="mt-1 font-medium text-ink-900">{{ $payout->payoutAccount?->masked_account ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-ink-500">Currency</dt>
                    <dd class="mt-1 font-medium text-ink-900">{{ $payout->currency }}</dd>
                </div>
                @if ($payout->paid_at)
                    <div>
                        <dt class="text-ink-500">Payment Date</dt>
                        <dd class="mt-1 font-medium text-ink-900">{{ $payout->paid_at->format('M j, Y') }}</dd>
                    </div>
                @endif
                @if ($payout->notes)
                    <div class="col-span-2">
                        <dt class="text-ink-500">Payment Request Notes</dt>
                        <dd class="mt-1 font-medium text-ink-900">{{ $payout->notes }}</dd>
                    </div>
                @endif
            </dl>

            @if ($payout->status === 'pending')
                <div class="mt-6 border-t border-ink-100 pt-6" x-data="{ cancelling: false }">
                    <form method="POST" action="{{ route('payouts.cancel', $payout) }}" x-on:submit="cancelling = true">
                        @csrf
                        <button
                            type="submit"
                            x-bind:disabled="cancelling"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 px-3.5 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Cancel Payout Request
                        </button>
                    </form>
                </div>
            @endif

            @if ($payout->status === 'rejected')
                <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-rose-600">Rejected Reason</p>
                    <p class="mt-1 text-sm text-rose-800">{{ $payout->rejection_reason ?? 'Not specified' }}</p>
                </div>
            @elseif ($payout->status === 'cancelled')
                <div class="mt-6 rounded-xl border border-ink-200 bg-ink-50 p-4 text-sm text-ink-600">
                    Cancelled by you on {{ $payout->cancelled_at?->format('M j, Y, g:i A') }}.
                </div>
            @else
                <div class="mt-6 border-t border-ink-100 pt-6">
                    <p class="mb-4 text-sm font-semibold text-ink-900">Timeline</p>
                    <ol class="space-y-4">
                        @foreach ($steps as $step)
                            <li class="flex items-start gap-3">
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full {{ $step['done'] ? 'bg-emerald-500 text-white' : 'border-2 border-ink-200 bg-white' }}">
                                    @if ($step['done'])
                                        <x-lucide-check class="h-3 w-3" />
                                    @endif
                                </span>
                                <div>
                                    <p class="text-sm font-medium {{ $step['done'] ? 'text-ink-900' : 'text-ink-400' }}">{{ $step['label'] }}</p>
                                    @if ($step['at'])
                                        <p class="text-xs text-ink-400">{{ $step['at']->format('M j, g:i A') }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </div>

        <div class="overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
            <div class="border-b border-ink-100 px-5 py-4">
                <h3 class="text-sm font-semibold text-ink-900">Commission Breakdown</h3>
            </div>

            @if ($payout->commissions->isEmpty() && $payout->referralCommissions->isEmpty())
                <p class="px-5 py-6 text-sm text-ink-400">No commission breakdown available for this payout.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                                <th class="px-5 py-3">Commission</th>
                                <th class="px-5 py-3">Store</th>
                                <th class="px-5 py-3">Date</th>
                                <th class="px-5 py-3 text-right">Rate</th>
                                <th class="px-5 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($payout->commissions as $commission)
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-3 font-medium text-ink-900">TXN-{{ $commission->id }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-ink-600">{{ $commission->store->name ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-ink-500">{{ $commission->created_at?->format('M j, Y') }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-ink-500">{{ $commission->commission_rate }}%</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-medium text-ink-900">{{ Currency::format($commission->pivot->amount, $payout->currency) }}</td>
                                </tr>
                            @endforeach
                            @foreach ($payout->referralCommissions as $commission)
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-3 font-medium text-ink-900">REF-{{ $commission->id }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-ink-600">{{ $commission->store->store_name ?? $commission->store->shop_domain ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-ink-500">{{ $commission->created_at?->format('M j, Y') }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-ink-500">{{ $commission->commission_rate }}%</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-medium text-ink-900">{{ Currency::format($commission->pivot->amount, $commission->currency) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
            <div class="border-b border-ink-100 px-5 py-4">
                <h3 class="text-sm font-semibold text-ink-900">Bank Transfer Information</h3>
            </div>
            @if ($payout->payoutAccount)
                <dl class="grid grid-cols-2 gap-4 px-5 py-4 text-sm">
                    <div><dt class="text-ink-500">Account Holder</dt><dd class="mt-1 font-medium text-ink-900">{{ $payout->payoutAccount->account_holder_name ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Bank</dt><dd class="mt-1 font-medium text-ink-900">{{ $payout->payoutAccount->bank_name ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Account Number</dt><dd class="mt-1 font-medium text-ink-900">{{ $payout->payoutAccount->masked_account ?? '—' }}</dd></div>
                    @if ($payout->payoutAccount->method === 'bank_transfer')
                        <div><dt class="text-ink-500">IFSC</dt><dd class="mt-1 font-medium text-ink-900">{{ $payout->payoutAccount->ifsc_code ?? '—' }}</dd></div>
                    @endif
                </dl>
            @else
                <p class="px-5 py-6 text-sm text-ink-400">No payout account on file.</p>
            @endif
        </div>

        <div class="overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
            <div class="border-b border-ink-100 px-5 py-4">
                <h3 class="text-sm font-semibold text-ink-900">Admin Payment Information</h3>
            </div>
            @if ($payout->status === 'paid')
                <dl class="grid grid-cols-2 gap-4 px-5 py-4 text-sm">
                    <div><dt class="text-ink-500">Transfer Reference</dt><dd class="mt-1 font-medium text-ink-900">{{ $payout->transfer_reference ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Transfer Date</dt><dd class="mt-1 font-medium text-ink-900">{{ $payout->transfer_date?->format('M j, Y') ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Paid By</dt><dd class="mt-1 font-medium text-ink-900">BRIX Admin</dd></div>
                    @if ($payout->payment_notes)
                        <div class="col-span-2"><dt class="text-ink-500">Admin Notes</dt><dd class="mt-1 font-medium text-ink-900">{{ $payout->payment_notes }}</dd></div>
                    @endif
                </dl>
            @else
                <p class="px-5 py-6 text-sm text-ink-400">Not paid yet.</p>
            @endif
        </div>
    </div>
</x-app-layout>
