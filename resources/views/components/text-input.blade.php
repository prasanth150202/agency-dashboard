@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full rounded-lg border border-ink-200 px-3 py-2 text-sm text-ink-900 placeholder-ink-400 outline-none transition focus:border-brix-400 focus:ring-2 focus:ring-brix-100 disabled:bg-ink-50 disabled:text-ink-400']) }}>
