@php use App\Support\Uang; @endphp

<x-layouts.app judul="Pengeluaran">
    <div class="flex flex-wrap items-center gap-3">
        <x-ui.button :href="route('pengeluaran.create')" icon="tambah">Catat pengeluaran</x-ui.button>

        <p class="text-sm text-ink-soft">
            Saldo kas tersedia
            <strong class="tabular {{ $saldoKas < 0 ? 'text-keluar' : 'text-masuk' }}">
                {{ Uang::format(Uang::keDesimal($saldoKas)) }}
            </strong>
            @if ($danaCampaign > 0)
                — yang bebas dipakai
                <strong class="tabular {{ $saldoBebas < 0 ? 'text-keluar' : 'text-masuk' }}">
                    {{ Uang::format(Uang::keDesimal($saldoBebas)) }}
                </strong>,
                sisanya milik campaign yang sedang berjalan.
            @endif
        </p>
    </div>

    <x-ui.card padat>
        <form method="GET" action="{{ route('pengeluaran.index') }}"
              class="flex flex-wrap items-end gap-3 border-b border-line px-4 py-3.5 sm:px-5">
            <div class="w-40">
                <label for="dari" class="block text-sm font-semibold">Dari tanggal</label>
                <input type="date" id="dari" name="dari" value="{{ $dari }}"
                       class="mt-1.5 block w-full min-h-11 rounded-xl border border-line-strong bg-card px-3 py-2.5 text-base">
            </div>
            <div class="w-40">
                <label for="sampai" class="block text-sm font-semibold">Sampai tanggal</label>
                <input type="date" id="sampai" name="sampai" value="{{ $sampai }}"
                       class="mt-1.5 block w-full min-h-11 rounded-xl border border-line-strong bg-card px-3 py-2.5 text-base">
            </div>
            <x-ui.button type="submit" variant="secondary">Terapkan</x-ui.button>
            @if ($dari || $sampai)
                <x-ui.button :href="route('pengeluaran.index')" variant="ghost">Hapus filter</x-ui.button>
            @endif
        </form>

        @if ($daftarPengeluaran->isEmpty())
            <x-ui.empty ikon="keluar-kas" judul="Belum ada pengeluaran">
                Catat setiap uang kas yang dipakai, sekecil apa pun. Pengeluaran yang tidak tercatat
                adalah penyebab paling sering saldo tidak cocok.
                <x-slot:aksi>
                    <x-ui.button :href="route('pengeluaran.create')" icon="tambah">Catat pengeluaran</x-ui.button>
                </x-slot:aksi>
            </x-ui.empty>
        @else
            <div class="hidden border-b border-line px-5 py-2 text-xs font-semibold uppercase tracking-wide text-ink-faint sm:grid sm:grid-cols-[6.5rem_1fr_9rem_8rem_7rem]">
                <span>Tanggal</span>
                <span>Keterangan</span>
                <span>Kategori</span>
                <span class="text-right">Jumlah</span>
                <span class="text-right">Aksi</span>
            </div>

            <ul class="divide-y divide-line">
                @foreach ($daftarPengeluaran as $keluar)
                    <li class="grid gap-x-3 gap-y-1 px-4 py-3 sm:grid-cols-[6.5rem_1fr_9rem_8rem_7rem] sm:items-center sm:px-5">
                        <span class="text-sm text-ink-soft tabular">{{ $keluar->tanggal->translatedFormat('j M Y') }}</span>

                        <span class="font-semibold text-ink">{{ $keluar->keterangan }}</span>

                        <span class="flex flex-wrap items-center gap-1">
                            <x-ui.badge>{{ $keluar->category->nama }}</x-ui.badge>
                            @if ($keluar->campaign)
                                <x-ui.badge tipe="info">{{ $keluar->campaign->nama }}</x-ui.badge>
                            @endif
                        </span>

                        <span class="font-semibold text-keluar tabular sm:text-right">
                            − {{ Uang::format($keluar->jumlah) }}
                        </span>

                        <span class="flex items-center gap-1 sm:justify-end">
                            @if ($keluar->bukti_path)
                                <a href="{{ route('pengeluaran.bukti', $keluar) }}" target="_blank" rel="noopener"
                                   class="inline-flex min-h-11 items-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-brand transition-colors hover:bg-brand-soft">
                                    <x-icon name="mata" class="size-4" />
                                    <span class="sr-only">Lihat bukti {{ $keluar->keterangan }}</span>
                                </a>
                            @endif

                            <a href="{{ route('pengeluaran.edit', $keluar) }}"
                               class="inline-flex min-h-11 items-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-brand transition-colors hover:bg-brand-soft">
                                <x-icon name="ubah" class="size-4" />
                                <span class="sr-only">Ubah {{ $keluar->keterangan }}</span>
                            </a>

                            <x-ui.confirm
                                :action="route('pengeluaran.destroy', $keluar)"
                                judul="Hapus pengeluaran ini?"
                                :pesan="'Saldo kas akan bertambah kembali sebesar '.Uang::format($keluar->jumlah).'. Catatannya tetap tersimpan di audit log.'"
                                tombol="Hapus">
                                <x-icon name="hapus" class="size-4" />
                                <span class="sr-only">Hapus {{ $keluar->keterangan }}</span>
                            </x-ui.confirm>
                        </span>
                    </li>
                @endforeach
            </ul>

            @if ($daftarPengeluaran->hasPages())
                <div class="border-t border-line px-4 py-3 sm:px-5">{{ $daftarPengeluaran->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</x-layouts.app>
