@props(['judul' => null, 'keterangan' => null, 'aksi' => null, 'padat' => false])

<section {{ $attributes->merge(['class' => 'rounded-card border border-line bg-card shadow-sm']) }}>
    @if ($judul || $aksi)
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-4 py-3.5 sm:px-5">
            <div class="min-w-0">
                @if ($judul)
                    <h2 class="text-base font-bold text-ink">{{ $judul }}</h2>
                @endif
                @if ($keterangan)
                    <p class="mt-0.5 text-sm text-ink-faint">{{ $keterangan }}</p>
                @endif
            </div>
            @if ($aksi)
                <div class="flex shrink-0 items-center gap-2">{{ $aksi }}</div>
            @endif
        </header>
    @endif

    <div class="{{ $padat ? '' : 'px-4 py-4 sm:px-5' }}">
        {{ $slot }}
    </div>
</section>
