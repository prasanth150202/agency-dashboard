@php
    $gamification = new \App\Services\Referral\Gamification($currentOrganisation->brix_agency_id);
    $milestones = $gamification->milestones();
    $progress = $gamification->progress();
@endphp
<x-app-layout title="Rewards">
    <div>
        <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Rewards</h2>
        <p class="mt-1 text-sm text-ink-500">Milestones you've earned as a BRIX partner.</p>
    </div>

    <div class="mt-6 rounded-2xl border border-ink-200/70 bg-white p-5 shadow-subtle">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-ink-900">Your milestones</h3>
            <span class="text-xs font-medium text-ink-500">{{ $progress['achieved'] }} / {{ $progress['total'] }}</span>
        </div>
        <ul class="mt-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
            @foreach ($milestones as $milestone)
                <li class="flex items-center gap-2.5 rounded-xl border px-3 py-2.5 text-sm {{ $milestone['achieved'] ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-ink-200/70 text-ink-500' }}">
                    <x-dynamic-component :component="$milestone['achieved'] ? 'lucide-circle-check-big' : 'lucide-circle'" class="h-4 w-4 shrink-0" />
                    {{ $milestone['label'] }}
                </li>
            @endforeach
        </ul>
    </div>

    <div class="mt-6 rounded-2xl border border-dashed border-ink-200 py-16 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-ink-100">
            <x-lucide-gift class="h-6 w-6 text-ink-400" />
        </div>
        <p class="mt-4 text-sm font-medium text-ink-700">No rewards available yet</p>
        <p class="mt-1 text-sm text-ink-500">Rewards for reaching a milestone will appear here.</p>
    </div>
</x-app-layout>
