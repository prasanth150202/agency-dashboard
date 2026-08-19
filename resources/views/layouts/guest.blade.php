<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'BRIX') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center bg-ink-50 px-4 py-10">
            <a href="/" class="flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-brix-600 text-sm font-bold text-white">B</div>
                <span class="text-lg font-semibold tracking-tight text-ink-900">BRIX</span>
            </a>

            <div class="mt-8 w-full sm:max-w-md rounded-2xl border border-ink-200/70 bg-white p-8 shadow-panel">
                {{ $slot }}
            </div>
        </div>

        <x-toast />
    </body>
</html>
