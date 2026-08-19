@props(['store'])

@php
    $activeModules = $store->modules->keyBy('module');
@endphp

<div class="flex flex-col rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle transition hover:shadow-panel">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('stores.show', $store) }}" class="truncate text-sm font-semibold text-ink-900 hover:text-brix-700">
                {{ $store->name }}
            </a>
            <p class="truncate text-xs text-ink-500">{{ $store->shop_domain }}</p>
        </div>
        <x-status-badge :status="$store->status" />
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
    </div>

    <div class="mt-3 flex items-center gap-2">
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
            href="{{ route('stores.show', $store) }}"
            class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-3 py-2 text-xs font-medium text-white hover:bg-brix-700"
        >
            <x-lucide-eye class="h-3.5 w-3.5" />
            Preview
        </a>
    </div>
</div>
