<x-app-layout :title="$store->name">
    <a href="{{ route('stores.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-900">
        <x-lucide-arrow-left class="h-4 w-4" />
        Stores
    </a>

    <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-ink-900 text-base font-semibold text-white">
                {{ Str::substr($store->name, 0, 1) }}
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="text-xl font-semibold tracking-tight text-ink-900">{{ $store->name }}</h2>
                    <x-status-badge :status="$store->status" />
                </div>
                <p class="mt-0.5 text-sm text-ink-500">{{ $store->shop_domain }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a
                href="{{ $store->admin_url }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-3.5 py-2.5 text-sm font-medium text-ink-700 hover:bg-ink-50"
            >
                <x-lucide-external-link class="h-4 w-4" />
                Visit site
            </a>
            <a
                href="{{ route('stores.show', $store) }}"
                class="inline-flex items-center gap-1.5 rounded-lg bg-brix-600 px-3.5 py-2.5 text-sm font-medium text-white hover:bg-brix-700"
            >
                <x-lucide-eye class="h-4 w-4" />
                Preview
            </a>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-2xl border border-ink-200/70 bg-white p-4 shadow-subtle">
            <p class="text-xs font-medium text-ink-500">Store status</p>
            <div class="mt-2"><x-status-badge :status="$store->status" /></div>
        </div>
        <div class="rounded-2xl border border-ink-200/70 bg-white p-4 shadow-subtle">
            <p class="text-xs font-medium text-ink-500">BRIX</p>
            <div class="mt-2"><x-status-badge status="active" label="Installed" /></div>
        </div>
        <div class="rounded-2xl border border-ink-200/70 bg-white p-4 shadow-subtle">
            <p class="text-xs font-medium text-ink-500">Shopify</p>
            <div class="mt-2"><x-status-badge status="active" label="Connected" /></div>
        </div>
        <div class="rounded-2xl border border-ink-200/70 bg-white p-4 shadow-subtle">
            <p class="text-xs font-medium text-ink-500">Last sync</p>
            <p class="mt-2.5 text-sm font-medium text-ink-900">{{ $store->last_active_at?->diffForHumans() ?? '—' }}</p>
        </div>
    </div>

    <div class="mt-8">
        <h3 class="text-sm font-semibold text-ink-900">BRIX modules</h3>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($modules as $module)
                <x-module-card :store="$store" :module="$module" />
            @endforeach
        </div>
    </div>
</x-app-layout>
