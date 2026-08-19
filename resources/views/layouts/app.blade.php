<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title . ' · BRIX' : config('app.name', 'BRIX') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body class="font-sans">
        <div x-data="layout()" class="min-h-screen bg-ink-50">
            <x-sidebar />

            <div
                class="flex min-h-screen flex-col transition-[padding] duration-200"
                :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'"
            >
                <x-topbar :title="$title ?? 'Dashboard'" />

                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <x-toast />
        <x-create-organisation-modal />

        @stack('scripts')
    </body>
</html>
