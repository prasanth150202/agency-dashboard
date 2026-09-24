@php use App\Services\Referral\ReferralReporting as Money; @endphp
<x-app-layout title="Leads">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Leads</h2>
            <p class="mt-1 text-sm text-ink-500">Every merchant your referral links have brought to BRIX, from first click through activation.</p>
        </div>
        <button
            type="button"
            x-data
            x-on:click="$dispatch('open-modal', 'add-lead')"
            class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700"
        >
            <x-lucide-plus class="h-4 w-4" />
            Add Lead
        </button>
    </div>

    @if (session('success'))
        <div class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
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
        $viewTabs = [
            'all' => 'All', 'in_review' => 'In Review', 'install_started' => 'Install Started',
            'installed' => 'Installed', 'active' => 'Active / Won', 'churned' => 'Churned', 'lost' => 'Lost',
        ];
        $activeView = $filters['view'] ?? 'all';
        $periodQuery = $period->key === 'all' ? [] : $period->query();
        $url = fn (array $overrides) => route('leads.index', array_filter(array_merge($filters, $periodQuery, $overrides), fn ($v) => $v !== null && $v !== ''));
        $stageLabel = fn (string $s) => ucwords(strtolower(str_replace('_', ' ', $s)));
        $pipeline = [\App\Models\Referral\Lead::STAGE_NEW, 'CONTACTED', 'INTERESTED', 'INSTALL_STARTED', 'INSTALLED', 'ACTIVE'];
        $pipelineTop = max(1, ...array_map(fn ($s) => (int) ($stageCounts[$s] ?? 0), $pipeline));
        $activeStage = $filters['stage'] ?? null;
    @endphp

    {{-- Period + source --}}
    <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <x-period-filter :period="$period" :options="['all', '7d', '30d', '3m', '6m', 'ytd', 'custom']"
            :keep="array_intersect_key($filters, array_flip(['search', 'stage', 'link', 'channel', 'view', 'source', 'reached']))" />
        <nav class="flex flex-wrap items-center gap-1.5 text-xs" aria-label="Lead source">
            <span class="text-ink-400">Source</span>
            @foreach (['' => 'All'] + $sources as $key => $label)
                @php $on = ($filters['source'] ?? '') === $key; @endphp
                <a href="{{ $url(['source' => $key ?: null, 'page' => null]) }}" @if ($on) aria-current="true" @endif
                    @class(['rounded-full border px-2.5 py-1 font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brix-600',
                        'border-ink-900 bg-ink-900 text-white' => $on, 'border-ink-200 bg-white text-ink-600 hover:border-ink-300 hover:text-ink-900' => ! $on])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    @if ($reached || $period->key !== 'all')
        <p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-ink-500">
            <x-lucide-calendar-days class="h-3.5 w-3.5" aria-hidden="true" />
            @if ($reached)
                Leads that <span class="font-medium text-ink-800">{{ $reached === 'installed' ? 'installed BRIX' : 'activated' }}</span> — {{ strtolower($period->label()) }}.
            @else
                Leads created {{ strtolower($period->label()) }}.
            @endif
            <a href="{{ route('leads.index') }}" class="font-medium text-ink-700 underline decoration-ink-300 hover:text-ink-900">Clear</a>
        </p>
    @endif

    {{-- Stage analytics: every count is a real filter --}}
    <section class="mt-4 rounded-xl border border-ink-200/70 bg-white p-4 shadow-subtle" aria-label="Leads by stage">
        <div class="grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-9">
            <a href="{{ $url(['stage' => null, 'page' => null]) }}" @class(['rounded-lg border px-3 py-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brix-600',
                'border-ink-900 bg-ink-900 text-white' => ! $activeStage, 'border-ink-200/70 hover:border-ink-300 hover:bg-ink-50' => $activeStage])>
                <span @class(['block text-[11px] font-medium', 'text-ink-300' => ! $activeStage, 'text-ink-500' => $activeStage])>Total</span>
                <span class="block text-lg font-semibold tabular-nums">{{ number_format($totalLeads) }}</span>
            </a>
            @foreach ($stages as $stage)
                @php $on = $activeStage === $stage; @endphp
                <a href="{{ $url(['stage' => $on ? null : $stage, 'page' => null]) }}" @if ($on) aria-current="true" @endif
                    @class(['rounded-lg border px-3 py-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brix-600',
                        'border-ink-900 bg-ink-900 text-white' => $on, 'border-ink-200/70 hover:border-ink-300 hover:bg-ink-50' => ! $on])>
                    <span @class(['block truncate text-[11px] font-medium', 'text-ink-300' => $on, 'text-ink-500' => ! $on])>{{ $stageLabel($stage) }}</span>
                    <span class="block text-lg font-semibold tabular-nums">{{ number_format($stageCounts[$stage] ?? 0) }}</span>
                </a>
            @endforeach
        </div>

        <div class="mt-4">
            <p class="text-[11px] font-medium uppercase tracking-wide text-ink-400">Pipeline</p>
            <ol class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($pipeline as $i => $stage)
                    @php $count = (int) ($stageCounts[$stage] ?? 0); @endphp
                    <li>
                        <a href="{{ $url(['stage' => $stage, 'page' => null]) }}" class="group block rounded-lg px-1 py-1 transition hover:bg-ink-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brix-600">
                            <span class="flex items-center justify-between text-[11px]">
                                <span class="flex items-center gap-1 text-ink-600 group-hover:text-ink-900">
                                    @if ($i > 0)<x-lucide-chevron-right class="h-3 w-3 text-ink-300" aria-hidden="true" />@endif{{ $stageLabel($stage) }}
                                </span>
                                <span class="font-semibold tabular-nums text-ink-900">{{ $count }}</span>
                            </span>
                            <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-ink-100" aria-hidden="true">
                                <span @class(['block h-full rounded-full', 'bg-emerald-500' => $stage === 'ACTIVE', 'bg-blue-500' => $stage !== 'ACTIVE'])
                                    style="width: {{ $count > 0 ? max(4, round($count / $pipelineTop * 100)) : 0 }}%"></span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
            <p class="mt-2 text-[11px] text-ink-400">Lead stage is your pipeline. BRIX status (install state) is shown separately per lead.</p>
        </div>
    </section>

    <div class="mt-5 flex flex-wrap gap-x-4 gap-y-1 overflow-x-auto border-b border-ink-200/70 scrollbar-none">
        @foreach ($viewTabs as $key => $label)
            <a
                href="{{ $url(['view' => $key === 'all' ? null : $key, 'page' => null]) }}"
                class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium {{ $activeView === $key ? 'border-brix-600 text-brix-700' : 'border-transparent text-ink-400 hover:text-ink-700' }}"
            >
                {{ $label }} <span class="text-xs text-ink-400">({{ $viewCounts[$key] ?? 0 }})</span>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('leads.index') }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        @foreach (array_merge($periodQuery, array_intersect_key($filters, array_flip(['view', 'source', 'reached']))) as $name => $value)
            @if (filled($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
        @endforeach
        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search store or domain"
            class="rounded-lg border-ink-200 text-sm focus:border-brix-500 focus:ring-brix-500 lg:col-span-2" />
        <select name="stage" class="rounded-lg border-ink-200 text-sm focus:border-brix-500 focus:ring-brix-500">
            <option value="">All stages</option>
            @foreach ($stages as $stage)
                <option value="{{ $stage }}" @selected(($filters['stage'] ?? '') === $stage)>{{ ucwords(strtolower(str_replace('_', ' ', $stage))) }}</option>
            @endforeach
        </select>
        <select name="link" class="rounded-lg border-ink-200 text-sm focus:border-brix-500 focus:ring-brix-500">
            <option value="">All links</option>
            @foreach ($links as $link)
                <option value="{{ $link->id }}" @selected((string) ($filters['link'] ?? '') === (string) $link->id)>{{ $link->name }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <select name="channel" class="min-w-0 flex-1 rounded-lg border-ink-200 text-sm focus:border-brix-500 focus:ring-brix-500">
                <option value="">All channels</option>
                @foreach ($channels as $channel)
                    <option value="{{ $channel }}" @selected(($filters['channel'] ?? '') === $channel)>{{ $channel }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-ink-900 px-4 py-2 text-sm font-medium text-white hover:bg-ink-800">Filter</button>
        </div>
    </form>

    @if ($leads->isEmpty())
        <div class="mt-8 rounded-2xl border border-dashed border-ink-200 py-16 text-center">
            <p class="text-sm font-medium text-ink-700">{{ $totalLeads === 0 ? 'No leads yet' : 'No leads match these filters' }}</p>
            <p class="mt-1 text-sm text-ink-500">
                {{ $totalLeads === 0 ? 'Leads appear here once a merchant clicks one of your referral links and enters their store.' : 'Try clearing a filter.' }}
            </p>
        </div>
    @else
        <div class="mt-6 overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                            <th class="px-5 py-3">Store</th>
                            <th class="px-5 py-3">Referral Source</th>
                            <th class="px-5 py-3">Stage</th>
                            <th class="px-5 py-3">Install Status</th>
                            <th class="px-5 py-3 text-right">Revenue</th>
                            <th class="px-5 py-3 text-right">Commission</th>
                            <th class="px-5 py-3">First Clicked</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($leads as $lead)
                            <tr class="hover:bg-ink-50">
                                <td class="px-5 py-3.5 font-medium text-ink-900">
                                    {{ $lead->store?->store_name ?? $lead->shop_domain ?? $lead->company_name ?? 'Awaiting store' }}
                                    @if ($lead->company_name && ($lead->store || $lead->shop_domain))
                                        <p class="text-xs font-normal text-ink-400">{{ $lead->company_name }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-ink-600">
                                    @if ($lead->trackingLink)
                                        {{ $lead->trackingLink->name }}
                                        <span class="text-ink-400">· {{ $lead->trackingLink->channel }}</span>
                                    @elseif ($lead->source === \App\Models\Referral\Lead::SOURCE_MANUAL)
                                        Manual
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5"><x-lead-stage-badge :stage="$lead->lead_stage" /></td>
                                <td class="whitespace-nowrap px-5 py-3.5"><x-lead-install-badge :lead="$lead" /></td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-right text-ink-600">{{ Money::money($revenue[$lead->id] ?? null) }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-right text-ink-600">{{ Money::money($commission[$lead->id] ?? null) }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-ink-500">{{ $lead->first_clicked_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                    <a href="{{ route('leads.show', $lead) }}" class="text-sm font-medium text-brix-600 hover:text-brix-700">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($leads->hasPages())
                <div class="border-t border-ink-100 px-5 py-4">{{ $leads->links() }}</div>
            @endif
        </div>
    @endif

    <x-modal name="add-lead" :open-on-load="$errors->any() && old('company_name') !== null" max-width="lg">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-base font-semibold text-ink-900">Add Lead</h2>
                <p class="mt-1 text-sm text-ink-500">A prospect you're working manually, outside a referral link.</p>
            </div>
            <button type="button" x-on:click="show = false" class="text-ink-400 hover:text-ink-700">
                <x-lucide-x class="h-4 w-4" />
            </button>
        </div>

        <form method="POST" action="{{ route('leads.store') }}" class="mt-5 space-y-4">
            @csrf

            <div>
                <label class="mb-1.5 block text-sm font-medium text-ink-700">Company Name</label>
                <input type="text" name="company_name" value="{{ old('company_name') }}" required
                    class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                @error('company_name')<p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-ink-700">Contact Name</label>
                <input type="text" name="contact_name" value="{{ old('contact_name') }}" required
                    class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                @error('contact_name')<p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-ink-700">Contact Email</label>
                <input type="email" name="contact_email" value="{{ old('contact_email') }}" required
                    class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                @error('contact_email')<p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-ink-700">Contact Phone <span class="font-normal text-ink-400">(optional)</span></label>
                <input type="text" name="contact_phone" value="{{ old('contact_phone') }}"
                    class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-ink-700">Website / Store URL</label>
                <input type="text" name="website" value="{{ old('website') }}" placeholder="https://example.com or yourstore.myshopify.com" required
                    class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                <p class="mt-1.5 text-xs text-ink-400">We'll check if this store already has BRIX installed. If not, we'll generate a referral link for you to send them.</p>
                @error('website')<p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-start gap-2.5 rounded-lg border border-ink-200 bg-ink-50/50 px-3 py-2.5">
                <input type="checkbox" name="authorize" value="1" @checked(old('authorize')) class="mt-0.5 rounded border-ink-300 text-brix-600 focus:ring-brix-200">
                <span class="text-sm text-ink-700">Authorize this store now
                    <span class="block text-xs text-ink-400">Adds it to your Stores and authorizes it, if BRIX is already installed on it.</span>
                </span>
            </label>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-ink-700">Notes <span class="font-normal text-ink-400">(optional)</span></label>
                <textarea name="notes" rows="3" class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">{{ old('notes') }}</textarea>
            </div>

            <div class="mt-6 flex justify-end gap-2.5">
                <button type="button" x-on:click="show = false" class="rounded-lg border border-ink-200 px-3.5 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50">Cancel</button>
                <button type="submit" class="rounded-lg bg-brix-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-brix-700">Add Lead</button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
