@props(['title' => 'Dashboard'])

<header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-ink-200/70 bg-white/80 px-4 backdrop-blur sm:px-6 lg:px-8">
    <button
        type="button"
        x-on:click="mobileNavOpen = true"
        class="-ml-1 flex h-9 w-9 items-center justify-center rounded-lg text-ink-500 hover:bg-ink-100 lg:hidden"
        aria-label="Open menu"
    >
        <x-lucide-menu class="h-5 w-5" />
    </button>

    <h1 class="text-base font-semibold text-ink-900 sm:text-lg">{{ $title }}</h1>

    <div class="ml-auto flex items-center gap-2 sm:gap-3">
        <form action="{{ route('stores.index') }}" method="GET" class="hidden sm:block">
            <div class="relative">
                <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                <input
                    type="search"
                    name="search"
                    placeholder="Search stores..."
                    class="w-52 rounded-lg border border-ink-200 bg-ink-50 py-2 pl-9 pr-3 text-sm text-ink-900 placeholder-ink-400 outline-none transition focus:border-brix-400 focus:bg-white focus:ring-2 focus:ring-brix-100 lg:w-64"
                >
            </div>
        </form>

        <x-notification-panel />

        <div class="h-6 w-px bg-ink-200"></div>

        <x-profile-menu />
    </div>
</header>
