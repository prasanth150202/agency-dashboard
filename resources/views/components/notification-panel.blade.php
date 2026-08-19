<div
    x-data="notificationPanel(@js($notificationGroups ?? []))"
    x-on:click.outside="open = false"
    class="relative"
>
    <button
        type="button"
        x-on:click="open = !open"
        class="relative flex h-9 w-9 items-center justify-center rounded-lg text-ink-500 transition hover:bg-ink-100 hover:text-ink-900"
        aria-label="Notifications"
    >
        <x-lucide-bell class="h-[18px] w-[18px]" />
        <span
            x-show="unreadCount > 0"
            x-cloak
            class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-white"
        ></span>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-x-2"
        x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-y-0 right-0 z-50 flex w-full flex-col border-l border-ink-200/70 bg-white shadow-panel sm:absolute sm:inset-y-auto sm:right-0 sm:top-[52px] sm:w-96 sm:rounded-2xl sm:border"
    >
        <div class="flex shrink-0 items-center justify-between border-b border-ink-200/70 px-5 py-4">
            <h3 class="text-sm font-semibold text-ink-900">Notifications</h3>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    x-show="unreadCount > 0"
                    x-on:click="markAllRead()"
                    class="text-xs font-medium text-brix-600 hover:text-brix-700"
                >
                    Mark all as read
                </button>
                <button type="button" x-on:click="open = false" aria-label="Close notifications" class="text-ink-400 hover:text-ink-700 sm:hidden">
                    <x-lucide-x class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto">
            <template x-if="groups.length === 0">
                <p class="px-5 py-10 text-center text-sm text-ink-400">You're all caught up.</p>
            </template>

            <template x-for="group in groups" :key="group.label">
                <div>
                    <p class="bg-ink-50 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-ink-400" x-text="group.label"></p>
                    <template x-for="item in group.items" :key="item.id">
                        <button
                            type="button"
                            x-on:click="markRead(item)"
                            class="flex w-full items-start gap-3 border-b border-ink-100 px-5 py-3.5 text-left transition hover:bg-ink-50"
                            :class="!item.read && 'bg-brix-50/40'"
                        >
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" :class="item.read ? 'bg-transparent' : 'bg-brix-500'"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-ink-900" x-text="item.title"></span>
                                <span class="mt-0.5 block text-xs text-ink-500" x-text="item.message"></span>
                                <span class="mt-1 block text-[11px] text-ink-400" x-text="item.time"></span>
                            </span>
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>
