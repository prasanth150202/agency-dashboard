@php use App\Support\Currency; @endphp
<x-admin-layout title="Lead — {{ $lead->company_name ?? $lead->shop_domain ?? $lead->id }}">
    <div class="mb-4">
        <a href="{{ route('admin.leads.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink-700">&larr; All leads</a>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-ink-900">{{ $lead->company_name ?? $lead->store->store_name ?? $lead->shop_domain ?? 'Unattributed lead' }}</h2>
                        <p class="text-sm text-ink-500">{{ $lead->shop_domain ?? 'No Shopify store matched yet' }}</p>
                    </div>
                    <x-lead-stage-badge :stage="$lead->lead_stage" />
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-ink-400">Agency</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $lead->agency->name ?? '—' }}</dd></div>
                    <div><dt class="text-ink-400">Company</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $lead->company_name ?? '—' }}</dd></div>
                    <div><dt class="text-ink-400">Contact</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $lead->contact_name ?? '—' }}</dd></div>
                    <div><dt class="text-ink-400">Email</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $lead->contact_email ?? '—' }}</dd></div>
                    <div><dt class="text-ink-400">Phone</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $lead->contact_phone ?? '—' }}</dd></div>
                    <div><dt class="text-ink-400">Website</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $lead->website ?? '—' }}</dd></div>
                    @if ($lead->trackingLink)
                        <div><dt class="text-ink-400">Referral link</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $lead->trackingLink->name }}</dd></div>
                        <div><dt class="text-ink-400">Campaign</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $lead->trackingLink->campaign_name ?? '—' }}</dd></div>
                        <div><dt class="text-ink-400">Source</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $lead->trackingLink->channel }}</dd></div>
                    @else
                        <div><dt class="text-ink-400">Source</dt><dd class="mt-0.5 font-medium text-ink-900">Manual{{ $lead->creator ? ' — '.$lead->creator->name : '' }}</dd></div>
                    @endif
                    <div><dt class="text-ink-400">Install status</dt><dd class="mt-0.5"><x-lead-install-badge :lead="$lead" /></dd></div>
                    <div><dt class="text-ink-400">Plan</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $lead->store->plan ?? $lead->brix_plan ?? '—' }}</dd></div>
                    <div><dt class="text-ink-400">Created</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $lead->created_at->format('M j, Y') }}</dd></div>
                </dl>

                @if ($lead->notes)
                    <div class="mt-4 border-t border-ink-100 pt-4 text-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-ink-400">Notes</p>
                        <p class="mt-1 whitespace-pre-line text-ink-700">{{ $lead->notes }}</p>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <h3 class="text-sm font-semibold text-ink-900">Timeline</h3>
                <ol class="mt-4 space-y-3 border-l border-ink-100 pl-4 text-sm">
                    @php
                        $timeline = [
                            ['label' => 'Clicked', 'at' => $lead->first_clicked_at],
                            ['label' => 'Contacted', 'at' => $lead->contacted_at],
                            ['label' => 'Installed', 'at' => $lead->installed_at],
                            ['label' => 'Activated', 'at' => $lead->activated_at],
                        ];
                    @endphp
                    @foreach ($timeline as $step)
                        <li class="relative">
                            <span class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full {{ $step['at'] ? 'bg-brix-500' : 'bg-ink-200' }}"></span>
                            <p class="{{ $step['at'] ? 'font-medium text-ink-900' : 'text-ink-400' }}">{{ $step['label'] }}</p>
                            <p class="text-xs text-ink-400">{{ $step['at']?->format('M j, Y g:ia') ?? 'Not yet' }}</p>
                        </li>
                    @endforeach
                    @foreach ($lead->events as $event)
                        <li class="relative">
                            <span class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full bg-ink-400"></span>
                            <p class="font-medium text-ink-900">{{ ucwords(strtolower(str_replace('_', ' ', $event->event_type))) }}</p>
                            <p class="text-xs text-ink-400">{{ $event->created_at->format('M j, Y g:ia') }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <h3 class="text-sm font-semibold text-ink-900">Revenue events</h3>
                <div class="mt-3 divide-y divide-ink-100">
                    @forelse ($revenueEvents as $event)
                        <div class="flex items-center justify-between py-2.5 text-sm">
                            <div>
                                <p class="font-medium text-ink-900">{{ ucfirst($event->revenue_type) }}</p>
                                <p class="text-xs text-ink-400">{{ $event->occurred_at->format('M j, Y') }} · {{ $event->source }}</p>
                            </div>
                            <p class="font-medium text-ink-900">{{ Currency::format((float) $event->revenue_amount, $event->currency) }}</p>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-ink-400">No verified revenue yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <h3 class="text-sm font-semibold text-ink-900">Commission</h3>
                <div class="mt-3 space-y-3">
                    @forelse ($commissions as $commission)
                        <div class="rounded-xl border border-ink-100 p-3 text-sm">
                            <div class="flex items-center justify-between">
                                <p class="font-medium text-ink-900">{{ Currency::format((float) $commission->commission_amount, $commission->currency) }}</p>
                                <x-status-badge
                                    :status="match($commission->status) { 'paid' => 'paid', 'in_payout' => 'in_payout', 'eligible' => 'active', 'reversed', 'cancelled' => 'offline', default => 'attention' }"
                                    :label="ucfirst(str_replace('_', ' ', $commission->status))"
                                />
                            </div>
                            <p class="mt-1 text-xs text-ink-400">Rate {{ (float) $commission->commission_rate }}% · {{ $commission->rate_source === 'store_override' ? 'Store override' : 'Agency default' }}</p>
                            @if ($commission->payouts->isNotEmpty())
                                <p class="mt-1 text-xs text-ink-400">Payout {{ $commission->payouts->first()->payout_code }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-ink-400">No commission earned yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
