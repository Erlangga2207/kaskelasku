@php use App\Support\Uang; @endphp

<x-layouts.app :judul="$campaign->nama">
    <x-ui.button :href="route('campaign.index')" variant="ghost" icon="kiri" size="sm">Kembali ke daftar campaign</x-ui.button>

    <x-ui.card :judul="$campaign->nama" :keterangan="$campaign->deskripsi">
        <x-slot:aksi>
            @unless ($campaign->isDibatalkan())
                <x-ui.button :href="route('campaign.edit', $campaign)" variant="secondary" size="sm" icon="ubah">Ubah</x-ui.button>
            @endunless
        </x-slot:aksi>

        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                @if ($campaign->status === 'aktif')
                    <x-ui.badge tipe="info">Aktif</x-ui.badge>
                @elseif ($campaign->status === 'selesai')
                    <x-ui.badge tipe="lunas">Selesai</x-ui.badge>
                @else
                    <x-ui.badge tipe="netral">Dibatalkan</x-ui.badge>
                @endif

                <span class="text-sm text-ink-soft">
                    {{ Uang::format($campaign->nominal_per_siswa) }} per siswa
                    &middot; {{ $ringkasan['peserta'] }} peserta
                    @if ($campaign->deadline)
                        &middot; batas {{ $campaign->deadline->translatedFormat('j F Y') }}
                    @else
                        &middot; tanpa batas waktu
                    @endif
                </span>
            </div>

            <div class="grid gap-3 sm:grid-cols-4">
                <x-ui.stat label="Target" :nilai="Uang::format(Uang::keDesimal($ringkasan['tertagih']))"
                           :keterangan="$ringkasan['lunas'].' dari '.$ringkasan['peserta'].' peserta lunas'" />
                <x-ui.stat label="Terkumpul" nada="masuk" ikon="masuk-arah"
                           :nilai="Uang::format(Uang::keDesimal($ringkasan['terkumpul']))"
                           :keterangan="'Kurang '.Uang::format(Uang::keDesimal($ringkasan['kurang']))" />
                <x-ui.stat label="Terpakai" nada="keluar" ikon="keluar-arah"
                           :nilai="Uang::format(Uang::keDesimal($ringkasan['terpakai']))"
                           :keterangan="$pengeluaran->count().' pengeluaran ditandai campaign ini'" />
                <x-ui.stat label="Sisa dana" nada="brand" ikon="dompet"
                           :nilai="Uang::format(Uang::keDesimal($ringkasan['sisa']))"
                           keterangan="Terpisah dari saldo bebas kelas" />
            </div>

            @unless ($campaign->isDibatalkan())
                <div class="flex flex-wrap gap-2 border-t border-line pt-4">
                    @if ($campaign->isAktif())
                        <form method="POST" action="{{ route('campaign.status', $campaign) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="selesai">
                            <x-ui.button type="submit" variant="secondary" size="sm">Tandai selesai</x-ui.button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('campaign.status', $campaign) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="aktif">
                            <x-ui.button type="submit" variant="secondary" size="sm">Aktifkan kembali</x-ui.button>
                        </form>
                    @endif

                    <x-ui.confirm :action="route('campaign.status', $campaign)" method="PATCH"
                                  :input="['status' => 'dibatalkan']"
                                  judul="Batalkan campaign ini?"
                                  pesan="Seluruh tagihan campaign ditarik. Pembayaran yang sudah masuk TIDAK dihapus — uangnya kembali menjadi deposit siswa dan otomatis dipakai untuk tagihan lain yang belum lunas."
                                  tombol="Ya, batalkan">
                        <x-icon name="hapus" class="size-4" /> Batalkan campaign
                    </x-ui.confirm>
                </div>
            @endunless
        </div>
    </x-ui.card>

    @if ($campaign->isDibatalkan())
        <x-ui.alert tipe="peringatan" judul="Campaign ini dibatalkan">
            Tagihannya sudah ditarik dan uang yang terlanjur dibayar dikembalikan menjadi deposit siswa.
            Riwayatnya sengaja tetap disimpan supaya bisa ditelusuri di audit log.
        </x-ui.alert>
    @endif

    <x-ui.card judul="Status bayar peserta" :keterangan="$tagihan->count().' tagihan'" padat>
        @if ($tagihan->isEmpty())
            <x-ui.empty ikon="peringatan" judul="Tidak ada tagihan">
                Campaign ini tidak punya satu pun tagihan, jadi tidak ada uang yang bisa masuk ke sini.
                Ubah campaign dan pilih pesertanya.
            </x-ui.empty>
        @else
            <ul class="divide-y divide-line">
                @foreach ($tagihan as $bill)
                    @php
                        $status = $kas->statusTagihan($bill);
                        $sisa = $kas->sisaTagihan($bill);
                    @endphp
                    <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                        <span class="min-w-0">
                            @if ($bill->student)
                                <a href="{{ route('siswa.show', $bill->student) }}"
                                   class="font-medium underline-offset-2 hover:text-brand hover:underline">
                                    @if ($bill->student->no_absen){{ $bill->student->no_absen }}.@endif
                                    {{ $bill->student->nama }}
                                </a>
                            @else
                                <span class="font-medium text-ink-faint">Siswa terhapus</span>
                            @endif
                            <span class="block text-xs text-ink-faint">
                                Dibayar {{ Uang::format(Uang::keDesimal($kas->dibayarTagihan($bill))) }}
                                dari {{ Uang::format($bill->nominal) }}
                            </span>
                        </span>

                        <span class="flex shrink-0 items-center gap-2">
                            @if ($sisa > 0)
                                <span class="text-sm font-semibold text-keluar tabular">
                                    {{ Uang::format(Uang::keDesimal($sisa)) }}
                                </span>
                            @endif
                            <x-ui.badge :tipe="$status === 'bebas' ? 'netral' : $status">
                                {{ ['belum' => 'Belum bayar', 'kurang' => 'Kurang', 'lunas' => 'Lunas', 'bebas' => 'Bebas'][$status] }}
                            </x-ui.badge>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.card judul="Pengeluaran campaign ini" :keterangan="'Total '.Uang::format(Uang::keDesimal($ringkasan['terpakai']))" padat>
        <x-slot:aksi>
            <x-ui.button :href="route('pengeluaran.create')" variant="ghost" size="sm" icon="tambah">Catat pengeluaran</x-ui.button>
        </x-slot:aksi>

        @if ($pengeluaran->isEmpty())
            <x-ui.empty ikon="keluar-kas" judul="Belum ada pengeluaran">
                Saat membelanjakan uang campaign ini, tandai pengeluarannya sebagai milik
                &ldquo;{{ $campaign->nama }}&rdquo; supaya sisa dananya ikut berkurang.
            </x-ui.empty>
        @else
            <ul class="divide-y divide-line">
                @foreach ($pengeluaran as $keluar)
                    <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{ $keluar->keterangan }}</span>
                            <span class="block text-xs text-ink-faint">
                                {{ $keluar->tanggal->translatedFormat('j M Y') }}
                                &middot; {{ $keluar->category?->nama ?? 'tanpa kategori' }}
                            </span>
                        </span>
                        <span class="shrink-0 font-semibold text-keluar tabular">
                            − {{ Uang::format($keluar->jumlah) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</x-layouts.app>
