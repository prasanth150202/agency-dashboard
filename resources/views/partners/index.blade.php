<x-app-layout title="Partners">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Partners</h2>
            <p class="mt-1 text-sm text-ink-500">Every agency workspace you're a member of.</p>
        </div>

        <button
            type="button"
            x-data
            x-on:click="$dispatch('open-modal', 'create-partner')"
            class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700"
        >
            <x-lucide-plus class="h-4 w-4" />
            Create new partner
        </button>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($organisations as $org)
            <div class="flex flex-col rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-ink-900 text-sm font-semibold text-white">
                        {{ Str::substr($org->name, 0, 1) }}
                    </div>
                    @if ($currentOrganisation->id === $org->id)
                        <span class="rounded-full bg-brix-50 px-2.5 py-1 text-xs font-medium text-brix-700">Current</span>
                    @endif
                </div>

                <h3 class="mt-3.5 text-sm font-semibold text-ink-900">{{ $org->name }}</h3>
                <p class="mt-0.5 text-xs text-ink-500">{{ $org->stores_count }} {{ Str::plural('store', $org->stores_count) }}</p>
                @if ($org->website)
                    <p class="mt-0.5 truncate text-xs text-ink-400">{{ $org->website }}</p>
                @endif

                @if ($currentOrganisation->id !== $org->id)
                    <form method="POST" action="{{ route('partners.switch') }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="organisation_id" value="{{ $org->id }}">
                        <button type="submit" class="w-full rounded-lg border border-ink-200 px-3 py-2 text-xs font-medium text-ink-700 hover:bg-ink-50">
                            Switch to this partner
                        </button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</x-app-layout>
