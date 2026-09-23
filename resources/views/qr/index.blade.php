<x-app-layout title="QR Referrals">
    <div>
        <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">QR Referrals</h2>
        <p class="mt-1 text-sm text-ink-500">
            A printable QR code for each referral link. Scans go through the same tracking as a link click, so leads, revenue and commission
            are attributed exactly as they are for the link.
        </p>
    </div>

    @if ($links->isEmpty())
        <div class="mt-8 rounded-2xl border border-dashed border-ink-200 py-16 text-center">
            <p class="text-sm font-medium text-ink-700">No referral links yet</p>
            <p class="mt-1 text-sm text-ink-500">Create a referral link and its QR code will appear here.</p>
            <a href="{{ route('referral-links.index') }}" class="mt-4 inline-flex rounded-lg bg-brix-600 px-4 py-2 text-sm font-medium text-white hover:bg-brix-700">Go to Referral Links</a>
        </div>
    @else
        {{-- Selected link: the QR simply encodes that link's existing /ref/{code} URL. --}}
        <section class="mt-6 grid grid-cols-1 gap-5 rounded-xl border border-ink-200/70 bg-white p-5 shadow-subtle md:grid-cols-[auto_1fr]" aria-label="Selected QR code"
            x-data="copyText(@js($selectedQrUrl))">
            <div class="mx-auto h-48 w-48 overflow-hidden rounded-lg border border-ink-100 bg-white p-2 [&>svg]:h-full [&>svg]:w-full" role="img" aria-label="QR code for {{ $selected->name }}">{!! $qrSvg[$selected->id] !!}</div>
            <div class="min-w-0">
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    <label for="qr-link" class="text-xs font-medium text-ink-500">Referral link</label>
                    <select id="qr-link" name="link" x-on:change="$el.form.submit()" class="min-w-0 rounded-lg border-ink-200 text-sm focus:border-brix-500 focus:ring-brix-500">
                        @foreach ($links as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $selected->id)>{{ $option->name }} ({{ $option->code }})</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="rounded-lg border border-ink-200 px-3 py-1.5 text-xs">Show</button></noscript>
                </form>
                <p class="mt-3 text-xs text-ink-500">Encodes</p>
                <p class="truncate font-mono text-sm text-ink-800">{{ $selectedQrUrl }}</p>
                <p class="mt-2 text-sm text-ink-600"><span class="font-semibold text-ink-900">{{ number_format($scans[$selected->id] ?? 0) }}</span> scans, counted under
                    <a href="{{ route('referral-links.show', $selected) }}" class="font-medium text-ink-800 underline decoration-ink-300 hover:text-ink-900">{{ $selected->name }}</a></p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" x-on:click="copy()" class="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-3 py-2 text-xs font-medium text-ink-700 hover:bg-ink-50">
                        <x-lucide-copy class="h-3.5 w-3.5" x-show="!copied" aria-hidden="true" />
                        <x-lucide-check class="h-3.5 w-3.5 text-emerald-600" x-show="copied" x-cloak aria-hidden="true" />
                        <span x-text="copied ? 'Copied' : 'Copy QR URL'">Copy QR URL</span>
                    </button>
                    @if ($selected->is_active)
                        <a href="{{ route('qr.download', $selected) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-ink-900 px-3 py-2 text-xs font-medium text-white hover:bg-ink-800">
                            <x-lucide-download class="h-3.5 w-3.5" aria-hidden="true" /> Download SVG
                        </a>
                    @else
                        <p class="self-center text-xs font-medium text-amber-700">Link is inactive — scans won't work.</p>
                    @endif
                </div>
            </div>
        </section>

        <h3 class="mt-6 text-sm font-semibold text-ink-900">All referral links</h3>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($links as $link)
                <div @class(['flex gap-4 rounded-2xl border bg-white p-4 shadow-subtle', 'border-ink-900' => $link->id === $selected->id, 'border-ink-200/70' => $link->id !== $selected->id])>
                    <div class="h-28 w-28 shrink-0 overflow-hidden rounded-lg border border-ink-100 bg-white [&>svg]:h-full [&>svg]:w-full">{!! $qrSvg[$link->id] !!}</div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-ink-900">{{ $link->name }}</p>
                        <p class="mt-0.5 truncate text-xs text-ink-500">{{ $link->channel }}{{ $link->campaign_name ? ' · '.$link->campaign_name : '' }}</p>
                        <p class="mt-0.5 font-mono text-xs text-ink-400">{{ $link->code }}</p>
                        <p class="mt-2 text-sm text-ink-600"><span class="font-semibold text-ink-900">{{ number_format($scans[$link->id] ?? 0) }}</span> scans</p>
                        @if ($link->is_active)
                            <a href="{{ route('qr.download', $link) }}" class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-brix-600 hover:text-brix-700">
                                <x-lucide-download class="h-3.5 w-3.5" /> Download SVG
                            </a>
                        @else
                            <p class="mt-2 text-xs font-medium text-amber-700">Link is inactive — scans won't work.</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
