@php use App\Support\Uang; @endphp

<x-layouts.app judul="Pembayaran">
    <x-ui.button :href="route('pembayaran.create')" icon="tambah">Catat pembayaran</x-ui.button>

    <x-ui.card padat>
        <form method="GET" action="{{ route('pembayaran.index') }}"
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
                <x-ui.button :href="route('pembayaran.index')" variant="ghost">Hapus filter</x-ui.button>
            @endif
        </form>

        @if ($daftarPembayaran->isEmpty())
            <x-ui.empty ikon="bayar" judul="Belum ada pembayaran tercatat">
                Setiap uang yang masuk ke tanganmu sebaiknya langsung dicatat di sini, supaya saldo kas
                selalu cocok dengan uang yang benar-benar ada.
                <x-slot:aksi>
                    <x-ui.button :href="route('pembayaran.create')" icon="tambah">Catat pembayaran</x-ui.button>
                </x-slot:aksi>
            </x-ui.empty>
        @else
            <div class="hidden border-b border-line px-5 py-2 text-xs font-semibold uppercase tracking-wide text-ink-faint sm:grid sm:grid-cols-[6.5rem_1fr_7rem_8rem_6rem]">
                <span>Tanggal</span>
                <span>Siswa</span>
                <span>Metode</span>
                <span class="text-right">Jumlah</span>
                <span class="text-right">Aksi</span>
            </div>

            <ul class="divide-y divide-line">
                @foreach ($daftarPembayaran as $bayar)
                    @php
                        $teralokasi = $bayar->allocations->sum(fn ($a) => Uang::keSen($a->jumlah));
                        $sisaDeposit = Uang::keSen($bayar->jumlah) - $teralokasi;
                    @endphp
                    <li class="grid gap-x-3 gap-y-1 px-4 py-3 sm:grid-cols-[6.5rem_1fr_7rem_8rem_6rem] sm:items-center sm:px-5">
                        <span class="text-sm text-ink-soft tabular">{{ $bayar->tanggal->translatedFormat('j M Y') }}</span>

                        <span class="min-w-0">
                            <a href="{{ route('siswa.show', $bayar->student) }}"
                               class="font-semibold text-ink underline-offset-2 hover:text-brand hover:underline">
                                {{ $bayar->student->nama }}
                            </a>
                            @if ($bayar->catatan)
                                <span class="block truncate text-sm text-ink-faint">{{ $bayar->catatan }}</span>
                            @endif
                            @if ($sisaDeposit > 0)
                                <span class="mt-1 inline-block">
                                    <x-ui.badge tipe="info">
                                        deposit {{ Uang::format(Uang::keDesimal($sisaDeposit)) }}
                                    </x-ui.badge>
                                </span>
                            @endif
                        </span>

                        <span class="flex items-center gap-1.5 text-sm text-ink-soft">
                            <x-icon :name="$bayar->metode === 'tunai' ? 'dompet' : 'masuk-arah'" class="size-4" />
                            <span class="capitalize">{{ $bayar->metode }}</span>
                        </span>

                        <span class="font-semibold text-masuk tabular sm:text-right">
                            + {{ Uang::format($bayar->jumlah) }}
                        </span>

                        <span class="flex items-center gap-1 sm:justify-end">
                            @if ($bayar->bukti_path)
                                <a href="{{ route('pembayaran.bukti', $bayar) }}" target="_blank" rel="noopener"
                                   class="inline-flex min-h-11 items-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-brand transition-colors hover:bg-brand-soft">
                                    <x-icon name="mata" class="size-4" />
                                    <span class="sr-only">Lihat bukti pembayaran {{ $bayar->student->nama }}</span>
                                </a>
                            @endif

                            <x-ui.confirm
                                :action="route('pembayaran.destroy', $bayar)"
                                judul="Hapus pembayaran ini?"
                                :pesan="'Alokasinya ikut dilepas dan status tagihan '.$bayar->student->nama.' kembali seperti sebelum dibayar. Catatannya tetap tersimpan di audit log.'"
                                tombol="Hapus pembayaran">
                                <x-icon name="hapus" class="size-4" />
                                <span class="sr-only">Hapus pembayaran {{ $bayar->student->nama }}</span>
                            </x-ui.confirm>
                        </span>
                    </li>
                @endforeach
            </ul>

            @if ($daftarPembayaran->hasPages())
                <div class="border-t border-line px-4 py-3 sm:px-5">{{ $daftarPembayaran->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</x-layouts.app>
