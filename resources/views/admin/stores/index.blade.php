<x-admin-layout title="Stores">
    <div class="rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
        <div class="border-b border-ink-100 p-5">
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <input
                    type="text"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Search by name or domain..."
                    class="w-full max-w-xs rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                >
                <select name="status" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Store::STATUSES as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <select name="installation" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                    <option value="">All installation states</option>
                    @foreach (\App\Models\Store::INSTALLATION_STATUSES as $status)
                        <option value="{{ $status }}" @selected(($filters['installation'] ?? '') === $status)>{{ ucfirst(strtolower($status)) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-ink-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-ink-800">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-100 text-left text-xs font-medium uppercase tracking-wide text-ink-400">
                        <th class="px-5 py-3">Store</th>
                        <th class="px-5 py-3">Partner</th>
                        <th class="px-5 py-3">Installation</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Last Active</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($stores as $store)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink-900">{{ $store->name }}</p>
                                <p class="text-xs text-ink-400">{{ $store->shop_domain }}</p>
                            </td>
                            <td class="px-5 py-3 text-ink-600">{{ $store->agency->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <x-status-badge :status="$store->installation_status === 'INSTALLED' ? 'active' : ($store->installation_status === 'UNINSTALLED' ? 'offline' : 'attention')" :label="ucfirst(strtolower($store->installation_status))" />
                            </td>
                            <td class="px-5 py-3">
                                <x-status-badge :status="$store->status === 'active' ? 'active' : ($store->status === 'offline' ? 'offline' : 'attention')" :label="ucfirst($store->status)" />
                            </td>
                            <td class="px-5 py-3 text-ink-400">{{ $store->last_active_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-5 py-3 text-right">
                                <form method="POST" action="{{ route('admin.stores.recheck', $store) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-brix-600 hover:text-brix-700">Re-check live</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-ink-400">No stores found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-ink-100 p-5">
            {{ $stores->links() }}
        </div>
    </div>
</x-admin-layout>
