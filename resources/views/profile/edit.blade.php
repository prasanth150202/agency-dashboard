<x-app-layout title="Profile">
    <div>
        <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Profile</h2>
        <p class="mt-1 text-sm text-ink-500">Manage your personal account details.</p>
    </div>

    <div class="mt-6 max-w-xl space-y-4">
        <div class="rounded-2xl border border-ink-200/70 bg-white p-6 shadow-subtle">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="rounded-2xl border border-ink-200/70 bg-white p-6 shadow-subtle">
            @include('profile.partials.update-password-form')
        </div>

        <div class="rounded-2xl border border-rose-200 bg-rose-50/40 p-6">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>
