<x-admin-layout title="Partners">
    <div class="rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
        <div class="border-b border-ink-100 p-5">
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <input
                    type="text"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Search by name, email, or slug..."
                    class="w-full max-w-xs rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                >
                <select name="status" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                    <option value="">All statuses</option>
                    @foreach (['active', 'trial', 'suspended'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-ink-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-ink-800">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-100 text-left text-xs font-medium uppercase tracking-wide text-ink-400">
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Owner</th>
                        <th class="px-5 py-3">Stores</th>
                        <th class="px-5 py-3">Commission Rate</th>
                        <th class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($partners as $partner)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.partners.show', $partner) }}" class="font-medium text-ink-900 hover:text-brix-600">{{ $partner->name }}</a>
                            </td>
                            <td class="px-5 py-3 text-ink-600">{{ $partner->owner_email }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ $partner->stores_count }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ $partner->commission_rate }}%</td>
                            <td class="px-5 py-3">
                                <x-status-badge :status="$partner->status === 'active' ? 'active' : ($partner->status === 'suspended' ? 'offline' : 'attention')" :label="ucfirst($partner->status)" />
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-ink-400">No partners found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-ink-100 p-5">
            {{ $partners->links() }}
        </div>
    </div>
</x-admin-layout>
