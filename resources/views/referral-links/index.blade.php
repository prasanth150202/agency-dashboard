@php use App\Support\Currency; @endphp
<x-app-layout title="Referral Links">
    <div x-data="{ createOpen: false, copyLink(url) { navigator.clipboard?.writeText(url); } }">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Referral Links</h2>
                <p class="mt-1 text-sm text-ink-500">
                    Track every merchant referral from first click through installation, activation, and commission.
                </p>
            </div>

            <button
                type="button"
                x-on:click="$dispatch('open-modal', 'create-referral-link')"
                class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700"
            >
                <x-lucide-plus class="h-4 w-4" />
                Create Link
            </button>
        </div>

        @if (session('success'))
            <div class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
        @elseif (session('error'))
            <div class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ session('error') }}</div>
        @endif

        @if (session('createdLink'))
            @php $createdLink = session('createdLink'); @endphp
            <div class="mt-4 flex flex-col gap-3 rounded-xl border border-brix-200 bg-brix-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 text-sm">
                    <p class="font-medium text-ink-900">"{{ $createdLink['name'] }}" is ready — share this link:</p>
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

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-metric-card label="Referral Links" :value="$metrics['total_links']" icon="link-2" />
            <x-metric-card label="Active Links" :value="$metrics['active_links']" icon="circle-check-big" />
            <x-metric-card label="Total Clicks" :value="$metrics['total_clicks']" icon="mouse-pointer-click" />
            <x-metric-card label="Total Leads" :value="$metrics['total_leads']" icon="users" />
        </div>

        <p class="mt-3 text-xs text-ink-500">
            Revenue and commission reflect verified BRIX usage charges only, in {{ $revenueCurrency }}.
            @if ($subscriptionNotVerifiable)
                Recurring subscription revenue is not yet verifiable and is not included.
            @endif
        </p>

        <form method="GET" action="{{ route('referral-links.index') }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                <input
                    type="search"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Search by name, campaign, or code..."
                    x-data
                    x-on:input.debounce.500ms="$el.form.submit()"
                    class="w-full rounded-lg border border-ink-200 bg-white py-2.5 pl-9 pr-3 text-sm text-ink-900 placeholder-ink-400 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                >
            </div>

            <select
                name="channel"
                x-data
                x-on:change="$el.form.submit()"
                class="rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm font-medium text-ink-700 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
            >
                <option value="">All channels</option>
                @foreach ($channels as $channel)
                    <option value="{{ $channel }}" @selected(($filters['channel'] ?? '') === $channel)>{{ $channel }}</option>
                @endforeach
            </select>

            <select
                name="status"
                x-data
                x-on:change="$el.form.submit()"
                class="rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm font-medium text-ink-700 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
            >
                <option value="">All statuses</option>
                <option value="ACTIVE" @selected(($filters['status'] ?? '') === 'ACTIVE')>Active</option>
                <option value="INACTIVE" @selected(($filters['status'] ?? '') === 'INACTIVE')>Inactive</option>
            </select>
        </form>

        @if ($links->isEmpty())
            <div class="mt-8 rounded-2xl border border-dashed border-ink-200 py-16 text-center">
                <p class="text-sm font-medium text-ink-700">No referral links yet</p>
                <p class="mt-1 text-sm text-ink-500">Create your first link to start tracking merchant referrals.</p>
            </div>
        @else
            <div class="mt-6 overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                                <th class="px-5 py-3">Link</th>
                                <th class="px-5 py-3">Channel</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Clicks</th>
                                <th class="px-5 py-3 text-right">Leads</th>
                                <th class="px-5 py-3 text-right">Active Stores</th>
                                <th class="px-5 py-3 text-right">Revenue</th>
                                <th class="px-5 py-3 text-right">Commission</th>
                                <th class="px-5 py-3">Last Activity</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($links as $link)
                                <tr class="hover:bg-ink-50" x-data="{ actionsOpen: false }">
                                    <td class="px-5 py-3.5">
                                        <p class="font-medium text-ink-900">{{ $link->name }}</p>
                                        <p class="mt-0.5 text-xs text-ink-500">{{ $link->campaign_name ?? '—' }}</p>
                                        <p class="mt-0.5 font-mono text-xs text-ink-400">{{ $link->code }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-ink-600">{{ $link->channel }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        <x-status-badge
                                            :status="$link->status === 'ACTIVE' ? 'active' : 'inactive'"
                                            :label="ucfirst(strtolower($link->status))"
                                        />
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right font-medium text-ink-900">{{ $link->clicks_count }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right font-medium text-ink-900">{{ $link->leads_count }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right font-medium text-ink-900">{{ $link->active_stores_count }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right text-ink-600">{{ Currency::format($link->revenue, $revenueCurrency) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right text-ink-600">{{ Currency::format($link->commission, $revenueCurrency) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-ink-500">{{ $link->last_activity_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                        <div class="relative inline-block text-left">
                                            <button type="button" x-on:click="actionsOpen = !actionsOpen" x-on:click.outside="actionsOpen = false" class="rounded-lg p-1.5 text-ink-400 hover:bg-ink-100 hover:text-ink-700">
                                                <x-lucide-ellipsis-vertical class="h-4 w-4" />
                                            </button>
                                            <div
                                                x-show="actionsOpen"
                                                x-cloak
                                                x-transition
                                                class="absolute right-0 z-10 mt-1 w-44 rounded-lg border border-ink-200/70 bg-white py-1 text-sm shadow-panel"
                                            >
                                                <button type="button" x-on:click="copyLink('{{ $link->referral_url }}'); actionsOpen = false" class="flex w-full items-center gap-2 px-3 py-2 text-left text-ink-700 hover:bg-ink-50">
                                                    <x-lucide-copy class="h-3.5 w-3.5" />
                                                    Copy Link
                                                </button>
                                                <a href="{{ route('referral-links.leads', $link) }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-ink-700 hover:bg-ink-50">
                                                    <x-lucide-users class="h-3.5 w-3.5" />
                                                    View Leads
                                                </a>
                                                <button type="button" x-on:click="actionsOpen = false; $dispatch('open-modal', 'edit-link-{{ $link->id }}')" class="flex w-full items-center gap-2 px-3 py-2 text-left text-ink-700 hover:bg-ink-50">
                                                    <x-lucide-pencil class="h-3.5 w-3.5" />
                                                    Edit
                                                </button>
                                                @if ($link->status === 'ACTIVE')
                                                    <form method="POST" action="{{ route('referral-links.deactivate', $link) }}">
                                                        @csrf
                                                        <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-rose-600 hover:bg-rose-50">
                                                            <x-lucide-pause class="h-3.5 w-3.5" />
                                                            Deactivate
                                                        </button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('referral-links.activate', $link) }}">
                                                        @csrf
                                                        <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-emerald-700 hover:bg-emerald-50">
                                                            <x-lucide-play class="h-3.5 w-3.5" />
                                                            Activate
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($links->hasPages())
                    <div class="border-t border-ink-100 px-5 py-4">
                        {{ $links->links() }}
                    </div>
                @endif
            </div>

            @foreach ($links as $link)
                <x-modal :name="'edit-link-'.$link->id" max-width="lg">
                    <div class="flex items-start justify-between">
                        <h2 class="text-base font-semibold text-ink-900">Edit "{{ $link->name }}"</h2>
                        <button type="button" x-on:click="show = false" class="text-ink-400 hover:text-ink-700">
                            <x-lucide-x class="h-4 w-4" />
                        </button>
                    </div>

                    <form method="POST" action="{{ route('referral-links.update', $link) }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-ink-700">Link Name</label>
                            <input type="text" name="name" value="{{ $link->name }}" required class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-ink-700">
                                Campaign <span class="font-normal text-ink-400">(optional)</span>
                            </label>
                            <input type="text" name="campaign_name" value="{{ $link->campaign_name }}" placeholder="e.g. September Push, Diwali Sale" class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                            <p class="mt-1.5 text-xs text-ink-400">
                                A label for grouping links from the same channel by campaign or time period — doesn't affect tracking.
                            </p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-ink-700">Channel</label>
                            <select name="channel" class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                                @foreach ($channels as $channel)
                                    <option value="{{ $channel }}" @selected($link->channel === $channel)>{{ $channel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-ink-700">Notes</label>
                            <textarea name="notes" rows="2" class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">{{ $link->notes }}</textarea>
                        </div>

                        <div class="mt-6 flex justify-end gap-2.5">
                            <button type="button" x-on:click="show = false" class="rounded-lg border border-ink-200 px-3.5 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50">Cancel</button>
                            <button type="submit" class="rounded-lg bg-brix-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-brix-700">Save Changes</button>
                        </div>
                    </form>
                </x-modal>
            @endforeach
        @endif

        <x-modal name="create-referral-link" :open-on-load="$errors->any() && session()->hasOldInput('name')" max-width="lg">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-base font-semibold text-ink-900">Create Referral Link</h2>
                    <p class="mt-1 text-sm text-ink-500">Generate a unique, trackable link for a channel or campaign.</p>
                </div>
                <button type="button" x-on:click="show = false" class="text-ink-400 hover:text-ink-700">
                    <x-lucide-x class="h-4 w-4" />
                </button>
            </div>

            <form method="POST" action="{{ route('referral-links.store') }}" class="mt-5 space-y-4">
                @csrf

                <div>
                    <label for="link-name" class="mb-1.5 block text-sm font-medium text-ink-700">Link Name</label>
                    <input
                        id="link-name"
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        placeholder="e.g. Instagram Bio Link"
                        class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 placeholder-ink-400 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                    >
                    @error('name')
                        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="link-campaign" class="mb-1.5 block text-sm font-medium text-ink-700">
                        Campaign <span class="font-normal text-ink-400">(optional)</span>
                    </label>
                    <input
                        id="link-campaign"
                        type="text"
                        name="campaign_name"
                        value="{{ old('campaign_name') }}"
                        placeholder="e.g. September Push, Diwali Sale"
                        class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 placeholder-ink-400 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                    >
                    <p class="mt-1.5 text-xs text-ink-400">
                        A label for grouping links from the same channel by campaign or time period — doesn't affect tracking.
                    </p>
                    @error('campaign_name')
                        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="link-channel" class="mb-1.5 block text-sm font-medium text-ink-700">Channel</label>
                    <select
                        id="link-channel"
                        name="channel"
                        class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-900 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                    >
                        @foreach ($channels as $channel)
                            <option value="{{ $channel }}" @selected(old('channel') === $channel)>{{ $channel }}</option>
                        @endforeach
                    </select>
                    @error('channel')
                        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="link-notes" class="mb-1.5 block text-sm font-medium text-ink-700">Notes <span class="font-normal text-ink-400">(optional)</span></label>
                    <textarea
                        id="link-notes"
                        name="notes"
                        rows="2"
                        placeholder="Internal notes about this link..."
                        class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 placeholder-ink-400 outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100"
                    >{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <p class="text-xs text-ink-400">
                    BRIX generates a unique referral code and destination automatically — you'll get a copyable link right after creating this.
                </p>

                <div class="mt-6 flex justify-end gap-2.5">
                    <button type="button" x-on:click="show = false" class="rounded-lg border border-ink-200 px-3.5 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-lg bg-brix-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-brix-700">
                        Create Link
                    </button>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
