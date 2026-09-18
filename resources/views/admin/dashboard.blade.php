<x-admin-layout title="Dashboard">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-metric-card label="Partners" :value="$metrics['partners']" icon="users" caption="{{ $metrics['active_partners'] }} active" />
        <x-metric-card label="Stores" :value="$metrics['stores']" icon="store" caption="{{ $metrics['installed_stores'] }} installed" />
        <x-metric-card label="Pending Payouts" :value="$metrics['pending_payouts_count']" icon="wallet" caption="{{ \App\Support\Currency::format($metrics['pending_payouts_amount'], 'INR') }} total" />
        <x-metric-card label="Paid This Month" :value="\App\Support\Currency::format($metrics['paid_this_month'], 'INR')" icon="indian-rupee" caption="across all partners" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-ink-900">Payout Queue</h2>
                <a href="{{ route('admin.payouts.index') }}" class="text-xs font-medium text-brix-600 hover:text-brix-700">View all</a>
            </div>

            <div class="mt-4 space-y-3">
                @forelse ($payoutQueue as $payout)
                    <a href="{{ route('admin.payouts.show', $payout) }}" class="flex items-center justify-between rounded-xl border border-ink-100 px-3 py-2.5 hover:border-ink-200 hover:bg-ink-50">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-ink-900">{{ $payout->partner->name ?? '—' }}</p>
                            <p class="text-xs text-ink-400">{{ $payout->payout_code }} · {{ $payout->requested_at?->format('M j, Y') }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="text-sm font-medium text-ink-900">{{ \App\Support\Currency::format((float) $payout->amount, $payout->currency) }}</span>
                            <x-status-badge :status="$payout->status === 'pending' ? 'attention' : 'in_payout'" :label="$payout->status_label" />
                        </div>
                    </a>
                @empty
                    <p class="py-6 text-center text-sm text-ink-400">No payouts waiting on review.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
            <h2 class="text-sm font-semibold text-ink-900">Recent Activity</h2>

            <div class="mt-4 space-y-3">
                @forelse ($recentActivity as $log)
                    <div class="flex items-start gap-3 text-sm">
                        <div class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-ink-300"></div>
                        <div class="min-w-0">
                            <p class="text-ink-700">{{ Str::headline($log->action) }}</p>
                            <p class="text-xs text-ink-400">{{ $log->created_at?->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-ink-400">No activity yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink-900">Recently Added Partners</h2>
            <a href="{{ route('admin.partners.index') }}" class="text-xs font-medium text-brix-600 hover:text-brix-700">View all</a>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-100 text-left text-xs font-medium uppercase tracking-wide text-ink-400">
                        <th class="pb-2">Name</th>
                        <th class="pb-2">Status</th>
                        <th class="pb-2">Commission Rate</th>
                        <th class="pb-2">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($recentPartners as $partner)
                        <tr class="hover:bg-ink-50">
                            <td class="py-2.5"><a href="{{ route('admin.partners.show', $partner) }}" class="font-medium text-ink-900 hover:text-brix-600">{{ $partner->name }}</a></td>
                            <td class="py-2.5"><x-status-badge :status="$partner->status === 'active' ? 'active' : ($partner->status === 'suspended' ? 'offline' : 'attention')" :label="ucfirst($partner->status)" /></td>
                            <td class="py-2.5 text-ink-600">{{ $partner->commission_rate }}%</td>
                            <td class="py-2.5 text-ink-400">{{ $partner->created_at?->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-ink-400">No partners yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
