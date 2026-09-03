<x-guest-layout>
    <div class="text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50">
            <x-lucide-check-circle-2 class="h-6 w-6 text-emerald-600" />
        </div>

        <h2 class="mt-4 text-lg font-semibold text-ink-900">You're connected</h2>
        <p class="mt-1 text-sm text-ink-500">
            <span class="font-medium text-ink-900">{{ $store->name }}</span>
            is now connected to <span class="font-medium text-ink-900">{{ $agencyName }}</span>.
        </p>
        <p class="mt-4 text-xs text-ink-400">
            You can close this tab and return to Shopify.
        </p>
    </div>
</x-guest-layout>
