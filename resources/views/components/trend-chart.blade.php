@props(['chart', 'height' => 'h-64', 'emptyTitle' => 'No activity in this period', 'emptyText' => null, 'emptyAction' => null, 'emptyHref' => null])

{{-- $chart comes from App\Services\Analytics\TrendChart::build(). --}}
@if ($chart['empty'])
    <div class="flex {{ $height }} flex-col items-center justify-center rounded-lg border border-dashed border-ink-200 px-4 text-center">
        <x-lucide-chart-no-axes-column class="h-6 w-6 text-ink-300" aria-hidden="true" />
        <p class="mt-2 text-sm font-medium text-ink-700">{{ $emptyTitle }}</p>
        @if ($emptyText)<p class="mt-0.5 text-xs text-ink-500">{{ $emptyText }}</p>@endif
        @if ($emptyAction && $emptyHref)
            <a href="{{ $emptyHref }}" class="mt-3 rounded-lg bg-ink-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-ink-800">{{ $emptyAction }}</a>
        @endif
    </div>
@else
    <div x-data="trendChart(@js($chart['config']))" {{ $attributes }}>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap gap-1.5" role="group" aria-label="Show or hide series">
                @foreach ($chart['config']['series'] as $series)
                    <button type="button" x-on:click="toggle('{{ $series['key'] }}')" :aria-pressed="(!hidden['{{ $series['key'] }}']).toString()"
                        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brix-600 motion-reduce:transition-none"
                        :class="hidden['{{ $series['key'] }}'] ? 'border-ink-200 text-ink-400 line-through' : 'border-ink-200 bg-white text-ink-700 hover:border-ink-300'">
                        <span class="h-2 w-2 rounded-sm" style="background: {{ $series['color'] }}" aria-hidden="true"></span>
                        {{ $series['label'] }}
                    </button>
                @endforeach
            </div>
            @if (count($chart['config']['currencies']) > 1)
                <div class="inline-flex rounded-md border border-ink-200 p-0.5 text-[11px] font-medium" role="group" aria-label="Currency">
                    @foreach ($chart['config']['currencies'] as $currency)
                        <button type="button" x-on:click="setCurrency('{{ $currency }}')" class="rounded px-2 py-0.5"
                            :class="currency === '{{ $currency }}' ? 'bg-ink-900 text-white' : 'text-ink-600 hover:text-ink-900'">{{ $currency }}</button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="relative mt-3 {{ $height }}">
            <div class="absolute inset-0 animate-pulse rounded-lg bg-ink-50 motion-reduce:animate-none" x-show="!chart" aria-hidden="true"></div>
            <canvas x-ref="canvas" role="img" aria-label="{{ $chart['summary'] }}"></canvas>
        </div>

        <p class="mt-2 text-[11px] text-ink-500">{{ $chart['summary'] }}</p>

        <details class="mt-1 text-xs">
            <summary class="cursor-pointer text-[11px] font-medium text-ink-500 hover:text-ink-800">View data table</summary>
            <div class="mt-2 max-h-56 overflow-auto rounded-lg border border-ink-100">
                <table class="w-full text-left text-xs">
                    <thead class="sticky top-0 bg-ink-50 text-ink-500">
                        <tr>
                            <th class="px-3 py-1.5 font-medium">Period</th>
                            @foreach ($chart['table']['metrics'] as $metric)
                                <th class="px-3 py-1.5 text-right font-medium">{{ $metric }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($chart['table']['rows'] as $row)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-1.5 text-ink-700">{{ $row['label'] }}</td>
                                @foreach ($row['cells'] as $cell)
                                    <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-ink-700">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </div>
@endif
