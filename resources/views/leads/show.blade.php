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
            @can('delete', $lead)
                <form
                    method="POST"
                    action="{{ route('leads.destroy', $lead) }}"
                    x-data
                    x-on:submit="if (! confirm('Delete this lead? This can\'t be undone.')) $event.preventDefault();"
                >
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        title="Delete this lead"
                        class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2.5 py-1.5 text-sm font-medium text-rose-600 hover:bg-rose-50"
                    >
                        <x-lucide-trash-2 class="h-3.5 w-3.5" />
                        Delete
                    </button>
                </form>
            @endcan
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

    @php
        $date = fn ($d) => $d?->format('M j, Y') ?? '—';
        $row = 'flex justify-between gap-3 py-1';
    @endphp

    {{-- Lead stage and BRIX status are deliberately separate facts. --}}
    <section class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Lead summary">
        <div class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle">
            <p class="text-xs font-medium text-ink-500">Lead Stage <span class="font-normal text-ink-400">· your pipeline</span></p>
            <div class="mt-2"><x-lead-stage-badge :stage="$lead->lead_stage" /></div>
        </div>
        <div class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle">
            <p class="text-xs font-medium text-ink-500">BRIX Status <span class="font-normal text-ink-400">· install state</span></p>
            <div class="mt-2 flex items-center justify-between gap-2">
                <p class="text-sm font-semibold text-ink-900">{{ $lead->brix_status ? str_replace('_', ' ', $lead->brix_status) : 'NOT INSTALLED' }}</p>
                @if ($lead->store_id === null && $lead->shop_domain !== null)
                    <form method="POST" action="{{ route('leads.recheck-install', $lead) }}">
                        @csrf
                        <button
                            type="submit"
                            title="Re-check BRIX install status for this shop"
                            class="inline-flex items-center gap-1 rounded-lg border border-ink-200 px-2 py-1 text-xs font-medium text-ink-600 hover:bg-ink-50"
                        >
                            <x-lucide-refresh-cw class="h-3 w-3" />
                            Recheck
                        </button>
                    </form>
                @endif
            </div>
        </div>
        <x-kpi-card label="Verified Revenue" :value="Money::money($revenue)" context="all time, this lead" icon="trending-up" />
        <x-kpi-card label="Commission Earned" :value="Money::money($commission)" context="pending, available or paid" icon="hand-coins" />
    </section>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <section class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle" aria-labelledby="timeline-heading" x-data="{ all: false }">
                <div class="flex items-baseline justify-between">
                    <h3 id="timeline-heading" class="text-sm font-semibold text-ink-900">Timeline</h3>
                    <span class="text-[11px] text-ink-400">{{ count($timeline) }} recorded {{ Str::plural('event', count($timeline)) }}</span>
                </div>
                <ol class="relative mt-3 border-l border-ink-200 pl-5">
                    @foreach ($timeline as $i => $item)
                        <li class="relative pb-4 last:pb-0" @if ($i >= 8) x-show="all" x-cloak @endif>
                            <span @class(['absolute -left-[25px] top-1 h-2.5 w-2.5 rounded-full ring-4 ring-white',
                                'bg-emerald-500' => $item['tone'] === 'success', 'bg-rose-500' => $item['tone'] === 'danger',
                                'bg-blue-500' => $item['tone'] === 'info', 'bg-ink-400' => $item['tone'] === 'neutral']) aria-hidden="true"></span>
                            <p class="text-sm font-medium text-ink-900">{{ $item['label'] }}</p>
                            <p class="text-xs text-ink-500">
                                <time datetime="{{ $item['at']->toIso8601String() }}">{{ $item['at']->format('M j, Y g:i A') }}</time>
                                @if ($item['detail']) · {{ $item['detail'] }} @endif
                            </p>
                        </li>
                    @endforeach
                </ol>
                @if (count($timeline) > 8)
                    <button type="button" x-on:click="all = !all" class="mt-2 text-xs font-medium text-ink-600 hover:text-ink-900"
                        x-text="all ? 'Show less' : 'Show all {{ count($timeline) }} events'"></button>
                @endif
            </section>

            <section class="overflow-hidden rounded-xl border border-ink-200/70 bg-white shadow-subtle">
                <h3 class="px-4 pt-4 text-sm font-semibold text-ink-900">Financial · Revenue &amp; commission</h3>
                @if ($revenueEvents->isEmpty())
                    <p class="px-4 pb-4 pt-2 text-sm text-ink-500">No verified BRIX revenue for this store yet.</p>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-y border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                                    <th class="px-4 py-2.5">Date</th>
                                    <th class="px-4 py-2.5">Type</th>
                                    <th class="px-4 py-2.5 text-right">Revenue</th>
                                    <th class="px-4 py-2.5 text-right">Commission</th>
                                    <th class="px-4 py-2.5">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100">
                                @foreach ($revenueEvents as $event)
                                    @php $c = $commissions->firstWhere('revenue_event_id', $event->id); @endphp
                                    <tr class="hover:bg-ink-50">
                                        <td class="whitespace-nowrap px-4 py-2.5 text-ink-600">{{ $event->occurred_at->format('M j, Y') }}</td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-ink-600">{{ ucfirst($event->revenue_type) }}</td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-ink-700">{{ Currency::format($event->revenue_amount, $event->currency) }}</td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-ink-700">{{ $c ? Currency::format($c->commission_amount, $c->currency) : '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-ink-500">{{ $c ? ucfirst(str_replace('_', ' ', $c->effective_status)) : 'Not commissioned' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <aside class="space-y-4">
            @if ($lead->trackingLink && ! $lead->brix_status)
                <section class="rounded-xl border border-blue-200 bg-blue-50/60 p-4 text-sm" x-data="copyText(@js($lead->trackingLink->referral_url))">
                    <h3 class="font-semibold text-ink-900">Install link</h3>
                    <p class="mt-1 text-xs text-ink-600">Send this so {{ $lead->company_name ?? 'they' }} can install BRIX{{ $lead->shop_domain ? ' on '.$lead->shop_domain : '' }}. The status here updates automatically once they do.</p>
                    <div class="mt-3 flex items-center gap-2 rounded-lg border border-blue-200 bg-white px-3 py-2">
                        <span class="min-w-0 flex-1 truncate font-mono text-xs text-ink-700">{{ $lead->trackingLink->referral_url }}</span>
                        <button type="button" x-on:click="copy()" class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-ink-600 hover:text-ink-900" :aria-label="copied ? 'Copied' : 'Copy link'">
                            <x-lucide-copy class="h-3.5 w-3.5" x-show="!copied" aria-hidden="true" />
                            <x-lucide-check class="h-3.5 w-3.5 text-emerald-600" x-show="copied" x-cloak aria-hidden="true" />
                            <span x-text="copied ? 'Copied' : 'Copy'"></span>
                        </button>
                    </div>
                </section>
            @endif

            <section class="rounded-xl border border-ink-200/70 bg-white p-4 text-sm shadow-subtle">
                <h3 class="font-semibold text-ink-900">Attribution</h3>
                <dl class="mt-2 divide-y divide-ink-100 text-ink-600">
                    <div class="{{ $row }}"><dt class="text-ink-400">Source</dt><dd class="text-right font-medium text-ink-800">{{ $sourceLabel }}</dd></div>
                    @if ($lead->trackingLink)
                        <div class="{{ $row }}"><dt class="text-ink-400">Referral link</dt>
                            <dd class="min-w-0 text-right"><a href="{{ route('referral-links.show', $lead->trackingLink) }}" class="font-medium text-ink-800 underline decoration-ink-300 hover:text-ink-900">{{ $lead->trackingLink->name }}</a>
                                <span class="block font-mono text-[11px] text-ink-400">{{ $lead->trackingLink->code }} · {{ $lead->trackingLink->channel }}</span></dd></div>
                    @endif
                    <div class="{{ $row }}"><dt class="text-ink-400">First clicked</dt><dd>{{ $date($lead->first_clicked_at) }}</dd></div>
                    <div class="{{ $row }}"><dt class="text-ink-400">Contacted</dt><dd>{{ $date($lead->contacted_at) }}</dd></div>
                </dl>
            </section>

            <section class="rounded-xl border border-ink-200/70 bg-white p-4 text-sm shadow-subtle">
                <h3 class="font-semibold text-ink-900">BRIX</h3>
                <dl class="mt-2 divide-y divide-ink-100 text-ink-600">
                    <div class="{{ $row }}"><dt class="text-ink-400">Status</dt><dd class="text-right"><x-lead-install-badge :lead="$lead" /></dd></div>
                    <div class="{{ $row }}"><dt class="text-ink-400">Store</dt><dd class="min-w-0 truncate text-right">{{ $lead->shop_domain ?? '—' }}</dd></div>
                    <div class="{{ $row }}"><dt class="text-ink-400">Plan</dt><dd>{{ $lead->store?->plan ?? $lead->brix_plan ?? '—' }}</dd></div>
                    <div class="{{ $row }}"><dt class="text-ink-400">Installed</dt><dd>{{ $date($lead->installed_at) }}</dd></div>
                    <div class="{{ $row }}"><dt class="text-ink-400">Authorized</dt><dd>{{ $date($authorizedAt) }}</dd></div>
                    <div class="{{ $row }}"><dt class="text-ink-400">Activated</dt><dd>{{ $date($lead->activated_at) }}</dd></div>
                </dl>
                @if ($lead->store)
                    <a href="{{ route('stores.show', $lead->store) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-ink-600 hover:text-ink-900">Open store <x-lucide-arrow-right class="h-3 w-3" aria-hidden="true" /></a>
                @endif
            </section>

            @if ($lead->company_name || $lead->contact_name || $lead->contact_email)
                <section class="rounded-xl border border-ink-200/70 bg-white p-4 text-sm shadow-subtle">
                    <h3 class="font-semibold text-ink-900">Contact</h3>
                    <dl class="mt-2 divide-y divide-ink-100 text-ink-600">
                        <div class="{{ $row }}"><dt class="shrink-0 text-ink-400">Company</dt><dd class="text-right">{{ $lead->company_name ?? '—' }}</dd></div>
                        <div class="{{ $row }}"><dt class="shrink-0 text-ink-400">Contact</dt><dd class="text-right">{{ $lead->contact_name ?? '—' }}</dd></div>
                        <div class="{{ $row }}"><dt class="shrink-0 text-ink-400">Email</dt><dd class="min-w-0 break-all text-right">@if ($lead->contact_email)<a href="mailto:{{ $lead->contact_email }}" class="hover:text-ink-900">{{ $lead->contact_email }}</a>@else — @endif</dd></div>
                        <div class="{{ $row }}"><dt class="shrink-0 text-ink-400">Phone</dt><dd class="text-right">{{ $lead->contact_phone ?? '—' }}</dd></div>
                        <div class="{{ $row }}"><dt class="shrink-0 text-ink-400">Website</dt><dd class="min-w-0 break-all text-right">{{ $lead->website ?? '—' }}</dd></div>
                    </dl>
                    @if ($lead->notes)
                        <div class="mt-2 border-t border-ink-100 pt-2">
                            <p class="text-xs font-medium uppercase tracking-wide text-ink-400">Notes</p>
                            <p class="mt-1 whitespace-pre-line text-ink-700">{{ $lead->notes }}</p>
                        </div>
                    @endif
                </section>
            @endif

            <section class="rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle">
                <h3 class="text-sm font-semibold text-ink-900">Your outreach</h3>
                @if ($lead->canChangeStageManually())
                    <form method="POST" action="{{ route('leads.stage', $lead) }}" class="mt-3 space-y-3">
                        @csrf
                        <label for="lead_stage" class="sr-only">Lead stage</label>
                        <select id="lead_stage" name="lead_stage" class="w-full rounded-lg border-ink-200 text-sm focus:border-brix-500 focus:ring-brix-500">
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
        </aside>
    </div>
</x-app-layout>
