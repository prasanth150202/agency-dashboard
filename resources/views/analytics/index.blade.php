@push('head')
    @vite(['resources/js/charts.js'])
@endpush

<x-app-layout title="Analytics">
    <div>
        <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Analytics</h2>
        <p class="mt-1 text-sm text-ink-500">Growth, revenue, and module adoption across {{ $organisation->name }}.</p>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
            <h3 class="text-sm font-semibold text-ink-900">Store growth</h3>
            <p class="text-xs text-ink-500">Cumulative connected stores, last 6 months</p>
            <div class="mt-4 h-64"><canvas id="storeGrowthChart"></canvas></div>
        </div>

        <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
            <h3 class="text-sm font-semibold text-ink-900">Revenue</h3>
            <p class="text-xs text-ink-500">Paid revenue by month</p>
            <div class="mt-4 h-64"><canvas id="revenueChart"></canvas></div>
        </div>

        <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
            <h3 class="text-sm font-semibold text-ink-900">Active stores</h3>
            <p class="text-xs text-ink-500">Current store health breakdown</p>
            <div class="mt-4 flex h-64 items-center justify-center"><canvas id="activeStoresChart"></canvas></div>
        </div>

        <div class="rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
            <h3 class="text-sm font-semibold text-ink-900">Module adoption</h3>
            <p class="text-xs text-ink-500">Active installs per module</p>
            <div class="mt-4 h-64"><canvas id="moduleAdoptionChart"></canvas></div>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
        <div class="border-b border-ink-100 px-5 py-4">
            <h3 class="text-sm font-semibold text-ink-900">Store activity</h3>
        </div>
        <ul class="divide-y divide-ink-100">
            @forelse ($storeActivity as $activity)
                <li class="flex items-center justify-between px-5 py-3.5">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-ink-900">{{ $activity->title }}</p>
                        <p class="truncate text-xs text-ink-500">{{ $activity->message }}</p>
                    </div>
                    <span class="shrink-0 pl-4 text-xs text-ink-400">{{ $activity->created_at->diffForHumans() }}</span>
                </li>
            @empty
                <li class="px-5 py-10 text-center text-sm text-ink-400">No activity yet.</li>
            @endforelse
        </ul>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const inkGrid = '#eeeef0';
                const brix = '#6842ea';

                new window.Chart(document.getElementById('storeGrowthChart'), {
                    type: 'line',
                    data: {
                        labels: @json($chartLabels),
                        datasets: [{
                            label: 'Stores',
                            data: @json($storeGrowth),
                            borderColor: brix,
                            backgroundColor: 'rgba(104, 66, 234, 0.08)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 0,
                            borderWidth: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false } },
                            y: { grid: { color: inkGrid }, beginAtZero: true, ticks: { precision: 0 } },
                        },
                    },
                });

                new window.Chart(document.getElementById('revenueChart'), {
                    type: 'bar',
                    data: {
                        labels: @json($chartLabels),
                        datasets: [{
                            label: 'Revenue (₹)',
                            data: @json($revenueByMonth),
                            backgroundColor: brix,
                            borderRadius: 6,
                            maxBarThickness: 28,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false } },
                            y: { grid: { color: inkGrid }, beginAtZero: true },
                        },
                    },
                });

                new window.Chart(document.getElementById('activeStoresChart'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Active', 'Attention', 'Offline'],
                        datasets: [{
                            data: [{{ $storeHealth['active'] }}, {{ $storeHealth['attention'] }}, {{ $storeHealth['offline'] }}],
                            backgroundColor: ['#10b981', '#f59e0b', '#f43f5e'],
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%',
                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, usePointStyle: true, pointStyle: 'circle' } } },
                    },
                });

                new window.Chart(document.getElementById('moduleAdoptionChart'), {
                    type: 'bar',
                    data: {
                        labels: @json($moduleAdoption->pluck('label')),
                        datasets: [{
                            label: 'Stores',
                            data: @json($moduleAdoption->pluck('count')),
                            backgroundColor: '#9a8cfa',
                            borderRadius: 6,
                            maxBarThickness: 22,
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { color: inkGrid }, beginAtZero: true, ticks: { precision: 0 } },
                            y: { grid: { display: false } },
                        },
                    },
                });
            });
        </script>
    @endpush
</x-app-layout>
