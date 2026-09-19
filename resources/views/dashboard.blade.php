@php use App\Support\Uang; @endphp

<x-layouts.app judul="Beranda">
    {{-- Saldo kas adalah angka yang paling sering dicari, jadi diberi porsi paling besar. --}}
    <section class="rounded-card border border-line bg-card p-5 shadow-sm">
        <p class="text-sm font-medium text-ink-faint">Saldo kas {{ $kelas->nama_kelas }}</p>
        <p class="mt-1 text-4xl font-extrabold tabular {{ $ringkasan['saldo'] < 0 ? 'text-keluar' : 'text-ink' }}">
            {{ Uang::format(Uang::keDesimal($ringkasan['saldo'])) }}
        </p>
        <p class="mt-1.5 text-sm text-ink-faint">
            Dihitung ulang dari seluruh pembayaran dikurangi pengeluaran — bukan angka yang disimpan.
        </p>

        {{-- Saldo besar bisa menyesatkan kalau sebagian sudah milik campaign,
             jadi pembagiannya ditempel langsung di bawah angka utamanya. --}}
        @if ($ringkasan['dana_campaign'] > 0)
            <dl class="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium text-ink-faint">Saldo bebas</dt>
                    <dd class="text-xl font-bold tabular {{ $ringkasan['saldo_bebas'] < 0 ? 'text-keluar' : 'text-masuk' }}">
                        {{ Uang::format(Uang::keDesimal($ringkasan['saldo_bebas'])) }}
                    </dd>
                    <dd class="text-xs text-ink-faint">Boleh dipakai untuk keperluan umum kelas</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-ink-faint">Dana campaign belum terpakai</dt>
                    <dd class="text-xl font-bold tabular text-brand">
                        {{ Uang::format(Uang::keDesimal($ringkasan['dana_campaign'])) }}
                    </dd>
                    <dd class="text-xs text-ink-faint">Sudah ada peruntukannya, bukan uang bebas</dd>
                </div>
            </dl>
        @endif
    </section>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat label="Total masuk" ikon="masuk-arah" nada="masuk"
                   :nilai="Uang::format(Uang::keDesimal($ringkasan['masuk']))" />

        <x-ui.stat label="Total keluar" ikon="keluar-arah" nada="keluar"
                   :nilai="Uang::format(Uang::keDesimal($ringkasan['keluar']))" />

        <x-ui.stat label="Penunggak" ikon="peringatan" nada="tunggak"
                   :nilai="$ringkasan['penunggak'].' siswa'"
                   :keterangan="'Total '.Uang::format(Uang::keDesimal($ringkasan['total_tunggakan']))" />

        <x-ui.stat label="Siswa aktif" ikon="siswa" nada="brand"
                   :nilai="$ringkasan['siswa_aktif']"
                   :keterangan="$ringkasan['deposit'] > 0
                        ? 'Deposit tersimpan '.Uang::format(Uang::keDesimal($ringkasan['deposit']))
                        : 'Belum ada deposit siswa'" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card judul="Penunggak terbesar" keterangan="Lima teratas" padat>
            <x-slot:aksi>
                <x-ui.button :href="route('laporan.index')" variant="ghost" size="sm">Lihat semua</x-ui.button>
            </x-slot:aksi>

            @if ($tunggakanTeratas->isEmpty())
                <x-ui.empty ikon="cek" judul="Tidak ada tunggakan">
                    Semua tagihan yang sudah jatuh tempo sudah lunas. Kabar bagus untuk diumumkan di grup kelas.
                </x-ui.empty>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($tunggakanTeratas as $baris)
                        <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                            <a href="{{ route('siswa.show', $baris['siswa']) }}"
                               class="min-w-0 font-semibold underline-offset-2 hover:text-brand hover:underline">
                                {{ $baris['siswa']->nama }}
                                <span class="block text-xs font-normal text-ink-faint">
                                    {{ $baris['belum_lunas'] }} tagihan belum lunas
                                </span>
                            </a>
                            <span class="shrink-0 font-semibold text-keluar tabular">
                                {{ Uang::format(Uang::keDesimal($baris['tunggakan'])) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card judul="Transaksi terbaru" padat>
            <x-slot:aksi>
                <x-ui.button :href="route('pembayaran.create')" size="sm" icon="tambah">Catat</x-ui.button>
            </x-slot:aksi>

            @if ($riwayatTerbaru->isEmpty())
                <x-ui.empty ikon="dompet" judul="Belum ada transaksi">
                    Mulailah dengan mencatat pembayaran pertama, supaya saldo di atas punya arti.
                </x-ui.empty>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($riwayatTerbaru as $baris)
                        <li class="flex items-center gap-3 px-4 py-3 sm:px-5">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg
                                         {{ $baris['jenis'] === 'masuk' ? 'bg-masuk-soft text-masuk-soft-ink' : 'bg-keluar-soft text-keluar-soft-ink' }}">
                                <x-icon :name="$baris['jenis'] === 'masuk' ? 'masuk-arah' : 'keluar-arah'" class="size-4" />
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $baris['keterangan'] }}</span>
                                <span class="block text-xs text-ink-faint">
                                    {{ $baris['tanggal']->translatedFormat('j M Y') }}
                                </span>
                            </span>

                            <span class="shrink-0 font-semibold tabular {{ $baris['jenis'] === 'masuk' ? 'text-masuk' : 'text-keluar' }}">
                                {{ $baris['jenis'] === 'masuk' ? '+' : '−' }}
                                {{ Uang::format(Uang::keDesimal($baris['jumlah'])) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    @if ($campaign->isNotEmpty())
        <x-ui.card judul="Iuran insidental berjalan" padat>
            <x-slot:aksi>
                <x-ui.button :href="route('campaign.index')" variant="ghost" size="sm">Semua campaign</x-ui.button>
            </x-slot:aksi>

            <ul class="divide-y divide-line">
                @foreach ($campaign as $baris)
                    <li class="px-4 py-3 sm:px-5">
                        <div class="flex items-center justify-between gap-3">
                            <a href="{{ route('campaign.show', $baris['campaign']) }}"
                               class="min-w-0 font-semibold underline-offset-2 hover:text-brand hover:underline">
                                {{ $baris['campaign']->nama }}
                            </a>
                            <span class="text-sm text-ink-soft tabular">
                                {{ Uang::format(Uang::keDesimal($baris['terkumpul'])) }}
                                / {{ Uang::format(Uang::keDesimal($baris['tertagih'])) }}
                            </span>
                        </div>

                        <div class="mt-2 flex items-center gap-2">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface"
                                 role="progressbar" aria-valuenow="{{ $baris['persen'] }}" aria-valuemin="0" aria-valuemax="100"
                                 aria-label="Ketercapaian {{ $baris['campaign']->nama }}">
                                <div class="h-full rounded-full bg-brand transition-[width] duration-300"
                                     style="width: {{ min($baris['persen'], 100) }}%"></div>
                            </div>
                            <span class="w-12 shrink-0 text-right text-xs font-semibold tabular text-ink-soft">
                                {{ $baris['persen'] }}%
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    @if ($rekapTerbaru->isNotEmpty())
        <x-ui.card judul="Rekap periode terakhir" padat>
            <x-slot:aksi>
                <x-ui.button :href="route('laporan.index')" variant="ghost" size="sm">Rekap lengkap</x-ui.button>
            </x-slot:aksi>

            <ul class="divide-y divide-line">
                @foreach ($rekapTerbaru as $baris)
                    @php
                        $persen = $baris['tertagih'] > 0
                            ? (int) round($baris['terkumpul'] / $baris['tertagih'] * 100)
                            : 100;
                    @endphp
                    <li class="px-4 py-3 sm:px-5">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-semibold">{{ $baris['periode']->label }}</span>
                            <span class="text-sm text-ink-soft tabular">
                                {{ Uang::format(Uang::keDesimal($baris['terkumpul'])) }}
                                / {{ Uang::format(Uang::keDesimal($baris['tertagih'])) }}
                            </span>
                        </div>

                        {{-- Progres ditulis juga sebagai angka: warna saja tidak cukup. --}}
                        <div class="mt-2 flex items-center gap-2">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface"
                                 role="progressbar" aria-valuenow="{{ $persen }}" aria-valuemin="0" aria-valuemax="100"
                                 aria-label="Ketercapaian {{ $baris['periode']->label }}">
                                <div class="h-full rounded-full bg-masuk transition-[width] duration-300"
                                     style="width: {{ min($persen, 100) }}%"></div>
                            </div>
                            <span class="w-12 shrink-0 text-right text-xs font-semibold tabular text-ink-soft">
                                {{ $persen }}%
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</x-layouts.app>
