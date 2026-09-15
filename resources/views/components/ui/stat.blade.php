@props(['label', 'nilai', 'ikon' => null, 'nada' => 'netral', 'keterangan' => null])

@php
    $warna = [
        'netral' => 'text-ink',
        'masuk' => 'text-masuk',
        'keluar' => 'text-keluar',
        'tunggak' => 'text-tunggak',
        'brand' => 'text-brand',
    ][$nada] ?? 'text-ink';

    $latarIkon = [
        'netral' => 'bg-surface text-ink-soft',
        'masuk' => 'bg-masuk-soft text-masuk-soft-ink',
        'keluar' => 'bg-keluar-soft text-keluar-soft-ink',
        'tunggak' => 'bg-tunggak-soft text-tunggak-soft-ink',
        'brand' => 'bg-brand-soft text-brand-soft-ink',
    ][$nada] ?? 'bg-surface text-ink-soft';
@endphp

<div {{ $attributes->merge(['class' => 'rounded-card border border-line bg-card p-4 shadow-sm']) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-ink-faint">{{ $label }}</p>
        @if ($ikon)
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ $latarIkon }}">
                <x-icon :name="$ikon" class="size-4" />
            </span>
        @endif
    </div>

    {{-- Angka uang memakai tabular-nums supaya kolom tidak bergoyang saat berubah. --}}
    <p class="mt-1.5 text-2xl font-extrabold tabular {{ $warna }}">{{ $nilai }}</p>

    @if ($keterangan)
        <p class="mt-1 text-xs text-ink-faint">{{ $keterangan }}</p>
    @endif
</div>
