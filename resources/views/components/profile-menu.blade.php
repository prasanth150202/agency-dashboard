<div x-data="profileMenu()" x-on:click.outside="close()" class="relative">
    <button
        type="button"
        x-on:click="open = !open"
        class="flex h-9 w-9 items-center justify-center rounded-full bg-ink-900 text-xs font-semibold text-white transition hover:opacity-90"
        aria-label="Profile menu"
    >
        {{ Str::of(auth()->user()->name ?? 'U')->substr(0, 1) }}
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 top-[52px] z-50 w-72 overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-panel"
    >
        <template x-if="view === 'menu'">
            <div>
                <div class="border-b border-ink-100 px-4 py-3.5">
                    <p class="text-sm font-semibold text-ink-900">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-ink-500">{{ auth()->user()->email }}</p>
                </div>

                <div class="border-b border-ink-100 px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-400">Organisation</p>
                    <p class="mt-1 text-sm font-medium text-ink-900">{{ $currentOrganisation->name ?? '—' }}</p>
                </div>

                <div class="p-1.5">
                    <button
                        type="button"
                        x-on:click="view = 'switch'"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50"
                    >
                        <span class="flex items-center gap-2.5">
                            <x-lucide-building-2 class="h-4 w-4 text-ink-400" />
                            Switch organisation
                        </span>
                        <x-lucide-chevron-right class="h-4 w-4 text-ink-400" />
                    </button>
                    <a
                        href="{{ route('settings') }}"
                        class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50"
                    >
                        <x-lucide-settings class="h-4 w-4 text-ink-400" />
                        Organisation settings
                    </a>
                    <a
                        href="{{ route('profile.edit') }}"
                        class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50"
                    >
                        <x-lucide-user class="h-4 w-4 text-ink-400" />
                        Profile
                    </a>
                    <button
                        type="button"
                        x-on:click="close(); $dispatch('open-modal', 'create-organisation')"
                        class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50"
                    >
                        <x-lucide-plus class="h-4 w-4 text-ink-400" />
                        Create new organisation
                    </button>
                </div>

                <div class="border-t border-ink-100 p-1.5">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50"
                        >
                            <x-lucide-log-out class="h-4 w-4" />
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </template>

        <template x-if="view === 'switch'">
            <x-organisation-switcher />
        </template>
    </div>
</div>
