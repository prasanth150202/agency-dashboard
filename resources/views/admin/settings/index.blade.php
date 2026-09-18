<x-admin-layout title="Platform Settings">
    <div class="max-w-xl rounded-2xl border border-ink-200/70 bg-white p-6 shadow-subtle">
        <h2 class="text-sm font-semibold text-ink-900">Finance Settings</h2>
        <p class="mt-1 text-sm text-ink-500">Controls every partner's payout eligibility platform-wide.</p>

        @auth('admin')
            @if (auth('admin')->user()->isFinance())
                <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="minimum_payout_amount" value="Minimum Payout Amount" />
                        <x-text-input id="minimum_payout_amount" type="number" step="0.01" min="0" name="minimum_payout_amount" :value="old('minimum_payout_amount', $settings->minimum_payout_amount)" required />
                        <x-input-error :messages="$errors->get('minimum_payout_amount')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label for="commission_holding_period_days" value="Commission Holding Period (days)" />
                        <x-text-input id="commission_holding_period_days" type="number" min="0" max="365" name="commission_holding_period_days" :value="old('commission_holding_period_days', $settings->commission_holding_period_days)" required />
                        <x-input-error :messages="$errors->get('commission_holding_period_days')" class="mt-1.5" />
                    </div>

                    <button type="submit" class="rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700">
                        Save Settings
                    </button>
                </form>
            @else
                <dl class="mt-5 space-y-3 text-sm">
                    <div><dt class="text-ink-400">Minimum Payout Amount</dt><dd class="font-medium text-ink-900">{{ \App\Support\Currency::format($settings->minimum_payout_amount) }}</dd></div>
                    <div><dt class="text-ink-400">Commission Holding Period</dt><dd class="font-medium text-ink-900">{{ $settings->commission_holding_period_days }} days</dd></div>
                </dl>
                <p class="mt-4 text-xs text-ink-400">Only Finance or Super Admins can edit these settings.</p>
            @endif
        @endauth
    </div>
</x-admin-layout>
