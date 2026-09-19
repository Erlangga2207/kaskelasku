<x-layouts.guest judul="Kelas yang dihapus">
    <x-ui.card>
        <div class="space-y-5">
            <div>
                <h1 class="text-xl font-bold">Kelas yang dijadwalkan dihapus</h1>
                <p class="mt-1 text-sm text-ink-faint">
                    Masih bisa dipulihkan selama {{ $tenggang }} hari sejak dihapus. Setelah itu
                    datanya hilang permanen dan tidak bisa dikembalikan.
                </p>
            </div>

            <x-ui.flash />

            @if ($daftar->isEmpty())
                <x-ui.empty ikon="cek" judul="Tidak ada kelas yang sedang dihapus">
                    Semua kelasmu aman.
                </x-ui.empty>
                <x-ui.button :href="route('dashboard')" variant="secondary" size="lg">Kembali ke beranda</x-ui.button>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($daftar as $kelas)
                        @php
                            $sisa = $kelas->dihapus_pada
                                ? max(0, $tenggang - (int) $kelas->dihapus_pada->diffInDays(now()))
                                : $tenggang;
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-3 py-4">
                            <div class="min-w-0">
                                <p class="font-semibold text-ink">{{ $kelas->nama_kelas }}</p>
                                <p class="mt-0.5 text-sm text-ink-faint">{{ $kelas->sekolah }}</p>
                                <p class="mt-1 text-sm {{ $sisa <= 3 ? 'font-semibold text-keluar' : 'text-ink-soft' }}">
                                    Hilang permanen dalam {{ $sisa }} hari lagi
                                </p>
                            </div>

                            @if ($kelas->owner_id === auth()->id())
                                <form method="POST" action="{{ route('kelas.restore', $kelas->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <x-ui.button type="submit" variant="secondary" size="sm" icon="putar">
                                        Pulihkan
                                    </x-ui.button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-ui.card>
</x-layouts.guest>
