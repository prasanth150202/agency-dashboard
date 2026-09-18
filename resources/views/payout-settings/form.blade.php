@php
    $selectedMethod = old('method', $account->method ?? 'bank_transfer');
@endphp

<form
    method="POST"
    action="{{ route('payout-settings.store') }}"
    x-data="{ method: '{{ $selectedMethod }}' }"
    class="space-y-4"
>
    @csrf

    <div>
        <p class="mb-2 text-sm font-medium text-ink-700">Payout Method</p>
        <div class="flex gap-4">
            <label class="flex items-center gap-2 text-sm text-ink-700">
                <input type="radio" name="method" value="bank_transfer" x-model="method" class="text-brix-600 focus:ring-brix-400">
                Bank Transfer
            </label>
            <label class="flex items-center gap-2 text-sm text-ink-700">
                <input type="radio" name="method" value="upi" x-model="method" class="text-brix-600 focus:ring-brix-400">
                UPI
            </label>
        </div>
        @error('method')
            <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div x-show="method === 'bank_transfer'" x-cloak class="space-y-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-ink-700">Account Holder Name</label>
            <input
                type="text"
                name="account_holder_name"
                value="{{ old('account_holder_name', $account->account_holder_name ?? '') }}"
                class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
            >
            @error('account_holder_name')
                <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-ink-700">Bank Account Number</label>
            <input
                type="text"
                inputmode="numeric"
                name="account_number"
                placeholder="Enter account number"
                class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 placeholder-ink-400 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
            >
            @error('account_number')
                <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-ink-700">Confirm Bank Account Number</label>
            <input
                type="text"
                inputmode="numeric"
                name="account_number_confirmation"
                placeholder="Re-enter account number"
                class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 placeholder-ink-400 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
            >
            @error('account_number_confirmation')
                <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-ink-700">IFSC Code</label>
            <input
                type="text"
                name="ifsc_code"
                value="{{ old('ifsc_code', $account->ifsc_code ?? '') }}"
                class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm uppercase text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
            >
            @error('ifsc_code')
                <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-ink-700">Account Type</label>
            <select
                name="account_type"
                class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
            >
                @php $selectedType = old('account_type', $account->account_type ?? 'savings'); @endphp
                <option value="savings" @selected($selectedType === 'savings')>Savings</option>
                <option value="current" @selected($selectedType === 'current')>Current</option>
            </select>
            @error('account_type')
                <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div x-show="method === 'upi'" x-cloak class="space-y-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-ink-700">UPI ID</label>
            <input
                type="text"
                name="upi_id"
                value="{{ old('upi_id', $account->upi_id ?? '') }}"
                placeholder="yourname@upi"
                class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 placeholder-ink-400 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
            >
            @error('upi_id')
                <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <button type="submit" class="rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700">
        Save Payout Details
    </button>
</form>
