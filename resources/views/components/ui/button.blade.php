@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'type' => 'submit',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-xl font-semibold '
        .'transition-colors duration-200 select-none cursor-pointer '
        .'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring-brand '
        .'disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:cursor-not-allowed aria-disabled:opacity-50';

    // Tinggi minimum 44px: standar target sentuh di ponsel.
    $sizes = [
        'sm' => 'min-h-11 px-3.5 text-sm',
        'md' => 'min-h-11 px-4 text-[0.9375rem]',
        'lg' => 'min-h-12 px-6 text-base w-full sm:w-auto',
    ];

    $variants = [
        'primary' => 'bg-brand text-brand-ink hover:brightness-110 active:brightness-95 shadow-sm',
        'secondary' => 'bg-card text-ink border border-line-strong hover:bg-surface',
        'ghost' => 'text-ink-soft hover:bg-surface hover:text-ink',
        'danger' => 'bg-keluar text-white hover:brightness-110 active:brightness-95',
        'danger-soft' => 'bg-keluar-soft text-keluar-soft-ink hover:brightness-95',
    ];

    $classes = implode(' ', [$base, $sizes[$size] ?? $sizes['md'], $variants[$variant] ?? $variants['primary']]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-[1.15em] shrink-0" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-[1.15em] shrink-0" />@endif
        {{ $slot }}
    </button>
@endif
