<x-guest-layout>
    <div>
        <h2 class="text-lg font-semibold text-ink-900">Install BRIX on your Shopify store</h2>
        <p class="mt-1 text-sm text-ink-500">
            Enter your store's Shopify domain and we'll take you to the Shopify App Store to install BRIX.
        </p>

        <form method="POST" action="{{ request()->fullUrl() }}" class="mt-5 space-y-4">
            @csrf

            <div>
                <label for="shop-domain" class="mb-1.5 block text-sm font-medium text-ink-700">Shopify store domain</label>
                <input
                    id="shop-domain"
                    type="text"
                    name="shop_domain"
                    value="{{ old('shop_domain') }}"
                    placeholder="yourstore.myshopify.com"
                    autocomplete="off"
                    required
                    class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 placeholder-ink-400 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                >
                @error('shop_domain')
                    <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                @enderror
                <p class="mt-1.5 text-xs text-ink-400">
                    Used only to credit the referral that brought you here. It does not give anyone access to your store.
                </p>
            </div>

            <button type="submit" class="w-full rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700">
                Continue to Shopify App Store
            </button>
        </form>
    </div>
</x-guest-layout>
