<x-admin-layout title="{{ $partner->name }}">
    <div class="mb-4">
        <a href="{{ route('admin.partners.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink-700">&larr; All partners</a>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-ink-900">{{ $partner->name }}</h2>
                        <p class="text-sm text-ink-500">{{ $partner->owner_name }} · {{ $partner->owner_email }}</p>
                    </div>
                    <x-status-badge :status="$partner->status === 'active' ? 'active' : ($partner->status === 'suspended' ? 'offline' : 'attention')" :label="ucfirst($partner->status)" />
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div><dt class="text-ink-400">Stores</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $partner->stores->count() }}</dd></div>
                    <div><dt class="text-ink-400">Commission Rate</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $partner->commission_rate }}%</dd></div>
                    <div><dt class="text-ink-400">Ledger Balance</dt><dd class="mt-0.5 font-medium text-ink-900">{{ \App\Support\Currency::format($ledgerBalance) }}</dd></div>
                    <div><dt class="text-ink-400">Country</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $partner->country }}</dd></div>
                </dl>
            </div>

            <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <h3 class="text-sm font-semibold text-ink-900">Stores</h3>
                <div class="mt-3 divide-y divide-ink-100">
                    @forelse ($partner->stores as $store)
                        <div class="flex items-center justify-between py-2.5 text-sm">
                            <div>
                                <p class="font-medium text-ink-900">{{ $store->name }}</p>
                                <p class="text-xs text-ink-400">{{ $store->shop_domain }}</p>
                            </div>
                            <x-status-badge :status="$store->status === 'active' ? 'active' : ($store->status === 'offline' ? 'offline' : 'attention')" :label="ucfirst($store->status)" />
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-ink-400">No stores yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <h3 class="text-sm font-semibold text-ink-900">Recent Payouts</h3>
                <div class="mt-3 divide-y divide-ink-100">
                    @forelse ($payouts as $payout)
                        <div class="flex items-center justify-between py-2.5 text-sm">
                            <div>
                                <p class="font-medium text-ink-900">{{ $payout->payout_code }}</p>
                                <p class="text-xs text-ink-400">{{ $payout->requested_at?->format('M j, Y') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-medium text-ink-900">{{ \App\Support\Currency::format((float) $payout->amount, $payout->currency) }}</p>
                                <p class="text-xs text-ink-400">{{ $payout->status_label }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-ink-400">No payouts yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                <h3 class="text-sm font-semibold text-ink-900">Activity</h3>
                <div class="mt-3 space-y-3">
                    @forelse ($activity as $log)
                        <div class="flex items-start gap-3 text-sm">
                            <div class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-ink-300"></div>
                            <div>
                                <p class="text-ink-700">{{ Str::headline($log->action) }}</p>
                                <p class="text-xs text-ink-400">{{ $log->created_at?->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-ink-400">No activity recorded.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div>
            @auth('admin')
                @if (auth('admin')->user()->isFinance())
                    <form method="POST" action="{{ route('admin.partners.update', $partner) }}" class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
                        @csrf
                        @method('PUT')

                        <h3 class="text-sm font-semibold text-ink-900">Edit Partner</h3>

                        <div class="mt-4">
                            <x-input-label for="commission_rate" value="Commission Rate (%)" />
                            <x-text-input id="commission_rate" type="number" step="0.01" min="0" max="100" name="commission_rate" :value="old('commission_rate', $partner->commission_rate)" required />
                            <x-input-error :messages="$errors->get('commission_rate')" class="mt-1.5" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status" class="mt-1 w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm outline-none focus:border-brix-400 focus:ring-2 focus:ring-brix-100">
                                @foreach (['active', 'trial', 'suspended'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', $partner->status) === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-1.5" />
                        </div>

                        <button type="submit" class="mt-5 w-full rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brix-700">
                            Save Changes
                        </button>
                    </form>
                @endif
            @endauth
        </div>
    </div>
</x-admin-layout>
