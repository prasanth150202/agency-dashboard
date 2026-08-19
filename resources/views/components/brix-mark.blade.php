@props(['class' => 'h-10 w-10'])

<img
    src="{{ asset('images/brix-logo.png') }}"
    alt="BRIX"
    {{ $attributes->merge(['class' => $class . ' object-contain shrink-0']) }}
>
