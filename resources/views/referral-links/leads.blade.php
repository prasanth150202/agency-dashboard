@php use App\Support\Currency; @endphp
<x-app-layout title="Leads">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('referral-links.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-800">
                <x-lucide-arrow-left class="h-3.5 w-3.5" />
                Referral Links
            </a>
            <h2 class="mt-2 text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">{{ $trackingLink->name }}</h2>
            <p class="mt-1 text-sm text-ink-500">
                {{ $trackingLink->channel }} · {{ $trackingLink->campaign_name ?? 'No campaign' }} · <span class="font-mono">{{ $trackingLink->code }}</span>
            </p>
        </div>

        <x-status-badge
            :status="$trackingLink->status === 'ACTIVE' ? 'active' : 'inactive'"
            :label="ucfirst(strtolower($trackingLink->status))"
        />
    </div>

    @if ($leads->isEmpty())
        <div class="mt-8 rounded-2xl border border-dashed border-ink-200 py-16 text-center">
            <p class="text-sm font-medium text-ink-700">No leads yet</p>
            <p class="mt-1 text-sm text-ink-500">Leads appear here once a merchant clicks this link and BRIX confirms their store installation.</p>
        </div>
    @else
        <div class="mt-6 overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                            <th class="px-5 py-3">Store</th>
                            <th class="px-5 py-3">Referral Source</th>
                            <th class="px-5 py-3">Lead Stage</th>
                            <th class="px-5 py-3">BRIX Status</th>
                            <th class="px-5 py-3">Plan</th>
                            <th class="px-5 py-3 text-right">Revenue</th>
                            <th class="px-5 py-3 text-right">Commission</th>
                            <th class="px-5 py-3">First Clicked</th>
                            <th class="px-5 py-3">Installed</th>
                            <th class="px-5 py-3">Activated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($leads as $lead)
                            <tr class="hover:bg-ink-50">
                                <td class="px-5 py-3.5 font-medium text-ink-900">{{ $lead->store?->store_name ?? $lead->shop_domain ?? 'Unattributed' }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-ink-600">{{ $trackingLink->channel }}{{ $trackingLink->campaign_name ? ' · '.$trackingLink->campaign_name : '' }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <x-status-badge
                                        :status="match($lead->lead_stage) {
                                            'ACTIVE' => 'active',
                                            'INSTALLED', 'INSTALL_STARTED', 'CONTACTED', 'INTERESTED' => 'attention',
                                            'NOT_INTERESTED', 'LOST' => 'offline',
                                            default => 'inactive',
                                        }"
                                        :label="ucwords(strtolower(str_replace('_', ' ', $lead->lead_stage)))"
                                    />
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-ink-600">{{ $lead->brix_status ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-ink-600">{{ $lead->store?->plan ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-right text-ink-600">{{ isset($revenue[$lead->id]) ? Currency::format($revenue[$lead->id], $revenueCurrency) : '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-right text-ink-600">{{ isset($commission[$lead->id]) ? Currency::format($commission[$lead->id], $revenueCurrency) : '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-ink-500">{{ $lead->first_clicked_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-ink-500">{{ $lead->installed_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-ink-500">{{ $lead->activated_at?->format('M j, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($leads->hasPages())
                <div class="border-t border-ink-100 px-5 py-4">
                    {{ $leads->links() }}
                </div>
            @endif
        </div>
    @endif
</x-app-layout>
