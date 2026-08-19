<x-modal name="create-organisation" :open-on-load="$errors->organisation->any()">
    <div class="flex items-start justify-between">
        <div>
            <h2 class="text-base font-semibold text-ink-900">Create new organisation</h2>
            <p class="mt-1 text-sm text-ink-500">Add a new agency workspace to manage its own stores.</p>
        </div>
        <button type="button" x-on:click="show = false" class="text-ink-400 hover:text-ink-700">
            <x-lucide-x class="h-4 w-4" />
        </button>
    </div>

    <form method="POST" action="{{ route('organisations.store') }}" class="mt-5 space-y-4">
        @csrf

        <div>
            <label for="org-name" class="mb-1.5 block text-sm font-medium text-ink-700">Organisation name</label>
            <input
                id="org-name"
                type="text"
                name="name"
                value="{{ old('name') }}"
                placeholder="e.g. Northwind Growth Studio"
                class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 placeholder-ink-400 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
            >
            @error('name', 'organisation')
                <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="org-website" class="mb-1.5 block text-sm font-medium text-ink-700">Agency website</label>
            <input
                id="org-website"
                type="text"
                name="website"
                value="{{ old('website') }}"
                placeholder="https://example.com"
                class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 placeholder-ink-400 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
            >
            @error('website', 'organisation')
                <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mt-6 flex justify-end gap-2.5">
            <button
                type="button"
                x-on:click="show = false"
                class="rounded-lg border border-ink-200 px-3.5 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50"
            >
                Cancel
            </button>
            <button
                type="submit"
                class="rounded-lg bg-brix-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-brix-700"
            >
                Create organisation
            </button>
        </div>
    </form>
</x-modal>
