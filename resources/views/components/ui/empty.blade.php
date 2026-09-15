@props(['ikon' => 'info', 'judul', 'aksi' => null])

{{-- Keadaan kosong wajib menjelaskan langkah berikutnya, bukan sekadar "tidak ada data". --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-3 px-6 py-12 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-surface text-ink-faint">
        <x-icon :name="$ikon" class="size-6" />
    </span>
    <div class="space-y-1">
        <p class="font-semibold text-ink">{{ $judul }}</p>
        <p class="mx-auto max-w-sm text-sm text-ink-faint">{{ $slot }}</p>
    </div>
    @if ($aksi)
        <div class="mt-1">{{ $aksi }}</div>
    @endif
</div>
