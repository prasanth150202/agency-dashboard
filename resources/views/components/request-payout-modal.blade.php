@props(['minimumPayout', 'availableBalance'])

@php
    $account = $currentOrganisation->payoutAccount;
@endphp

<div
    x-show="show"
    x-cloak
    x-transition:enter="ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    class="fixed inset-0 z-50 overflow-y-auto"
>
    <div class="fixed inset-0 bg-ink-950/40" x-on:click="close()"></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div
            x-show="show"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="relative w-full max-w-md rounded-2xl border border-ink-200/70 bg-white p-6 shadow-panel"
            x-on:click.stop
            x-cloak
        >
            {{-- Success state --}}
            <template x-if="success">
                <div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <x-lucide-circle-check-big class="h-6 w-6" />
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-ink-900">Payout Request Submitted</h3>
                    <p class="mt-1 text-sm text-ink-500">
                        Your payout request has been submitted. It will be reviewed by the BRIX team.
                    </p>

                    <dl class="mt-5 space-y-2.5 rounded-xl bg-ink-50 p-4 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-ink-500">Amount</dt>
                            <dd class="font-semibold text-ink-900" x-text="'₹' + success.amount"></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-ink-500">Payout ID</dt>
                            <dd class="font-medium text-ink-900" x-text="success.payout_code"></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-ink-500">Status</dt>
                            <dd class="font-medium text-amber-700" x-text="success.status"></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-ink-500">Requested</dt>
                            <dd class="font-medium text-ink-900" x-text="success.requested_at"></dd>
                        </div>
                    </dl>

                    <button
                        type="button"
                        x-on:click="finish()"
                        class="mt-6 w-full rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700"
                    >
                        Done
                    </button>
                </div>
            </template>

            {{-- No payout account configured --}}
            @if (! $account)
                <template x-if="!success">
                    <div>
                        <div class="flex items-start justify-between">
                            <h3 class="text-base font-semibold text-ink-900">Request Payout</h3>
                            <button type="button" x-on:click="close()" class="text-ink-400 hover:text-ink-700">
                                <x-lucide-x class="h-4 w-4" />
                            </button>
                        </div>
                        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                            Payout account not configured.
                        </div>
                        <a
                            href="{{ route('payout-settings') }}"
                            class="mt-4 flex w-full items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700"
                        >
                            <x-lucide-landmark class="h-4 w-4" />
                            Add Payout Account
                        </a>
                    </div>
                </template>
            @elseif (! $account->is_verified)
                <template x-if="!success">
                    <div>
                        <div class="flex items-start justify-between">
                            <h3 class="text-base font-semibold text-ink-900">Request Payout</h3>
                            <button type="button" x-on:click="close()" class="text-ink-400 hover:text-ink-700">
                                <x-lucide-x class="h-4 w-4" />
                            </button>
                        </div>
                        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                            You cannot request a payout until your payout account is verified.
                        </div>
                        <a
                            href="{{ route('payout-settings') }}"
                            class="mt-4 flex w-full items-center justify-center gap-1.5 rounded-lg border border-ink-200 px-4 py-2.5 text-sm font-medium text-ink-700 hover:bg-ink-50"
                        >
                            View Payout Settings
                        </a>
                    </div>
                </template>
            @else
                {{-- Amount / confirm state --}}
                <template x-if="!success">
                    <div>
                        <div class="flex items-start justify-between">
                            <h3 class="text-base font-semibold text-ink-900">Request Payout</h3>
                            <button type="button" x-on:click="close()" class="text-ink-400 hover:text-ink-700">
                                <x-lucide-x class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="mt-5 rounded-xl bg-ink-50 p-4">
                            <p class="text-xs font-medium text-ink-500">Available Balance</p>
                            <p class="mt-1 text-xl font-semibold text-ink-900">₹{{ number_format($availableBalance, 2) }}</p>
                        </div>

                        <div class="mt-4">
                            <label class="mb-1.5 block text-sm font-medium text-ink-700">Amount to Withdraw</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-400">₹</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    x-model.number="amount"
                                    class="w-full rounded-lg border border-ink-200 py-2 pl-7 pr-3 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                                >
                            </div>
                            <p class="mt-1.5 text-xs text-ink-400">Minimum payout: ₹{{ number_format($minimumPayout, 2) }}</p>
                        </div>

                        <div class="mt-4">
                            <p class="mb-1.5 text-sm font-medium text-ink-700">Payout Method</p>
                            <div class="flex items-center gap-2.5 rounded-lg border border-ink-200 bg-ink-50 px-3.5 py-2.5">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-ink-700">
                                    <x-lucide-landmark class="h-4 w-4" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-ink-900">{{ $account->method_label }}</p>
                                    <p class="truncate text-xs text-ink-500">
                                        {{ $account->masked_account }}
                                        @if ($account->method === 'bank_transfer' && $account->ifsc_code)
                                            &middot; {{ $account->ifsc_code }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                        <template x-if="error">
                            <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-sm text-rose-700" x-text="error"></div>
                        </template>

                        <div class="mt-6 flex justify-end gap-2.5">
                            <button
                                type="button"
                                x-on:click="close()"
                                class="rounded-lg border border-ink-200 px-3.5 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                x-on:click="submit()"
                                x-bind:disabled="submitting"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-brix-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-brix-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <span x-show="submitting" x-cloak>Submitting…</span>
                                <span x-show="!submitting">Request Payout</span>
                            </button>
                        </div>
                    </div>
                </template>
            @endif
        </div>
    </div>
</div>
