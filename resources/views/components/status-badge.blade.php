@props(['status', 'label' => null])

@php
    $config = match ($status) {
        'active' => ['dot' => 'bg-emerald-500', 'text' => 'text-emerald-700', 'bg' => 'bg-emerald-50'],
        'attention' => ['dot' => 'bg-amber-500', 'text' => 'text-amber-700', 'bg' => 'bg-amber-50'],
        'offline' => ['dot' => 'bg-rose-500', 'text' => 'text-rose-700', 'bg' => 'bg-rose-50'],
        'inactive' => ['dot' => 'bg-ink-300', 'text' => 'text-ink-500', 'bg' => 'bg-ink-100'],
        // Commission-ledger-specific extras — additive, doesn't affect
        // any existing caller (Stores/Payouts only ever pass the four above).
        'eligible' => ['dot' => 'bg-violet-500', 'text' => 'text-violet-700', 'bg' => 'bg-violet-50'],
        'paid' => ['dot' => 'bg-blue-500', 'text' => 'text-blue-700', 'bg' => 'bg-blue-50'],
        'in_payout' => ['dot' => 'bg-cyan-500', 'text' => 'text-cyan-700', 'bg' => 'bg-cyan-50'],
        default => ['dot' => 'bg-ink-300', 'text' => 'text-ink-500', 'bg' => 'bg-ink-100'],
    };

    $label = $label ?? ucfirst($status);
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full {$config['bg']} px-2.5 py-1 text-xs font-medium {$config['text']}"]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ $config['dot'] }}"></span>
    {{ $label }}
</span>
