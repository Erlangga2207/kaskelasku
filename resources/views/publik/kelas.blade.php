@php use App\Support\Uang; @endphp

<x-layouts.publik :judul="'Kas Kelas '.$kelas->nama_kelas" :kelas="$kelas" :token="$kelas->public_token">

    <section class="rounded-card border border-line bg-card p-5 shadow-sm">
        <p class="text-sm font-medium text-ink-faint">Saldo kas saat ini</p>
        <p class="mt-1 text-4xl font-extrabold tabular {{ $ringkasan['saldo'] < 0 ? 'text-keluar' : 'text-ink' }}">
            {{ Uang::format(Uang::keDesimal($ringkasan['saldo'])) }}
        </p>
        <p class="mt-1.5 text-sm text-ink-faint">
            Diperbarui {{ $diperbaruiPada->translatedFormat('j M Y, H:i') }} WIB, langsung dari catatan transaksi.
        </p>
    </section>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-ui.stat label="Total uang masuk" ikon="masuk-arah" nada="masuk"
                   :nilai="Uang::format(Uang::keDesimal($ringkasan['masuk']))" />
        <x-ui.stat label="Total uang keluar" ikon="keluar-arah" nada="keluar"
                   :nilai="Uang::format(Uang::keDesimal($ringkasan['keluar']))" />
    </div>

    @if ($rekap->isNotEmpty())
        <x-ui.card judul="Rekap per periode" padat>
            <ul class="divide-y divide-line">
                @foreach ($rekap as $baris)
                    @php
                        $persen = $baris['tertagih'] > 0
                            ? (int) round($baris['terkumpul'] / $baris['tertagih'] * 100)
                            : 100;
                    @endphp
                    <li class="px-4 py-3 sm:px-5">
                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                            <span class="font-semibold">
                                {{ $baris['periode']->label }}
                                @if ($baris['periode']->is_libur)
                                    <x-ui.badge tipe="netral">Libur</x-ui.badge>
                                @endif
                            </span>
                            <span class="text-sm text-ink-soft tabular">
                                {{ Uang::format(Uang::keDesimal($baris['terkumpul'])) }}
                                dari {{ Uang::format(Uang::keDesimal($baris['tertagih'])) }}
                            </span>
                        </div>

                        <div class="mt-2 flex items-center gap-2">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface"
                                 role="progressbar" aria-valuenow="{{ $persen }}" aria-valuemin="0" aria-valuemax="100"
                                 aria-label="Ketercapaian {{ $baris['periode']->label }}">
                                <div class="h-full rounded-full bg-masuk" style="width: {{ min($persen, 100) }}%"></div>
                            </div>
                            <span class="w-14 shrink-0 text-right text-xs font-semibold tabular text-ink-soft">
                                {{ $persen }}% · {{ $baris['lunas'] }}/{{ $baris['jumlah_tagihan'] }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    @if ($campaign->isNotEmpty())
        {{-- Progres iuran insidental. Yang tampil hanya angka kelas — tidak ada
             satu pun data pribadi tambahan di luar yang sudah ada di halaman ini. --}}
        <x-ui.card judul="Iuran insidental" padat>
            <ul class="divide-y divide-line">
                @foreach ($campaign as $baris)
                    <li class="px-4 py-3 sm:px-5">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                            <span class="font-semibold">
                                {{ $baris['campaign']->nama }}
                                @if ($baris['campaign']->status === 'selesai')
                                    <x-ui.badge tipe="lunas">Selesai</x-ui.badge>
                                @endif
                            </span>
                            <span class="text-sm text-ink-soft tabular">
                                {{ Uang::format(Uang::keDesimal($baris['terkumpul'])) }}
                                / {{ Uang::format(Uang::keDesimal($baris['tertagih'])) }}
                            </span>
                        </div>

                        @if ($baris['campaign']->deskripsi)
                            <p class="mt-0.5 text-sm text-ink-faint">{{ $baris['campaign']->deskripsi }}</p>
                        @endif

                        <div class="mt-2 flex items-center gap-2">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface"
                                 role="progressbar" aria-valuenow="{{ $baris['persen'] }}"
                                 aria-valuemin="0" aria-valuemax="100"
                                 aria-label="Ketercapaian {{ $baris['campaign']->nama }}">
                                <div class="h-full rounded-full bg-brand transition-[width] duration-300"
                                     style="width: {{ min($baris['persen'], 100) }}%"></div>
                            </div>
                            <span class="shrink-0 text-xs font-semibold tabular text-ink-soft">
                                {{ $baris['persen'] }}% &middot; {{ $baris['lunas'] }}/{{ $baris['peserta'] }}
                            </span>
                        </div>

                        <p class="mt-1.5 text-xs text-ink-faint">
                            {{ Uang::format($baris['campaign']->nominal_per_siswa) }} per siswa
                            @if ($baris['campaign']->deadline)
                                &middot; batas {{ $baris['campaign']->deadline->translatedFormat('j M Y') }}
                            @endif
                            &middot; terpakai {{ Uang::format(Uang::keDesimal($baris['terpakai'])) }}
                            &middot; sisa {{ Uang::format(Uang::keDesimal($baris['sisa'])) }}
                        </p>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    @if ($kelas->punyaQris())
        {{-- QRIS statis. Tidak ada integrasi pembayaran apa pun: gambar ini hanya
             memudahkan transfer, dan bendahara tetap mencatat serta mengonfirmasi
             uang masuk secara manual. --}}
        <x-ui.card judul="Bayar lewat QRIS" padat>
            <div class="flex flex-col items-center gap-4 px-4 py-4 sm:flex-row sm:items-start sm:px-5">
                {{--
                    width/height eksplisit (224 = w-56) supaya browser sudah
                    menyiapkan ruangnya sebelum gambar selesai dimuat; tanpa itu
                    seluruh isi halaman melompat saat QRIS-nya muncul, dan
                    halaman ini justru paling sering dibuka di jaringan seluler.
                    object-contain menjaga rasio aslinya: berkasnya diunggah
                    bendahara, jadi tidak boleh diandaikan persis persegi.
                --}}
                <img src="{{ route('publik.qris', $kelas->public_token) }}"
                     alt="Kode QRIS kas kelas {{ $kelas->nama_kelas }}"
                     loading="lazy"
                     width="224" height="224"
                     class="size-56 max-w-full shrink-0 rounded-xl border border-line bg-white object-contain p-3">

                <div class="min-w-0 flex-1 space-y-2 text-center sm:text-left">
                    @if ($kelas->qris_nama_pemilik)
                        <div>
                            <p class="text-sm text-ink-faint">Atas nama</p>
                            <p class="text-lg font-bold">{{ $kelas->qris_nama_pemilik }}</p>
                        </div>
                    @endif

                    <p class="text-sm text-ink-soft">
                        Pindai dengan aplikasi bank atau e-wallet, lalu
                        <strong class="font-semibold text-ink">kirim bukti transfernya ke bendahara</strong>.
                    </p>

                    <p class="text-sm text-ink-faint">
                        Status bayar di halaman ini berubah setelah bendahara mencatatnya. Jadi kalau
                        baru saja membayar dan masih tertulis belum lunas, itu wajar — tunggu dicatat dulu.
                    </p>
                </div>
            </div>
        </x-ui.card>
    @endif


    <x-ui.card judul="Status bayar" keterangan="Ketuk nama untuk melihat rincian tagihannya." padat>
        @if ($daftarSiswa->isEmpty())
            <x-ui.empty ikon="siswa" judul="Belum ada data siswa">
                Bendahara belum memasukkan daftar anggota kelas.
            </x-ui.empty>
        @else
            <ul class="divide-y divide-line">
                @foreach ($daftarSiswa as $item)
                    <li x-data="{ buka: false }">
                        <button type="button" @click="buka = !buka" :aria-expanded="buka"
                                class="flex w-full min-h-14 cursor-pointer items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-surface sm:px-5">
                            <span class="w-7 shrink-0 text-sm font-semibold tabular text-ink-faint">
                                {{ $item['siswa']->no_absen ? str_pad($item['siswa']->no_absen, 2, '0', STR_PAD_LEFT) : '—' }}
                            </span>

                            <span class="min-w-0 flex-1 font-semibold">{{ $item['siswa']->nama }}</span>

                            @if ($item['belum_lunas'] === 0)
                                <x-ui.badge tipe="lunas" ikon="cek">Lunas</x-ui.badge>
                            @else
                                <x-ui.badge tipe="kurang">{{ $item['belum_lunas'] }} belum lunas</x-ui.badge>
                            @endif

                            <x-icon name="kanan" class="size-4 shrink-0 text-ink-faint transition-transform"
                                    x-bind:class="buka ? 'rotate-90' : ''" />
                        </button>

                        <div x-cloak x-show="buka" x-collapse class="bg-surface px-4 pb-3 sm:px-5">
                            @if ($item['baris']->isEmpty())
                                <p class="py-3 text-sm text-ink-faint">Belum ada tagihan untuk siswa ini.</p>
                            @else
                                <ul class="divide-y divide-line">
                                    @foreach ($item['baris'] as $tagihan)
                                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                            <span>{{ $tagihan['label'] }}</span>
                                            <span class="flex items-center gap-2">
                                                <span class="tabular text-ink-faint">{{ Uang::format($tagihan['nominal']) }}</span>
                                                @switch($tagihan['status'])
                                                    @case('lunas')<x-ui.badge tipe="lunas" ikon="cek">Lunas</x-ui.badge>@break
                                                    @case('kurang')<x-ui.badge tipe="kurang">Kurang {{ Uang::format(Uang::keDesimal($tagihan['sisa'])) }}</x-ui.badge>@break
                                                    @case('bebas')<x-ui.badge tipe="info">Dibebaskan</x-ui.badge>@break
                                                    @default<x-ui.badge tipe="belum">Belum bayar</x-ui.badge>
                                                @endswitch
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.alert tipe="info" judul="Ada angka yang terasa keliru?">
        Sampaikan ke bendahara kelas. Halaman ini sengaja tidak punya tombol ubah apa pun —
        perubahan hanya bisa dilakukan bendahara lewat akunnya, dan semuanya tercatat.
    </x-ui.alert>
</x-layouts.publik>
