@props(['label', 'value', 'change' => null, 'comparison' => null, 'context' => null, 'href' => null, 'icon' => null])

@php
    $tag = $href ? 'a' : 'div';
    $up = $change !== null && $change >= 0;
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'group relative flex min-w-0 flex-col rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle transition duration-200 motion-reduce:transition-none',
        'hover:-translate-y-0.5 hover:border-ink-300 hover:shadow-panel focus:outline-none focus-visible:ring-2 focus-visible:ring-brix-600 focus-visible:ring-offset-2 motion-reduce:hover:translate-y-0' => $href,
    ]) }}
>
    <div class="flex items-center justify-between gap-2">
        <span class="truncate text-xs font-medium text-ink-500">{{ $label }}</span>
        @if ($icon)
            <x-dynamic-component :component="'lucide-'.$icon" class="h-4 w-4 shrink-0 text-ink-400 group-hover:text-ink-700" aria-hidden="true" />
        @endif
    </div>

    <p class="mt-2 break-words text-xl font-semibold tabular-nums tracking-tight text-ink-900">{{ $value }}</p>

    <div class="mt-1.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[11px] leading-4">
        @if ($change !== null)
            <span @class(['inline-flex items-center gap-0.5 font-semibold', 'text-emerald-600' => $up, 'text-rose-600' => ! $up])>
                <x-dynamic-component :component="$up ? 'lucide-arrow-up-right' : 'lucide-arrow-down-right'" class="h-3 w-3" aria-hidden="true" />
                <span class="sr-only">{{ $up ? 'Up' : 'Down' }}</span>{{ $up ? '+' : '' }}{{ $change }}%
            </span>
            <span class="text-ink-400">{{ $comparison }}</span>
        @elseif ($context)
            <span class="text-ink-400">{{ $context }}</span>
        @endif
    </div>

    @if ($change !== null && $context)
        <p class="mt-0.5 text-[11px] text-ink-400">{{ $context }}</p>
    @endif

    @if ($href)
        <x-lucide-arrow-right class="absolute bottom-4 right-4 h-3.5 w-3.5 text-ink-300 opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100 motion-reduce:transition-none" aria-hidden="true" />
    @endif
</{{ $tag }}>
