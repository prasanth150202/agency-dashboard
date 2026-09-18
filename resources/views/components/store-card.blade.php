@props(['store'])

@php
    $activeModules = $store->modules->keyBy('module');
    $badge = $store->connection_badge;
    $action = $store->connection_action;
@endphp

<div class="flex flex-col rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle transition hover:shadow-panel">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('stores.show', $store) }}" class="truncate text-sm font-semibold text-ink-900 hover:text-brix-700">
                {{ $store->name }}
            </a>
            <p class="truncate text-xs text-ink-500">{{ $store->shop_domain }}</p>
        </div>
        <x-status-badge :status="$badge['status']" :label="$badge['label']" />
    </div>

    <div class="mt-4 grid grid-cols-2 gap-x-3 gap-y-1.5 border-t border-ink-100 pt-4">
        @foreach (\App\Models\StoreModule::MODULES as $key => $label)
            @php $isActive = ($activeModules[$key]->status ?? 'inactive') === 'active'; @endphp
            <div class="flex items-center gap-1.5 text-xs">
                <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $isActive ? 'bg-emerald-500' : 'bg-ink-300' }}"></span>
                <span class="truncate {{ $isActive ? 'text-ink-700' : 'text-ink-400' }}">{{ $label }}</span>
            </div>
        @endforeach
    </div>

    <div class="mt-4 flex items-center justify-between border-t border-ink-100 pt-4">
        <span class="text-xs font-medium text-ink-500">
            {{ $store->active_modules_count }} / {{ $store->total_modules_count }} modules active
        </span>
        <span class="text-xs font-medium text-ink-500">{{ $store->plan }} plan</span>
    </div>

    <div class="mt-3 flex items-center gap-2">
        @if ($store->agency_relationship_status === null)
            {{-- Predates the store-connection flow (seeded/demo data) —
                 unchanged from before this feature existed. --}}
            <a
                href="{{ $store->admin_url }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-ink-200 px-3 py-2 text-xs font-medium text-ink-700 hover:bg-ink-50"
            >
                <x-lucide-external-link class="h-3.5 w-3.5" />
                Visit site
            </a>
            <a
                href="{{ $store->brix_app_url }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-3 py-2 text-xs font-medium text-white hover:bg-brix-700"
            >
                <x-lucide-eye class="h-3.5 w-3.5" />
                Preview
            </a>
        @else
            @if ($action['route'])
                <form
                    method="POST"
                    action="{{ route($action['route'], $store) }}"
                    class="flex-1"
                    x-data="{ submitting: false }"
                    x-on:submit="submitting = true"
                >
                    @csrf
                    <button
                        type="submit"
                        x-bind:disabled="submitting"
                        class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-3 py-2 text-xs font-medium text-white hover:bg-brix-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <x-lucide-loader-2 x-show="submitting" class="h-3.5 w-3.5 animate-spin" />
                        <span x-text="submitting ? 'Please wait…' : '{{ $action['label'] }}'"></span>
                    </button>
                </form>
            @elseif ($badge['status'] === 'active')
                <a
                    href="{{ $store->admin_url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-3 py-2 text-xs font-medium text-white hover:bg-brix-700"
                >
                    <x-lucide-external-link class="h-3.5 w-3.5" />
                    {{ $action['label'] }}
                </a>
            @else
                <a
                    href="{{ config('services.shopify.app_store_url') }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-3 py-2 text-xs font-medium text-white hover:bg-brix-700"
                >
                    <x-lucide-external-link class="h-3.5 w-3.5" />
                    {{ $action['label'] }}
                </a>
            @endif

            @if ($badge['status'] === 'active')
                <a
                    href="{{ $store->brix_app_url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-ink-200 px-3 py-2 text-xs font-medium text-ink-700 hover:bg-ink-50"
                >
                    <x-lucide-eye class="h-3.5 w-3.5" />
                    Preview
                </a>
            @endif
        @endif
    </div>
</div>
