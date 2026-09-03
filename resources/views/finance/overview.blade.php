<x-app-layout title="Finance Overview">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Finance Overview</h2>
            <p class="mt-1 text-sm text-ink-500">A snapshot of {{ $organisation->name }}'s earnings and payouts.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('earnings') }}" class="rounded-lg border border-ink-200 px-3.5 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50">
                View Earnings
            </a>
            <a href="{{ route('payouts') }}" class="rounded-lg bg-brix-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-brix-700">
                View Payouts
            </a>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-metric-card label="Total Earnings" :value="'₹' . number_format($metrics['total_earnings'], 2)" icon="trending-up" />
        <x-metric-card label="This Month" :value="'₹' . number_format($metrics['this_month'], 2)" icon="calendar-days" />
        <x-metric-card label="Pending Commission" :value="'₹' . number_format($metrics['pending_commission'], 2)" icon="clock" />
        <x-metric-card label="Available Balance" :value="'₹' . number_format($metrics['available_balance'], 2)" icon="wallet" prominent />
        <x-metric-card label="Processing Payouts" :value="'₹' . number_format($metrics['processing_payouts'], 2)" icon="loader" />
        <x-metric-card label="Paid Out" :value="'₹' . number_format($metrics['paid_out'], 2)" icon="circle-check-big" />
    </div>
</x-app-layout>
