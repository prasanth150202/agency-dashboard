@props(['store', 'module'])

@php
    $isActive = $module->status === 'active';
@endphp

<div class="flex flex-col justify-between rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
    <div>
        <div class="flex items-start justify-between gap-2">
            <h3 class="text-sm font-semibold text-ink-900">{{ $module->label }}</h3>
            <x-status-badge :status="$module->status" />
        </div>

        <p class="mt-3 text-xs text-ink-500">
            @if ($isActive)
                Last updated {{ $module->last_updated_at?->diffForHumans() ?? 'recently' }}
            @else
                Not configured
            @endif
        </p>
    </div>

    @if ($isActive)
        <a
            href="{{ $store->safe_app_url }}"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-ink-200 px-3 py-2 text-xs font-medium text-ink-700 hover:bg-ink-50"
        >
            <x-lucide-external-link class="h-3.5 w-3.5" />
            Open module
        </a>
    @else
        <form method="POST" action="{{ route('stores.modules.toggle', [$store, $module->key]) }}" class="mt-4">
            @csrf
            <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-lg bg-brix-600 px-3 py-2 text-xs font-medium text-white hover:bg-brix-700"
            >
                Configure
            </button>
        </form>
    @endif
</div>
