<x-admin-layout title="Leads">
    <div class="rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
        <div class="border-b border-ink-100 p-5">
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <input
                    type="text"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Search by domain or store..."
                    class="w-full max-w-xs rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                >
                <select name="agency" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                    <option value="">All agencies</option>
                    @foreach ($agencies as $agency)
                        <option value="{{ $agency->id }}" @selected(($filters['agency'] ?? '') == $agency->id)>{{ $agency->name }}</option>
                    @endforeach
                </select>
                <select name="stage" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                    <option value="">All stages</option>
                    @foreach ($stages as $stage)
                        <option value="{{ $stage }}" @selected(($filters['stage'] ?? '') === $stage)>{{ ucwords(strtolower(str_replace('_', ' ', $stage))) }}</option>
                    @endforeach
                </select>
                <select name="brix_status" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                    <option value="">All BRIX statuses</option>
                    @foreach (['INSTALLED', 'AUTHORIZED', 'ACTIVE', 'UNINSTALLED', 'DISCONNECTED'] as $status)
                        <option value="{{ $status }}" @selected(($filters['brix_status'] ?? '') === $status)>{{ ucfirst(strtolower($status)) }}</option>
                    @endforeach
                </select>
                <select name="channel" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                    <option value="">All sources</option>
                    @foreach ($channels as $channel)
                        <option value="{{ $channel }}" @selected(($filters['channel'] ?? '') === $channel)>{{ $channel }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-ink-200 px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                <button type="submit" class="rounded-lg bg-ink-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-ink-800">Filter</button>
                @if (array_filter($filters))
                    <a href="{{ route('admin.leads.index') }}" class="text-xs font-medium text-ink-400 hover:text-ink-700">Clear</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-100 text-left text-xs font-medium uppercase tracking-wide text-ink-400">
                        <th class="px-5 py-3">Agency</th>
                        <th class="px-5 py-3">Company</th>
                        <th class="px-5 py-3">Contact</th>
                        <th class="px-5 py-3">Email</th>
                        <th class="px-5 py-3">Lead Stage</th>
                        <th class="px-5 py-3">BRIX Status</th>
                        <th class="px-5 py-3">Shopify Store</th>
                        <th class="px-5 py-3 text-right">Revenue</th>
                        <th class="px-5 py-3 text-right">Commission</th>
                        <th class="px-5 py-3">Payout</th>
                        <th class="px-5 py-3">Created</th>
                        <th class="px-5 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($leads as $lead)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3 text-ink-600">{{ $lead->agency->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink-900">{{ $lead->company_name ?? '—' }}</p>
                                @if ($lead->trackingLink)
                                    <p class="text-xs text-ink-400">
                                        {{ $lead->trackingLink->name }}
                                        @if ($lead->trackingLink->campaign_name)
                                            · {{ $lead->trackingLink->campaign_name }}
                                        @endif
                                    </p>
                                @else
                                    <p class="text-xs text-ink-400">Manual</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-ink-600">{{ $lead->contact_name ?? '—' }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ $lead->contact_email ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <x-lead-stage-badge :stage="$lead->lead_stage" />
                            </td>
                            <td class="px-5 py-3"><x-lead-install-badge :lead="$lead" /></td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink-900">{{ $lead->store->store_name ?? $lead->shop_domain ?? 'Unattributed' }}</p>
                                @if ($lead->shop_domain)
                                    <p class="text-xs text-ink-400">{{ $lead->shop_domain }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right text-ink-600">
                                @forelse ($revenue[$lead->id] ?? [] as $currency => $amount)
                                    <div>{{ \App\Support\Currency::format((float) $amount, $currency) }}</div>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td class="px-5 py-3 text-right text-ink-600">
                                @forelse ($commission[$lead->id] ?? [] as $currency => $amount)
                                    <div>{{ \App\Support\Currency::format((float) $amount, $currency) }}</div>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td class="px-5 py-3">
                                @if (isset($commissionStatus[$lead->id]))
                                    <x-status-badge
                                        :status="match($commissionStatus[$lead->id]) { 'paid' => 'paid', 'in_payout' => 'in_payout', 'eligible' => 'active', default => 'attention' }"
                                        :label="ucfirst(str_replace('_', ' ', $commissionStatus[$lead->id]))"
                                    />
                                @else
                                    <span class="text-ink-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-ink-400">{{ $lead->created_at->format('M j, Y') }}</td>
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.leads.show', $lead) }}" class="text-sm font-medium text-brix-600 hover:text-brix-700">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="px-5 py-10 text-center text-ink-400">No leads found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-ink-100 p-5">
            {{ $leads->links() }}
        </div>
    </div>
</x-admin-layout>
