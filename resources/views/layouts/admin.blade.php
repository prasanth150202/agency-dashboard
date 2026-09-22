<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title . ' · BRIX Admin' : 'BRIX Admin' }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-ink-50">
            <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-ink-200/70 bg-ink-950 lg:flex">
                <div class="flex h-16 shrink-0 items-center gap-2.5 border-b border-ink-800 px-5">
                    <x-brix-mark class="h-9 w-9 shrink-0" />
                    <span class="text-base font-semibold tracking-tight text-white">BRIX Admin</span>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                    @php
                        $navItems = [
                            ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'admin.dashboard', 'active' => request()->routeIs('admin.dashboard')],
                            ['label' => 'Partners', 'icon' => 'users', 'route' => 'admin.partners.index', 'active' => request()->routeIs('admin.partners.*')],
                            ['label' => 'Stores', 'icon' => 'store', 'route' => 'admin.stores.index', 'active' => request()->routeIs('admin.stores.*')],
                            ['label' => 'Leads', 'icon' => 'user-plus', 'route' => 'admin.leads.index', 'active' => request()->routeIs('admin.leads.*')],
                            ['label' => 'Referral Links', 'icon' => 'link-2', 'route' => 'admin.referral-links.index', 'active' => request()->routeIs('admin.referral-links.*')],
                            ['label' => 'Tracking', 'icon' => 'mouse-pointer-click', 'route' => 'admin.tracking.index', 'active' => request()->routeIs('admin.tracking.*')],
                            ['label' => 'Revenue', 'icon' => 'trending-up', 'route' => 'admin.revenue.index', 'active' => request()->routeIs('admin.revenue.*')],
                            ['label' => 'Commissions', 'icon' => 'indian-rupee', 'route' => 'admin.commissions.index', 'active' => request()->routeIs('admin.commissions.*')],
                            ['label' => 'Payouts', 'icon' => 'wallet', 'route' => 'admin.payouts.index', 'active' => request()->routeIs('admin.payouts.*'), 'badge' => \App\Models\Payout::whereIn('status', \App\Models\Payout::RESERVING_STATUSES)->count()],
                            ['label' => 'Settings', 'icon' => 'settings', 'route' => 'admin.settings.index', 'active' => request()->routeIs('admin.settings.*')],
                        ];

                        $navItems = collect($navItems)->filter(fn (array $item) => \Illuminate\Support\Facades\Route::has($item['route']))->values()->all();
                    @endphp

                    @foreach ($navItems as $item)
                        <a
                            href="{{ route($item['route']) }}"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors {{ $item['active'] ? 'bg-white/10 text-white' : 'text-ink-300 hover:bg-white/5 hover:text-white' }}"
                        >
                            <x-dynamic-component :component="'lucide-' . $item['icon']" class="h-[18px] w-[18px] shrink-0" />
                            <span class="flex-1">{{ $item['label'] }}</span>
                            @if (! empty($item['badge']))
                                <span class="rounded-full bg-brix-600 px-2 py-0.5 text-xs font-semibold text-white">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <div class="border-t border-ink-800 p-3">
                    <div class="flex items-center gap-2.5 rounded-xl px-2 py-2">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white" style="background-color: {{ auth('admin')->user()->avatar_color ?? '#3b82f6' }}">
                            {{ Str::of(auth('admin')->user()->name ?? 'A')->substr(0, 1) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-white">{{ auth('admin')->user()->name }}</p>
                            <p class="truncate text-xs text-ink-400">{{ auth('admin')->user()->role_label }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg p-1.5 text-ink-400 hover:bg-white/5 hover:text-white" title="Log out">
                                <x-lucide-log-out class="h-4 w-4" />
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            <div class="flex min-h-screen flex-col lg:pl-64">
                <header class="flex h-16 shrink-0 items-center justify-between border-b border-ink-200/70 bg-white px-4 sm:px-6 lg:px-8">
                    <h1 class="text-lg font-semibold tracking-tight text-ink-900">{{ $title ?? 'Admin' }}</h1>
                </header>

                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    @if (session('success'))
                        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            {{ session('error') }}
                        </div>
                    @endif
                    @if (session('info'))
                        <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                            {{ session('info') }}
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
