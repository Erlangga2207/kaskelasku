@props(['tipe' => 'netral', 'ikon' => null])

@php
    $gaya = [
        'netral' => 'bg-surface text-ink-soft border-line-strong',
        'lunas' => 'bg-masuk-soft text-masuk-soft-ink border-transparent',
        'kurang' => 'bg-tunggak-soft text-tunggak-soft-ink border-transparent',
        'belum' => 'bg-keluar-soft text-keluar-soft-ink border-transparent',
        'info' => 'bg-brand-soft text-brand-soft-ink border-transparent',
    ][$tipe] ?? 'bg-surface text-ink-soft border-line-strong';
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold '.$gaya,
]) }}>
    @if ($ikon)<x-icon :name="$ikon" class="size-3.5" />@endif
    {{ $slot }}
</span>
