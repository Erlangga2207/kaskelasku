@props(['aktif' => '1'])

@php
    $langkah = [
        '1' => 'Buat kelas',
        '2' => 'Daftar siswa',
        '3' => 'Periode iuran',
    ];
@endphp

{{-- Penanda langkah: bendahara harus tahu masih ada berapa lagi sebelum selesai. --}}
<ol class="flex items-center gap-2 text-xs font-semibold" aria-label="Langkah penyiapan">
    @foreach ($langkah as $nomor => $label)
        @php
            $selesai = $nomor < $aktif;
            $sekarang = $nomor === (string) $aktif;
        @endphp
        <li class="flex items-center gap-2 {{ $sekarang ? 'text-brand' : ($selesai ? 'text-masuk' : 'text-ink-faint') }}"
            @if ($sekarang) aria-current="step" @endif>
            <span class="flex size-6 shrink-0 items-center justify-center rounded-full border
                         {{ $sekarang ? 'border-brand bg-brand text-brand-ink' : ($selesai ? 'border-masuk bg-masuk-soft text-masuk-soft-ink' : 'border-line-strong') }}">
                @if ($selesai)
                    <x-icon name="cek" class="size-3.5" />
                @else
                    {{ $nomor }}
                @endif
            </span>
            <span class="hidden sm:inline">{{ $label }}</span>
        </li>
        @if (! $loop->last)
            <li aria-hidden="true" class="h-px w-4 bg-line-strong sm:w-6"></li>
        @endif
    @endforeach
</ol>
