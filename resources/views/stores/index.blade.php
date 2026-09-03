<x-app-layout title="Stores">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Stores</h2>
            <p class="mt-1 text-sm text-ink-500">
                {{ $stores->total() }} {{ Str::plural('store', $stores->total()) }} connected to {{ $organisation->name }}
            </p>
        </div>

        <button
            type="button"
            x-data
            x-on:click="$dispatch('open-modal', 'add-store')"
            class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700"
        >
            <x-lucide-plus class="h-4 w-4" />
            Add store
        </button>
    </div>

    @if (session('success'))
        <div class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @elseif (session('info'))
        <div class="mt-4 rounded-xl bg-brix-50 px-4 py-3 text-sm font-medium text-ink-700">{{ session('info') }}</div>
    @elseif (session('error'))
        <div class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ session('error') }}</div>
    @endif

    @if ($pendingConnections->isNotEmpty())
        <div class="mt-4 space-y-2.5">
            @foreach ($pendingConnections as $onboarding)
                <div class="flex flex-col gap-2 rounded-xl border border-ink-200/70 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2.5 text-sm">
                        <x-lucide-loader-2 class="h-4 w-4 animate-spin text-brix-600" />
                        <span class="font-medium text-ink-900">{{ $onboarding->shop_domain }}</span>
                        <span class="text-ink-500">— waiting for BRIX to be installed on Shopify</span>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <button
                            type="button"
                            x-data
                            x-on:click="$dispatch('open-modal', 'add-store')"
                            class="text-sm font-medium text-brix-600 hover:text-brix-700"
                        >
                            Continue
                        </button>
                        <a href="{{ route('stores.index') }}" class="inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-900">
                            <x-lucide-refresh-cw class="h-3.5 w-3.5" /> Refresh
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('stores.index') }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
            <input
                type="search"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search by name or domain..."
                x-data
                x-on:input.debounce.500ms="$el.form.submit()"
                class="w-full rounded-lg border border-ink-200 bg-white py-2.5 pl-9 pr-3 text-sm text-ink-900 placeholder-ink-400 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
            >
        </div>

        <select
            name="status"
            x-data
            x-on:change="$el.form.submit()"
            class="rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm font-medium text-ink-700 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
        >
            <option value="">All statuses</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
            <option value="attention" @selected(($filters['status'] ?? '') === 'attention')>Attention</option>
            <option value="offline" @selected(($filters['status'] ?? '') === 'offline')>Offline</option>
        </select>

        <select
            name="module"
            x-data
            x-on:change="$el.form.submit()"
            class="rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm font-medium text-ink-700 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
        >
            <option value="">All modules</option>
            @foreach ($modules as $key => $label)
                <option value="{{ $key }}" @selected(($filters['module'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <select
            name="sort"
            x-data
            x-on:change="$el.form.submit()"
            class="rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm font-medium text-ink-700 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
        >
            <option value="recent" @selected(($filters['sort'] ?? 'recent') === 'recent')>Recently active</option>
            <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Name (A–Z)</option>
        </select>
    </form>

    @if ($stores->isEmpty())
        <div class="mt-8 rounded-2xl border border-dashed border-ink-200 py-16 text-center">
            <p class="text-sm text-ink-500">No stores match your filters.</p>
        </div>
    @else
        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($stores as $store)
                <x-store-card :store="$store" />
            @endforeach
        </div>

        <div class="mt-6">
            {{ $stores->links() }}
        </div>
    @endif

    <x-modal name="add-store" :open-on-load="$errors->store->any()">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-base font-semibold text-ink-900">Add a Store</h2>
                <p class="mt-1 text-sm text-ink-500">Connect a Shopify store to your BRIX agency account.</p>
            </div>
            <button type="button" x-on:click="show = false" class="text-ink-400 hover:text-ink-700">
                <x-lucide-x class="h-4 w-4" />
            </button>
        </div>

        <form method="POST" action="{{ route('stores.connect.start') }}" class="mt-5 space-y-4">
            @csrf

            <div>
                <label for="store-domain" class="mb-1.5 block text-sm font-medium text-ink-700">Shopify store domain</label>
                <input
                    id="store-domain"
                    type="text"
                    name="shop_domain"
                    value="{{ old('shop_domain') }}"
                    placeholder="yourstore.myshopify.com"
                    autocomplete="off"
                    class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 placeholder-ink-400 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                >
                @error('shop_domain', 'store')
                    <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                @enderror
                <p class="mt-1.5 text-xs text-ink-400">
                    Used only to route you to the right store — BRIX still verifies installation and
                    authorization on our servers before this store is ever marked connected.
                </p>
            </div>

            <div class="mt-6 flex justify-end gap-2.5">
                <button type="button" x-on:click="show = false" class="rounded-lg border border-ink-200 px-3.5 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50">
                    Cancel
                </button>
                <button type="submit" class="rounded-lg bg-brix-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-brix-700">
                    Connect Shopify Store
                </button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
