@props(['tipe' => 'info', 'judul' => null])

@php
    // Warna tidak pernah jadi satu-satunya penanda — selalu ada ikon + teks.
    $gaya = [
        'info' => ['kelas' => 'bg-brand-soft text-brand-soft-ink border-brand-soft', 'ikon' => 'info'],
        'sukses' => ['kelas' => 'bg-masuk-soft text-masuk-soft-ink border-masuk-soft', 'ikon' => 'cek'],
        'peringatan' => ['kelas' => 'bg-tunggak-soft text-tunggak-soft-ink border-tunggak-soft', 'ikon' => 'peringatan'],
        'galat' => ['kelas' => 'bg-keluar-soft text-keluar-soft-ink border-keluar-soft', 'ikon' => 'peringatan'],
    ][$tipe] ?? ['kelas' => 'bg-brand-soft text-brand-soft-ink border-brand-soft', 'ikon' => 'info'];
@endphp

<div role="{{ in_array($tipe, ['galat', 'peringatan']) ? 'alert' : 'status' }}"
     {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm '.$gaya['kelas']]) }}>
    <x-icon :name="$gaya['ikon']" class="mt-0.5 size-5 shrink-0" />
    <div class="min-w-0 flex-1">
        @if ($judul)
            <p class="font-semibold">{{ $judul }}</p>
        @endif
        <div class="{{ $judul ? 'mt-0.5' : '' }}">{{ $slot }}</div>
    </div>
</div>
