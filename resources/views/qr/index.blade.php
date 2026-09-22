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
        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($links as $link)
                <div class="flex gap-4 rounded-2xl border border-ink-200/70 bg-white p-4 shadow-subtle">
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
