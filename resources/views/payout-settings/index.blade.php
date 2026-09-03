@php
    $hasErrors = $errors->any();
@endphp

<x-app-layout title="Payout Settings">
    <div>
        <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Payout Settings</h2>
        <p class="mt-1 text-sm text-ink-500">Manage the account where BRIX sends your agency payouts.</p>
    </div>

    <div class="mt-6 max-w-xl space-y-4">
        {{-- Current account summary --}}
        @if ($account && ! $hasErrors)
            <div
                x-data="{ editing: false }"
                class="rounded-2xl border border-ink-200/70 bg-white p-6 shadow-subtle"
            >
                <div x-show="!editing">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-ink-400">Current Payout Account</p>
                            <p class="mt-1 text-sm font-semibold text-ink-900">{{ $account->method_label }}</p>
                        </div>
                        @if ($account->is_verified)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                <x-lucide-check class="h-3 w-3" />
                                Verified
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                                <x-lucide-circle-alert class="h-3 w-3" />
                                Verification Required
                            </span>
                        @endif
                    </div>

                    <dl class="mt-4 space-y-2 text-sm">
                        @if ($account->method === 'bank_transfer')
                            <div class="flex justify-between"><dt class="text-ink-500">Account Holder</dt><dd class="font-medium text-ink-900">{{ $account->account_holder_name }}</dd></div>
                            <div class="flex justify-between"><dt class="text-ink-500">Account Number</dt><dd class="font-medium text-ink-900">{{ $account->masked_account }}</dd></div>
                            <div class="flex justify-between"><dt class="text-ink-500">IFSC Code</dt><dd class="font-medium text-ink-900">{{ $account->ifsc_code }}</dd></div>
                        @else
                            <div class="flex justify-between"><dt class="text-ink-500">UPI ID</dt><dd class="font-medium text-ink-900">{{ $account->upi_id }}</dd></div>
                        @endif
                    </dl>

                    @unless ($account->is_verified)
                        <p class="mt-4 text-xs text-amber-700">
                            You cannot request a payout until your payout account is verified.
                        </p>
                    @endunless

                    <button
                        type="button"
                        x-on:click="editing = true"
                        class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-3.5 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50"
                    >
                        Edit
                    </button>
                </div>

                <div x-show="editing" x-cloak>
                    @include('payout-settings.form', ['account' => $account])
                    <button type="button" x-on:click="editing = false" class="mt-3 text-sm font-medium text-ink-500 hover:text-ink-700">
                        Cancel
                    </button>
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-ink-200/70 bg-white p-6 shadow-subtle">
                <p class="mb-1 text-sm font-semibold text-ink-900">Payout method</p>
                <p class="mb-5 text-sm text-ink-500">Add your bank or UPI details to start receiving payouts.</p>
                @include('payout-settings.form', ['account' => $account])
            </div>
        @endif
    </div>
</x-app-layout>
