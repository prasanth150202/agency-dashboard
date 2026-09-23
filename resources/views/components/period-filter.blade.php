@props(['period', 'options' => ['7d', '30d', '3m', '6m', 'ytd', 'custom'], 'keep' => []])

{{-- GET form: every widget on the page re-renders server-side for the chosen window. `keep` preserves the page's other filters. --}}
<form
    method="GET"
    x-data="{ custom: @js($period->key === 'custom') }"
    {{ $attributes->class('flex flex-wrap items-center gap-2') }}
    aria-label="Reporting period"
>
    @foreach ($keep as $name => $value)
        @if (filled($value))
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach

    <div class="inline-flex max-w-full overflow-x-auto rounded-lg border border-ink-200 bg-white p-0.5 text-xs font-medium scrollbar-none" role="group">
        @foreach ($options as $key)
            @if ($key === 'custom')
                <button type="button" x-on:click="custom = !custom" :aria-expanded="custom.toString()"
                    @class(['whitespace-nowrap rounded-md px-2.5 py-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brix-600',
                        'bg-ink-900 text-white' => $period->key === 'custom', 'text-ink-600 hover:bg-ink-50 hover:text-ink-900' => $period->key !== 'custom'])>
                    Custom
                </button>
            @else
                <button type="submit" name="range" value="{{ $key }}"
                    @if ($period->key === $key) aria-current="true" @endif
                    @class(['whitespace-nowrap rounded-md px-2.5 py-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brix-600',
                        'bg-ink-900 text-white' => $period->key === $key, 'text-ink-600 hover:bg-ink-50 hover:text-ink-900' => $period->key !== $key])>
                    {{ \App\Support\AnalyticsPeriod::OPTIONS[$key] }}
                </button>
            @endif
        @endforeach
    </div>

    @if (in_array('custom', $options, true))
        <div x-show="custom" x-cloak x-transition.opacity.duration.150ms class="flex flex-wrap items-center gap-1.5 text-xs">
            <label class="sr-only" for="period-from">From</label>
            <input id="period-from" type="date" name="from" value="{{ $period->key === 'custom' ? $period->from->toDateString() : '' }}" max="{{ now()->toDateString() }}"
                x-bind:disabled="!custom" class="rounded-lg border-ink-200 py-1.5 text-xs focus:border-brix-500 focus:ring-brix-500">
            <span class="text-ink-400">to</span>
            <label class="sr-only" for="period-to">To</label>
            <input id="period-to" type="date" name="to" value="{{ $period->key === 'custom' ? $period->to->toDateString() : '' }}" max="{{ now()->toDateString() }}"
                x-bind:disabled="!custom" class="rounded-lg border-ink-200 py-1.5 text-xs focus:border-brix-500 focus:ring-brix-500">
            <button type="submit" name="range" value="custom" class="rounded-lg bg-ink-900 px-3 py-1.5 font-medium text-white hover:bg-ink-800">Apply</button>
        </div>
    @endif
</form>
