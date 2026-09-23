@props(['steps', 'caption' => null])

{{-- steps: [['label' => .., 'value' => int, 'href' => ?string, 'hint' => ?string]] — real counts only. --}}
@php
    $top = max(1, ...array_map(fn ($s) => (int) $s['value'], $steps));
@endphp

<ol {{ $attributes->class('space-y-1.5') }}>
    @foreach ($steps as $i => $step)
        @php
            $rate = $i > 0 ? \App\Services\Referral\ReferralFunnel::rate((int) $step['value'], (int) $steps[$i - 1]['value']) : null;
            $width = $step['value'] > 0 ? max(3, round($step['value'] / $top * 100)) : 0;
            $tag = ! empty($step['href']) ? 'a' : 'div';
        @endphp
        <li>
            @if ($i > 0)
                <p class="flex items-center gap-1 pl-1 text-[11px] text-ink-400">
                    <x-lucide-corner-down-right class="h-3 w-3" aria-hidden="true" />
                    @if ($rate !== null)
                        <span class="font-medium text-ink-600">{{ $rate }}%</span> of {{ strtolower($steps[$i - 1]['label']) }}
                    @else
                        no {{ strtolower($steps[$i - 1]['label']) }} to convert
                    @endif
                </p>
            @endif
            <{{ $tag }} @if ($tag === 'a') href="{{ $step['href'] }}" @endif
                @class(['group grid grid-cols-[6.5rem_1fr_auto] items-center gap-3 rounded-lg px-2 py-1.5 sm:grid-cols-[8rem_1fr_auto]',
                    'transition hover:bg-ink-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brix-600 motion-reduce:transition-none' => $tag === 'a'])>
                <span class="truncate text-sm text-ink-700 group-hover:text-ink-900">{{ $step['label'] }}</span>
                <span class="h-5 overflow-hidden rounded bg-ink-100" aria-hidden="true">
                    <span class="block h-full rounded bg-ink-800 transition-all duration-300 group-hover:bg-ink-900 motion-reduce:transition-none" style="width: {{ $width }}%"></span>
                </span>
                <span class="flex items-center gap-1 text-right text-sm font-semibold tabular-nums text-ink-900">
                    {{ number_format($step['value']) }}
                    @if ($tag === 'a')<x-lucide-chevron-right class="h-3.5 w-3.5 text-ink-300 group-hover:text-ink-600" aria-hidden="true" />@endif
                </span>
            </{{ $tag }}>
            @if (! empty($step['hint']))
                <p class="pl-2 text-[11px] text-ink-400">{{ $step['hint'] }}</p>
            @endif
        </li>
    @endforeach
</ol>
@if ($caption)
    <p class="mt-3 text-[11px] leading-4 text-ink-400">{{ $caption }}</p>
@endif
