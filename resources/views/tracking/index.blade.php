@php
    use App\Services\Referral\ReferralFunnel as Funnel;
    use App\Services\Referral\ReferralReporting as Money;

    $steps = [
        ['label' => 'Clicks', 'value' => $totals['clicks']],
        ['label' => 'Store entered', 'value' => $totals['with_store']],
        ['label' => 'Leads', 'value' => $totals['leads']],
        ['label' => 'Installed', 'value' => $totals['installed']],
        ['label' => 'Active', 'value' => $totals['active']],
        ['label' => 'Earning revenue', 'value' => $totals['earning']],
    ];
    $top = max(1, ...array_column($steps, 'value'));
@endphp
<x-app-layout title="Tracking">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Referral Tracking</h2>
            <p class="mt-1 text-sm text-ink-500">How visitors from your links become installed, active, revenue-earning stores.</p>
        </div>
        <div class="inline-flex rounded-lg border border-ink-200 bg-white p-0.5 text-sm">
            @foreach ($ranges as $r)
                <a href="{{ route('tracking.index', ['range' => $r]) }}"
                   class="rounded-md px-3 py-1.5 font-medium {{ $range === $r ? 'bg-ink-900 text-white' : 'text-ink-600 hover:text-ink-900' }}">{{ $r }}d</a>
            @endforeach
        </div>
    </div>

    @if (! $hasAnyLinks)
        <div class="mt-8 rounded-2xl border border-dashed border-ink-200 py-16 text-center">
            <p class="text-sm font-medium text-ink-700">Nothing to track yet</p>
            <p class="mt-1 text-sm text-ink-500">Create a referral link and share it — clicks and leads will appear here.</p>
            <a href="{{ route('referral-links.index') }}" class="mt-4 inline-flex rounded-lg bg-brix-600 px-4 py-2 text-sm font-medium text-white hover:bg-brix-700">Go to Referral Links</a>
        </div>
    @else
        <section class="mt-6 rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
            <h3 class="text-sm font-semibold text-ink-900">Funnel · last {{ $range }} days</h3>
            <p class="mt-0.5 text-xs text-ink-500">Clicks are counted in the period. Every later step covers leads whose first click was in the period.</p>
            <div class="mt-4 space-y-2.5">
                @foreach ($steps as $i => $step)
                    <div class="flex items-center gap-3">
                        <span class="w-32 shrink-0 text-sm text-ink-600">{{ $step['label'] }}</span>
                        <div class="h-6 flex-1 overflow-hidden rounded-md bg-ink-100">
                            <div class="h-full rounded-md bg-brix-500" style="width: {{ $step['value'] > 0 ? max(2, round($step['value'] / $top * 100)) : 0 }}%"></div>
                        </div>
                        <span class="w-12 shrink-0 text-right text-sm font-semibold text-ink-900">{{ number_format($step['value']) }}</span>
                        <span class="w-14 shrink-0 text-right text-xs text-ink-400">
                            @if ($i > 0 && ($rate = Funnel::rate($step['value'], $steps[$i - 1]['value'])) !== null){{ $rate }}%@endif
                        </span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="mt-6 rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
            <h3 class="text-sm font-semibold text-ink-900">Clicks per day</h3>
            <div class="mt-4 flex h-28 items-end gap-px">
                @foreach ($daily as $date => $count)
                    <div class="group relative flex-1" title="{{ $date }}: {{ $count }} click(s)">
                        <div class="w-full rounded-t bg-brix-400 group-hover:bg-brix-600" style="height: {{ $count > 0 ? max(4, round($count / $dailyMax * 100)) : 0 }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-[11px] text-ink-400">
                <span>{{ array_key_first($daily) }}</span><span>{{ array_key_last($daily) }}</span>
            </div>
        </section>

        <section class="mt-6 overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
            <h3 class="px-5 pt-5 text-sm font-semibold text-ink-900">By channel</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-y border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                            <th class="px-5 py-2.5">Channel</th>
                            <th class="px-5 py-2.5 text-right">Clicks</th>
                            <th class="px-5 py-2.5 text-right">Leads</th>
                            <th class="px-5 py-2.5 text-right">Installed</th>
                            <th class="px-5 py-2.5 text-right">Active</th>
                            <th class="px-5 py-2.5 text-right">Earning</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($channels as $channel => $c)
                            <tr>
                                <td class="px-5 py-3 font-medium text-ink-900">{{ $channel }}</td>
                                <td class="px-5 py-3 text-right text-ink-600">{{ number_format($c['clicks']) }}</td>
                                <td class="px-5 py-3 text-right text-ink-600">{{ number_format($c['leads']) }}</td>
                                <td class="px-5 py-3 text-right text-ink-600">{{ number_format($c['installed']) }}</td>
                                <td class="px-5 py-3 text-right text-ink-600">{{ number_format($c['active']) }}</td>
                                <td class="px-5 py-3 text-right text-ink-600">{{ number_format($c['earning']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mt-6 overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-subtle">
            <h3 class="px-5 pt-5 text-sm font-semibold text-ink-900">By link</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-y border-ink-100 text-xs font-medium uppercase tracking-wide text-ink-400">
                            <th class="px-5 py-2.5">Link</th>
                            <th class="px-5 py-2.5 text-right">Clicks</th>
                            <th class="px-5 py-2.5 text-right">QR scans</th>
                            <th class="px-5 py-2.5 text-right">Leads</th>
                            <th class="px-5 py-2.5 text-right">Installed</th>
                            <th class="px-5 py-2.5 text-right">Active</th>
                            <th class="px-5 py-2.5 text-right">Revenue (all time)</th>
                            <th class="px-5 py-2.5 text-right">Commission (all time)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-5 py-3">
                                    <a href="{{ route('referral-links.leads', $row['link']) }}" class="font-medium text-ink-900 hover:text-brix-700">{{ $row['link']->name }}</a>
                                    <span class="block text-xs text-ink-400">{{ $row['link']->channel }} · <span class="font-mono">{{ $row['link']->code }}</span></span>
                                </td>
                                <td class="px-5 py-3 text-right text-ink-600">{{ number_format($row['clicks']) }}</td>
                                <td class="px-5 py-3 text-right text-ink-600">{{ number_format($row['qr_scans']) }}</td>
                                <td class="px-5 py-3 text-right text-ink-600">{{ number_format($row['leads']) }}</td>
                                <td class="px-5 py-3 text-right text-ink-600">{{ number_format($row['installed']) }}</td>
                                <td class="px-5 py-3 text-right text-ink-600">{{ number_format($row['active']) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-ink-600">{{ Money::money($row['revenue']) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-ink-600">{{ Money::money($row['commission']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-app-layout>
