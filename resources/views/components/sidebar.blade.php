@php
    $navItems = [
        ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
        ['label' => 'Stores', 'icon' => 'store', 'route' => 'stores.index', 'active' => request()->routeIs('stores.*')],
        ['label' => 'Analytics', 'icon' => 'bar-chart-3', 'route' => 'analytics', 'active' => request()->routeIs('analytics')],
        ['label' => 'Payout', 'icon' => 'wallet', 'route' => 'payouts', 'active' => request()->routeIs('payouts')],
    ];
@endphp

{{-- Mobile backdrop --}}
<div
    x-show="mobileNavOpen"
    x-cloak
    x-transition:enter="ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-40 bg-ink-950/40 lg:hidden"
    x-on:click="mobileNavOpen = false"
></div>

<aside
    class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-ink-200/70 bg-white transition-all duration-200 lg:translate-x-0"
    :class="[mobileNavOpen ? 'translate-x-0' : '-translate-x-full', sidebarCollapsed ? 'lg:w-20' : 'lg:w-64']"
>
    {{-- Logo --}}
    <div class="flex h-16 shrink-0 items-center gap-2.5 border-b border-ink-200/70 px-5" :class="sidebarCollapsed && 'lg:justify-center lg:px-0'">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brix-600 text-sm font-bold text-white">
            B
        </div>
        <span class="text-base font-semibold tracking-tight text-ink-900" x-show="!sidebarCollapsed" x-cloak>BRIX</span>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        @foreach ($navItems as $item)
            <div class="group relative">
                <a
                    href="{{ route($item['route']) }}"
                    x-on:click="mobileNavOpen = false"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors {{ $item['active'] ? 'bg-brix-50 text-brix-700' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900' }}"
                    :class="sidebarCollapsed && 'lg:justify-center'"
                >
                    <x-dynamic-component :component="'lucide-' . $item['icon']" class="h-[18px] w-[18px] shrink-0 {{ $item['active'] ? 'text-brix-600' : 'text-ink-400' }}" />
                    <span x-show="!sidebarCollapsed" x-cloak>{{ $item['label'] }}</span>
                </a>

                {{-- Tooltip shown only when collapsed --}}
                <span
                    x-show="sidebarCollapsed"
                    x-cloak
                    class="pointer-events-none absolute left-full top-1/2 z-50 ml-3 hidden -translate-y-1/2 whitespace-nowrap rounded-lg bg-ink-900 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 shadow-panel transition-opacity group-hover:opacity-100 lg:group-hover:block"
                >
                    {{ $item['label'] }}
                </span>
            </div>
        @endforeach
    </nav>

    {{-- Collapse toggle --}}
    <div class="hidden border-t border-ink-200/70 px-3 py-3 lg:block">
        <button
            type="button"
            x-on:click="toggleSidebar()"
            class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-ink-500 hover:bg-ink-100 hover:text-ink-900"
            :class="sidebarCollapsed && 'justify-center'"
        >
            <x-lucide-chevron-right class="h-4 w-4 shrink-0 transition-transform" x-bind:class="sidebarCollapsed ? '' : 'rotate-180'" />
            <span x-show="!sidebarCollapsed" x-cloak>Collapse</span>
        </button>
    </div>

    {{-- Organisation / profile footer --}}
    <div class="border-t border-ink-200/70 p-3">
        <div class="flex items-center gap-2.5 rounded-xl px-2 py-2" :class="sidebarCollapsed && 'lg:justify-center'">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-ink-900 text-xs font-semibold text-white">
                {{ Str::of($currentOrganisation->name ?? 'B')->substr(0, 1) }}
            </div>
            <div class="min-w-0" x-show="!sidebarCollapsed" x-cloak>
                <p class="truncate text-sm font-medium text-ink-900">{{ $currentOrganisation->name ?? '—' }}</p>
                <p class="truncate text-xs text-ink-400">{{ auth()->user()->name ?? '' }}</p>
            </div>
        </div>
    </div>
</aside>
