<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-lg font-semibold tracking-tight text-ink-900">Welcome back</h1>
        <p class="mt-1 text-sm text-ink-500">Sign in to your BRIX agency dashboard.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center gap-2">
                <input id="remember_me" type="checkbox" class="rounded border-ink-300 text-brix-600 focus:ring-brix-400" name="remember">
                <span class="text-sm text-ink-600">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-brix-600 hover:text-brix-700" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <x-primary-button class="w-full">
            {{ __('Log in') }}
        </x-primary-button>
    </form>

    @if (Route::has('register'))
        <p class="mt-6 text-center text-sm text-ink-500">
            Don't have an account?
            <a href="{{ route('register') }}" class="font-medium text-brix-600 hover:text-brix-700">Sign up</a>
        </p>
    @endif
</x-guest-layout>
