@php use App\Support\Currency; @endphp
<x-admin-layout title="Referral Links">
    <div class="mb-5">
        <h2 class="text-lg font-semibold text-ink-900">Referral links</h2>
        <p class="text-sm text-ink-500">Every agency's referral links.</p>
    </div>

    <form method="GET" class="mb-5 flex flex-wrap items-center gap-3">
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, campaign, code..." class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
        <select name="agency" onchange="this.form.submit()" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
            <option value="">All agencies</option>
            @foreach ($agencies as $agency)
                <option value="{{ $agency->id }}" @selected(($filters['agency'] ?? '') == $agency->id)>{{ $agency->name }}</option>
            @endforeach
        </select>
        <select name="channel" onchange="this.form.submit()" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
            <option value="">All channels</option>
            @foreach ($channels as $channel)
                <option value="{{ $channel }}" @selected(($filters['channel'] ?? '') === $channel)>{{ $channel }}</option>
            @endforeach
        </select>
        <select name="status" onchange="this.form.submit()" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
            <option value="">All statuses</option>
            <option value="ACTIVE" @selected(($filters['status'] ?? '') === 'ACTIVE')>Active</option>
            <option value="INACTIVE" @selected(($filters['status'] ?? '') === 'INACTIVE')>Inactive</option>
        </select>
        <button type="submit" class="rounded-lg bg-ink-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-ink-800">Filter</button>
    </form>

    <div class="rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-100 text-left text-xs font-medium uppercase tracking-wide text-ink-400">
                        <th class="px-5 py-3">Link</th>
                        <th class="px-5 py-3">Agency</th>
                        <th class="px-5 py-3">Channel</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Clicks</th>
                        <th class="px-5 py-3 text-right">Leads</th>
                        <th class="px-5 py-3 text-right">Active</th>
                        <th class="px-5 py-3 text-right">Revenue</th>
                        <th class="px-5 py-3 text-right">Commission</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($links as $link)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink-900">{{ $link->name }}</p>
                                <p class="text-xs text-ink-400">{{ $link->campaign_name ?? '—' }} · <span class="font-mono">{{ $link->code }}</span></p>
                            </td>
                            <td class="px-5 py-3 text-ink-600">{{ $link->agency->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ $link->channel }}</td>
                            <td class="px-5 py-3"><x-status-badge :status="$link->status === 'ACTIVE' ? 'active' : 'inactive'" :label="ucfirst(strtolower($link->status))" /></td>
                            <td class="px-5 py-3 text-right text-ink-900">{{ $link->clicks_count }}</td>
                            <td class="px-5 py-3 text-right text-ink-900">{{ $link->leads_count }}</td>
                            <td class="px-5 py-3 text-right text-ink-900">{{ $link->active_stores_count }}</td>
                            <td class="px-5 py-3 text-right text-ink-600">
                                @forelse ($revenue[$link->id] ?? [] as $row)
                                    <div>{{ Currency::format((float) $row->total, $row->currency) }}</div>
                                @empty —
                                @endforelse
                            </td>
                            <td class="px-5 py-3 text-right text-ink-600">
                                @forelse ($commission[$link->id] ?? [] as $row)
                                    <div>{{ Currency::format((float) $row->total, $row->currency) }}</div>
                                @empty —
                                @endforelse
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-10 text-center text-ink-400">No referral links found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-ink-100 p-5">{{ $links->links() }}</div>
    </div>
</x-admin-layout>
