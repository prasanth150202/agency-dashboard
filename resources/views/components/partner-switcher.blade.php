<div>
    <div class="flex items-center gap-2.5 border-b border-ink-100 px-4 py-3.5">
        <button type="button" x-on:click="view = 'menu'" class="text-ink-400 hover:text-ink-700">
            <x-lucide-arrow-left class="h-4 w-4" />
        </button>
        <p class="text-sm font-semibold text-ink-900">Partners</p>
    </div>

    <div class="max-h-72 overflow-y-auto p-1.5">
        @foreach ($availableOrganisations as $org)
            <form method="POST" action="{{ route('partners.switch') }}">
                @csrf
                <input type="hidden" name="organisation_id" value="{{ $org->id }}">
                <button
                    type="submit"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition hover:bg-ink-50 {{ $currentOrganisation->id === $org->id ? 'bg-brix-50' : '' }}"
                >
                    <span class="h-2 w-2 shrink-0 rounded-full {{ $currentOrganisation->id === $org->id ? 'bg-brix-600' : 'border border-ink-300' }}"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-ink-900">{{ $org->name }}</span>
                        <span class="block text-xs text-ink-400">{{ $org->stores_count }} {{ Str::plural('store', $org->stores_count) }}</span>
                    </span>
                </button>
            </form>
        @endforeach
    </div>

    <div class="border-t border-ink-100 p-1.5">
        <button
            type="button"
            x-on:click="close(); $dispatch('open-modal', 'create-partner')"
            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-brix-600 hover:bg-brix-50"
        >
            <x-lucide-plus class="h-4 w-4" />
            Create new partner
        </button>
        <a
            href="{{ route('partners.index') }}"
            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-ink-600 hover:bg-ink-50"
        >
            <x-lucide-building-2 class="h-4 w-4" />
            Manage all partners
        </a>
    </div>
</div>
