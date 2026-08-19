<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-1.5 rounded-lg bg-brix-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brix-700 focus:outline-none focus:ring-2 focus:ring-brix-200 disabled:opacity-40']) }}>
    {{ $slot }}
</button>
