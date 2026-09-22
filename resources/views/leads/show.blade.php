@php use App\Services\Referral\ReferralReporting as Money; use App\Support\Currency; @endphp
<x-app-layout title="Lead">
    <a href="{{ route('leads.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-800">
        <x-lucide-arrow-left class="h-3.5 w-3.5" />
        Leads
    </a>

    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">{{ $lead->store?->store_name ?? $lead->shop_domain ?? $lead->company_name ?? 'Awaiting store' }}</h2>
            <p class="mt-1 text-sm text-ink-500">
                @if ($lead->source === \App\Models\Referral\Lead::SOURCE_MANUAL)
                    Added manually{{ $lead->creator ? ' by '.$lead->creator->name : '' }}
                @else
                    {{ $lead->shop_domain ?? 'Store domain not yet provided' }}
                    @if ($lead->trackingLink)
                        · via {{ $lead->trackingLink->name }} ({{ $lead->trackingLink->channel }})
                    @endif
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-lead-install-badge :lead="$lead" />
            <x-lead-stage-badge :stage="$lead->lead_stage" />
        </div>
    </div>

    @if (session('success'))
        <div class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @elseif (session('error'))
        <div class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ session('error') }}</div>
    @endif

    @if (session('createdLink'))
        @php $createdLink = session('createdLink'); @endphp
        <div x-data="{ copyLink(url) { navigator.clipboard?.writeText(url); } }"
            class="mt-4 flex flex-col gap-3 rounded-xl border border-brix-200 bg-brix-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 text-sm">
                <p class="font-medium text-ink-900">Referral link ready — share this with the lead:</p>
                <p class="mt-0.5 truncate font-mono text-ink-700">{{ $createdLink['url'] }}</p>
            </div>
            <button
                type="button"
                x-on:click="copyLink('{{ $createdLink['url'] }}')"
                class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg border border-brix-300 bg-white px-3.5 py-2 text-sm font-medium text-brix-700 hover:bg-brix-100"
            >
                <x-lucide-copy class="h-4 w-4" />
                Copy Link
            </button>
        </div>
    @endif

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-metric-card label="BRIX Status" :value="$lead->brix_status ?? '—'" icon="store" />
        <x-metric-card label="Plan" :value="$lead->store?->plan ?? $lead->brix_plan ?? '—'" icon="package" />
        <x-metric-card label="Verified Revenue" :value="Money::money($revenue)" icon="trending-up" />
        <x-metric-card label="Commission Earned" :value="Money::money($commission)" icon="indian-rupee" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <h3 class="text-sm font-semibold text-ink-900">Timeline</h3>
                <ol class="mt-4 space-y-4">
                    @forelse ($lead->events as $event)
                        <li class="flex gap-3">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brix-500"></span>
                            <div>
                                <p class="text-sm font-medium text-ink-800">{{ ucwords(strtolower(str_replace('_', ' ', $event->event_type))) }}</p>
                                <p class="text-xs text-ink-500">{{ $event->created_at->format('M j, Y g:i A') }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-ink-500">No recorded activity yet.</li>
                    @endforelse
                </ol>
            </section>

            <section class="overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
                <h3 class="px-5 pt-5 text-sm font-semibold text-ink-900">Revenue &amp; commission</h3>
                @if ($revenueEvents->isEmpty())
                    <p class="px-5 pb-5 pt-2 text-sm text-ink-500">No verified BRIX revenue for this store yet.</p>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-y border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                                    <th class="px-5 py-2.5">Date</th>
                                    <th class="px-5 py-2.5">Type</th>
                                    <th class="px-5 py-2.5 text-right">Revenue</th>
                                    <th class="px-5 py-2.5 text-right">Commission</th>
                                    <th class="px-5 py-2.5">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100">
                                @foreach ($revenueEvents as $event)
                                    @php $c = $commissions->firstWhere('revenue_event_id', $event->id); @endphp
                                    <tr>
                                        <td class="whitespace-nowrap px-5 py-3 text-ink-600">{{ $event->occurred_at->format('M j, Y') }}</td>
                                        <td class="whitespace-nowrap px-5 py-3 text-ink-600">{{ ucfirst($event->revenue_type) }}</td>
                                        <td class="whitespace-nowrap px-5 py-3 text-right text-ink-700">{{ Currency::format($event->revenue_amount, $event->currency) }}</td>
                                        <td class="whitespace-nowrap px-5 py-3 text-right text-ink-700">{{ $c ? Currency::format($c->commission_amount, $c->currency) : '—' }}</td>
                                        <td class="whitespace-nowrap px-5 py-3 text-ink-500">{{ $c ? ucfirst($c->effective_status) : 'Not commissioned' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <aside class="space-y-6" x-data="{ copyLink(url) { navigator.clipboard?.writeText(url); } }">
            @if ($lead->trackingLink)
                <section class="rounded-2xl border border-ink-200/70 bg-white p-5 text-sm shadow-subtle">
                    <h3 class="font-semibold text-ink-900">Referral Link</h3>
                    @if ($lead->brix_status)
                        <p class="mt-2 text-ink-500">Installed via this link.</p>
                    @else
                        <p class="mt-2 text-ink-500">Send this link so {{ $lead->company_name ?? 'they' }} can install BRIX. Their status here updates automatically once they do.</p>
                        <div class="mt-3 flex items-center gap-2 rounded-lg border border-ink-200 bg-ink-50 px-3 py-2">
                            <span class="min-w-0 flex-1 truncate font-mono text-xs text-ink-700">{{ $lead->trackingLink->referral_url }}</span>
                            <button type="button" x-on:click="copyLink('{{ $lead->trackingLink->referral_url }}')"
                                class="shrink-0 text-ink-400 hover:text-brix-600">
                                <x-lucide-copy class="h-4 w-4" />
                            </button>
                        </div>
                    @endif
                </section>
            @elseif ($lead->brix_status)
                <section class="rounded-2xl border border-ink-200/70 bg-white p-5 text-sm shadow-subtle">
                    <h3 class="font-semibold text-ink-900">Shopify Status</h3>
                    <p class="mt-2 text-ink-500">This store was already on Shopify with BRIX when added, so no referral link was needed.</p>
                </section>
            @endif

            @if ($lead->company_name || $lead->contact_name || $lead->contact_email)
                <section class="rounded-2xl border border-ink-200/70 bg-white p-5 text-sm shadow-subtle">
                    <h3 class="font-semibold text-ink-900">Contact</h3>
                    <dl class="mt-3 space-y-2 text-ink-600">
                        <div class="flex justify-between gap-3"><dt class="shrink-0 text-ink-400">Company</dt><dd class="text-right">{{ $lead->company_name ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="shrink-0 text-ink-400">Contact</dt><dd class="text-right">{{ $lead->contact_name ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="shrink-0 text-ink-400">Email</dt><dd class="text-right">{{ $lead->contact_email ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="shrink-0 text-ink-400">Phone</dt><dd class="text-right">{{ $lead->contact_phone ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="shrink-0 text-ink-400">Website</dt><dd class="text-right">{{ $lead->website ?? '—' }}</dd></div>
                    </dl>
                    @if ($lead->notes)
                        <div class="mt-3 border-t border-ink-100 pt-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-ink-400">Notes</p>
                            <p class="mt-1 whitespace-pre-line text-ink-700">{{ $lead->notes }}</p>
                        </div>
                    @endif
                </section>
            @endif

            <section class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <h3 class="text-sm font-semibold text-ink-900">Your outreach</h3>
                @if ($lead->canChangeStageManually())
                    <form method="POST" action="{{ route('leads.stage', $lead) }}" class="mt-3 space-y-3">
                        @csrf
                        <select name="lead_stage" class="w-full rounded-lg border-ink-200 text-sm focus:border-brix-500 focus:ring-brix-500">
                            @foreach ($manualStages as $stage)
                                <option value="{{ $stage }}" @selected($lead->lead_stage === $stage)>{{ ucwords(strtolower(str_replace('_', ' ', $stage))) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="w-full rounded-lg bg-brix-600 px-4 py-2 text-sm font-medium text-white hover:bg-brix-700">Update stage</button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-ink-500">This lead is matched to a BRIX store, so its stage follows the store's real install status.</p>
                @endif
            </section>

            <section class="rounded-2xl border border-ink-200/70 bg-white p-5 text-sm shadow-subtle">
                <h3 class="font-semibold text-ink-900">Key dates</h3>
                <dl class="mt-3 space-y-2 text-ink-600">
                    <div class="flex justify-between"><dt>First clicked</dt><dd>{{ $lead->first_clicked_at?->format('M j, Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt>Contacted</dt><dd>{{ $lead->contacted_at?->format('M j, Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt>Installed</dt><dd>{{ $lead->installed_at?->format('M j, Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt>Activated</dt><dd>{{ $lead->activated_at?->format('M j, Y') ?? '—' }}</dd></div>
                </dl>
            </section>
        </aside>
    </div>
</x-app-layout>
