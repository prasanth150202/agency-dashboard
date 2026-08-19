<x-app-layout title="Settings">
    <div>
        <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Settings</h2>
        <p class="mt-1 text-sm text-ink-500">Manage {{ $organisation->name }}'s workspace details.</p>
    </div>

    <div class="mt-6 max-w-xl rounded-2xl border border-ink-200/70 bg-white p-6 shadow-subtle">
        <h3 class="text-sm font-semibold text-ink-900">Organisation details</h3>

        <form method="POST" action="{{ route('settings.update') }}" class="mt-5 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="settings-name" class="mb-1.5 block text-sm font-medium text-ink-700">Organisation name</label>
                <input
                    id="settings-name"
                    type="text"
                    name="name"
                    value="{{ old('name', $organisation->name) }}"
                    class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                >
                @error('name')
                    <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="settings-website" class="mb-1.5 block text-sm font-medium text-ink-700">Agency website</label>
                <input
                    id="settings-website"
                    type="text"
                    name="website"
                    value="{{ old('website', $organisation->website) }}"
                    placeholder="https://example.com"
                    class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                >
                @error('website')
                    <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-ink-700">Your role</label>
                <p class="text-sm text-ink-500">{{ ucfirst($role ?? 'member') }}</p>
            </div>

            <div class="pt-2">
                <button type="submit" class="rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700">
                    Save changes
                </button>
            </div>
        </form>
    </div>

    <div class="mt-4 max-w-xl rounded-2xl border border-ink-200/70 bg-white p-6 shadow-subtle">
        <h3 class="text-sm font-semibold text-ink-900">Account</h3>
        <p class="mt-1 text-sm text-ink-500">Manage your personal profile and password.</p>
        <a href="{{ route('profile.edit') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-3.5 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50">
            <x-lucide-user class="h-4 w-4" />
            Go to profile
        </a>
    </div>
</x-app-layout>
