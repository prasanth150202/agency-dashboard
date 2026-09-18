<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-lg font-semibold tracking-tight text-ink-900">BRIX Admin</h1>
        <p class="mt-1 text-sm text-ink-500">Internal access only.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <label for="remember_me" class="inline-flex items-center gap-2">
            <input id="remember_me" type="checkbox" class="rounded border-ink-300 text-brix-600 focus:ring-brix-400" name="remember">
            <span class="text-sm text-ink-600">Remember me</span>
        </label>

        <x-primary-button class="w-full">
            Log in
        </x-primary-button>
    </form>
</x-guest-layout>
