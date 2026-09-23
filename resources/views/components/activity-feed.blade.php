@props(['items', 'empty' => 'No activity in this period.'])

<ol {{ $attributes->class('relative space-y-0.5') }}>
    @forelse ($items as $item)
        @php
            $dot = match ($item['tone']) {
                'success' => 'bg-emerald-500', 'warning' => 'bg-amber-500', 'danger' => 'bg-rose-500', default => 'bg-blue-500',
            };
            $tag = $item['url'] ? 'a' : 'div';
        @endphp
        <li>
            <{{ $tag }} @if ($item['url']) href="{{ $item['url'] }}" @endif
                @class(['group flex gap-3 rounded-lg px-2 py-2', 'transition hover:bg-ink-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brix-600 motion-reduce:transition-none' => $item['url']])>
                <span class="relative mt-1.5 flex h-2 w-2 shrink-0 rounded-full {{ $dot }}" aria-hidden="true"></span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-baseline justify-between gap-2">
                        <span class="truncate text-xs font-medium text-ink-900">{{ $item['title'] }}</span>
                        <time datetime="{{ $item['at']->toIso8601String() }}" title="{{ $item['at']->format('M j, Y g:i A') }}" class="shrink-0 text-[11px] text-ink-400">{{ $item['at']->diffForHumans(null, true, true) }}</time>
                    </span>
                    @if ($item['detail'])
                        <span class="block truncate text-[11px] text-ink-500">{{ $item['detail'] }}</span>
                    @endif
                </span>
            </{{ $tag }}>
        </li>
    @empty
        <li class="px-2 py-6 text-center text-xs text-ink-400">{{ $empty }}</li>
    @endforelse
</ol>
