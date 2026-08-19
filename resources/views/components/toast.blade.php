@php
    $flashes = collect(['success' => 'success', 'error' => 'error'])
        ->map(fn ($type, $key) => session($key) ? ['type' => $type, 'message' => session($key)] : null)
        ->filter()
        ->values();
@endphp

@if ($flashes->isNotEmpty())
    <div class="fixed inset-x-0 bottom-6 z-[60] flex flex-col items-center gap-2 px-4">
        @foreach ($flashes as $flash)
            <div
                x-data="toastNotice(@js($flash['message']), @js($flash['type']))"
                x-show="show"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="flex items-center gap-2.5 rounded-xl border px-4 py-3 text-sm font-medium shadow-panel {{ $flash['type'] === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}"
            >
                @if ($flash['type'] === 'error')
                    <x-lucide-circle class="h-4 w-4 shrink-0" />
                @else
                    <x-lucide-circle-check class="h-4 w-4 shrink-0" />
                @endif
                <span x-text="message"></span>
                <button type="button" x-on:click="show = false" class="ml-1 text-current/60 hover:text-current">
                    <x-lucide-x class="h-3.5 w-3.5" />
                </button>
            </div>
        @endforeach
    </div>
@endif
