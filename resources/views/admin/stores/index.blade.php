<x-admin-layout title="Stores">
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-ink-500">
            {{ number_format($totalInstalled) }} store{{ $totalInstalled === 1 ? '' : 's' }} installed on BRIX, live from cartninja
            — {{ number_format($devCount) }} look{{ $devCount === 1 ? 's' : '' }} like dev/test stores.
        </p>
    </div>

    <div class="rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
        <div class="border-b border-ink-100 p-5">
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <input
                    type="text"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Search by shop domain..."
                    class="w-full max-w-xs rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                >
                <select name="dev" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                    <option value="" @selected(($filters['dev'] ?? '') === '')>All stores</option>
                    <option value="real" @selected(($filters['dev'] ?? '') === 'real')>Real stores only</option>
                    <option value="dev" @selected(($filters['dev'] ?? '') === 'dev')>Dev/test stores only</option>
                </select>
                <button type="submit" class="rounded-lg bg-ink-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-ink-800">Filter</button>
            </form>
            <p class="mt-2 text-xs text-ink-400">
                "Dev/test" is a live check, not a guess from the name: Shopify redirects a store's *.myshopify.com URL to its connected custom domain when one exists —
                a shop still sitting on *.myshopify.com is flagged dev/test. A genuine but very new store with no custom domain yet can still be misflagged this way.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-100 text-left text-xs font-medium uppercase tracking-wide text-ink-400">
                        <th class="px-5 py-3">Store</th>
                        <th class="px-5 py-3">Partner</th>
                        <th class="px-5 py-3">Plan</th>
                        <th class="px-5 py-3">Subscription</th>
                        <th class="px-5 py-3">Installed</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink-900">
                                    {{ $row->shop_domain }}
                                    @if ($row->is_dev)
                                        <span class="ml-1.5 rounded-full bg-ink-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-ink-500" title="No custom domain redirect detected — usually a dev/test store, but a brand-new real one can look like this too">Dev?</span>
                                    @endif
                                </p>
                            </td>
                            <td class="px-5 py-3 text-ink-600">
                                @if ($row->partner)
                                    {{ $row->partner }}
                                @else
                                    <span class="text-ink-400">No partner</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <x-status-badge
                                    :status="$row->plan_key === 'free' ? 'inactive' : 'active'"
                                    :label="$row->plan_label"
                                />
                            </td>
                            <td class="px-5 py-3">
                                @if ($row->is_trial)
                                    <x-status-badge status="attention" :label="'Trial — ends '.\Illuminate\Support\Carbon::parse($row->trial_ends_on)->format('M j, Y')" />
                                @else
                                    <x-status-badge
                                        :status="match ($row->subscription_status) {
                                            'ACTIVE' => 'active',
                                            'FREE' => 'inactive',
                                            'FROZEN', 'EXPIRED' => 'attention',
                                            'CANCELLED' => 'offline',
                                            default => 'inactive',
                                        }"
                                        :label="ucfirst(strtolower($row->subscription_status))"
                                    />
                                @endif
                            </td>
                            <td class="px-5 py-3 text-ink-400">{{ \Illuminate\Support\Carbon::parse($row->installed_at)->format('M j, Y') }}</td>
                            <td class="px-5 py-3 text-right">
                                @if ($row->local_store)
                                    <form method="POST" action="{{ route('admin.stores.recheck', $row->local_store) }}">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium text-brix-600 hover:text-brix-700">Re-check live</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-ink-400">No stores found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-ink-100 p-5">
            {{ $shops->links() }}
        </div>
    </div>
</x-admin-layout>
