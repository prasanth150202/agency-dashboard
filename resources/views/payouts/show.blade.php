@php
    $steps = [
        ['label' => 'Payout requested', 'done' => (bool) $payout->requested_at, 'at' => $payout->requested_at],
        ['label' => 'Under review', 'done' => in_array($payout->status, ['processing', 'paid']), 'at' => null],
        ['label' => 'Processing', 'done' => in_array($payout->status, ['processing', 'paid']), 'at' => $payout->processing_at],
        ['label' => 'Paid', 'done' => $payout->status === 'paid', 'at' => $payout->paid_at],
    ];
@endphp

<x-app-layout :title="$payout->payout_code">
    <a href="{{ route('payouts') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-900">
        <x-lucide-arrow-left class="h-4 w-4" />
        Payouts
    </a>

    <div class="mx-auto mt-4 max-w-2xl">
        <div class="rounded-2xl border border-ink-200/70 bg-white p-6 shadow-subtle">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight text-ink-900">{{ $payout->payout_code }}</h2>
                    <p class="mt-1 text-2xl font-semibold text-ink-900">₹{{ number_format((float) $payout->amount, 2) }}</p>
                </div>
                <x-status-badge
                    :status="match($payout->status) {
                        'paid' => 'active',
                        'pending', 'processing' => 'attention',
                        default => 'inactive',
                    }"
                    :label="$payout->status === 'pending' ? 'Pending Review' : $payout->status_label"
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
                    <dt class="text-ink-500">Account</dt>
                    <dd class="mt-1 font-medium text-ink-900">{{ $payout->payoutAccount?->masked_account ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-ink-500">Currency</dt>
                    <dd class="mt-1 font-medium text-ink-900">{{ $payout->currency }}</dd>
                </div>
            </dl>

            @if ($payout->status === 'rejected')
                <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-rose-600">Rejected Reason</p>
                    <p class="mt-1 text-sm text-rose-800">{{ $payout->rejection_reason ?? 'Not specified' }}</p>
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
    </div>
</x-app-layout>
