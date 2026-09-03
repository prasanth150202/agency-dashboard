@props(['label', 'value', 'delta' => null, 'icon' => null, 'caption' => 'vs last period', 'prominent' => false])

<div @class([
    'rounded-2xl p-5 shadow-subtle',
    'border-2 border-brix-600 bg-ink-900 text-white' => $prominent,
    'border border-ink-200/70 bg-white' => ! $prominent,
])>
    <div class="flex items-center justify-between">
        <span @class(['text-sm font-medium', 'text-ink-300' => $prominent, 'text-ink-500' => ! $prominent])>{{ $label }}</span>
        @if ($icon)
            <span @class([
                'flex h-8 w-8 items-center justify-center rounded-lg',
                'bg-white/10 text-white' => $prominent,
                'bg-brix-50 text-brix-600' => ! $prominent,
            ])>
                <x-dynamic-component :component="'lucide-' . $icon" class="h-4 w-4" />
            </span>
        @endif
    </div>

    <p @class(['mt-3 text-2xl font-semibold tracking-tight', 'text-white' => $prominent, 'text-ink-900' => ! $prominent])>{{ $value }}</p>

    @if (! is_null($delta))
        @php
            $positive = $delta >= 0;
        @endphp
        <p class="mt-2 inline-flex items-center gap-1 text-xs font-medium {{ $positive ? 'text-emerald-500' : 'text-rose-500' }}">
            <x-lucide-trending-up class="h-3.5 w-3.5 {{ $positive ? '' : 'rotate-180' }}" />
            {{ $positive ? '+' : '' }}{{ $delta }}%
            <span @class(['font-normal', 'text-ink-400' => $prominent, 'text-ink-400' => ! $prominent])>{{ $caption }}</span>
        </p>
    @endif

    {{ $slot }}
</div>
