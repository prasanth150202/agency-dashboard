@php use App\Services\Referral\AccountMapping as Map; @endphp
<x-app-layout title="Account Mapping">
    <div>
        <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Account Mapping</h2>
        <p class="mt-1 text-sm text-ink-500">
            How your partner account, referred merchants and their BRIX stores connect. Stores are matched by BRIX when a merchant installs,
            never by hand, so referral commission can only follow a real install.
        </p>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-metric-card label="Referred accounts" :value="$summary['referred']" icon="users" />
        <x-metric-card label="Connected" :value="$summary[Map::MAPPED]" icon="circle-check-big" />
        <x-metric-card label="Needs authorization" :value="$summary[Map::NEEDS_ACTION]" icon="shield-alert" />
        <x-metric-card label="Awaiting install" :value="$summary[Map::AWAITING_STORE]" icon="hourglass" />
        <x-metric-card label="Direct stores" :value="$summary['direct']" icon="store" />
    </div>

    <section class="mt-6 rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
        <h3 class="text-sm font-semibold text-ink-900">Your partner account</h3>
        <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
            <div><dt class="text-ink-400">Partner</dt><dd class="font-medium text-ink-900">{{ $partner->name }}</dd></div>
            <div><dt class="text-ink-400">Status</dt><dd class="font-medium text-ink-900">{{ ucfirst($partner->status) }}</dd></div>
            <div><dt class="text-ink-400">Your role</dt><dd class="font-medium text-ink-900">{{ ucfirst($role ?? 'member') }}</dd></div>
        </dl>
        <p class="mt-3 text-xs text-ink-500">Logins with access: {{ $members->pluck('name')->implode(', ') }}</p>
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
        <h3 class="px-5 pt-5 text-sm font-semibold text-ink-900">Referred merchants</h3>
        @if ($leads->isEmpty())
            <p class="px-5 pb-6 pt-2 text-sm text-ink-500">No referred merchants yet. They appear here once someone enters a store through one of your links.</p>
        @else
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-y border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                            <th class="px-5 py-2.5">Merchant</th>
                            <th class="px-5 py-2.5">Referred via</th>
                            <th class="px-5 py-2.5">Mapping</th>
                            <th class="px-5 py-2.5">Install</th>
                            <th class="px-5 py-2.5">Authorization</th>
                            <th class="px-5 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($leads as $lead)
                            @php
                                $state = $mapping->stateOf($lead);
                                $own = $lead->store && (int) $lead->store->agency_id === $agencyId;
                            @endphp
                            <tr>
                                <td class="px-5 py-3 font-medium text-ink-900">{{ $own ? $lead->store->store_name : ($lead->shop_domain ?? '—') }}
                                    @if ($own)<span class="block text-xs font-normal text-ink-400">{{ $lead->store->shop_domain }}</span>@endif
                                </td>
                                <td class="px-5 py-3 text-ink-600">{{ $lead->trackingLink?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3"><x-status-badge :status="Map::badge($state)" :label="Map::label($state)" /></td>
                                <td class="whitespace-nowrap px-5 py-3 text-ink-600">{{ $own ? $lead->store->installation_status : '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-ink-600">{{ $own ? ($mapping->relationship($lead->store) ?? 'Not started') : '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    @if ($own)
                                        <a href="{{ route('stores.show', $lead->store) }}" class="text-sm font-medium text-brix-600 hover:text-brix-700">Open store</a>
                                    @else
                                        <a href="{{ route('leads.show', $lead) }}" class="text-sm font-medium text-brix-600 hover:text-brix-700">View lead</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($leads->hasPages())<div class="border-t border-ink-100 px-5 py-4">{{ $leads->links() }}</div>@endif
        @endif
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
        <h3 class="px-5 pt-5 text-sm font-semibold text-ink-900">Directly connected stores <span class="font-normal text-ink-400">(not from a referral)</span></h3>
        @if ($directStores->isEmpty())
            <p class="px-5 pb-6 pt-2 text-sm text-ink-500">Every store you manage came through a referral, or you have not connected any directly.</p>
        @else
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-ink-100 border-t border-ink-100">
                        @foreach ($directStores as $store)
                            <tr>
                                <td class="px-5 py-3 font-medium text-ink-900">{{ $store->store_name }}<span class="block text-xs font-normal text-ink-400">{{ $store->shop_domain }}</span></td>
                                <td class="px-5 py-3 text-ink-600">{{ $store->installation_status }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $mapping->relationship($store) ?? 'Not started' }}</td>
                                <td class="px-5 py-3 text-right"><a href="{{ route('stores.show', $store) }}" class="text-sm font-medium text-brix-600 hover:text-brix-700">Open store</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($directStores->hasPages())<div class="border-t border-ink-100 px-5 py-4">{{ $directStores->links() }}</div>@endif
        @endif
    </section>
</x-app-layout>
