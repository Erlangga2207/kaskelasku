@php use App\Support\Uang; @endphp

<x-layouts.app judul="Laporan">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat label="Saldo kas" ikon="dompet"
                   :nada="$ringkasan['saldo'] < 0 ? 'keluar' : 'netral'"
                   :nilai="Uang::format(Uang::keDesimal($ringkasan['saldo']))" />
        <x-ui.stat label="Total masuk" ikon="masuk-arah" nada="masuk"
                   :nilai="Uang::format(Uang::keDesimal($ringkasan['masuk']))" />
        <x-ui.stat label="Total keluar" ikon="keluar-arah" nada="keluar"
                   :nilai="Uang::format(Uang::keDesimal($ringkasan['keluar']))" />
        <x-ui.stat label="Tunggakan" ikon="peringatan" nada="tunggak"
                   :nilai="Uang::format(Uang::keDesimal($ringkasan['total_tunggakan']))"
                   :keterangan="$ringkasan['penunggak'].' siswa menunggak'" />
    </div>

    <x-ui.card judul="Rekap per periode" padat>
        @if ($rekap->isEmpty())
            <x-ui.empty ikon="periode" judul="Belum ada periode">
                Rekap muncul setelah periode iuran dibuat.
            </x-ui.empty>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-sm">
                    <caption class="sr-only">Rekap iuran per periode</caption>
                    <thead>
                        <tr class="border-b border-line text-left text-xs uppercase tracking-wide text-ink-faint">
                            <th scope="col" class="px-4 py-2 font-semibold sm:px-5">Periode</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Tertagih</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Terkumpul</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Sisa</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold sm:px-5">Lunas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($rekap as $baris)
                            <tr>
                                <th scope="row" class="px-4 py-2.5 text-left font-semibold sm:px-5">
                                    {{ $baris['periode']->label }}
                                    @if ($baris['periode']->is_libur)
                                        <x-ui.badge tipe="netral">Libur</x-ui.badge>
                                    @endif
                                </th>
                                <td class="px-4 py-2.5 text-right tabular">{{ Uang::format(Uang::keDesimal($baris['tertagih'])) }}</td>
                                <td class="px-4 py-2.5 text-right tabular text-masuk">{{ Uang::format(Uang::keDesimal($baris['terkumpul'])) }}</td>
                                <td class="px-4 py-2.5 text-right tabular {{ $baris['sisa'] > 0 ? 'text-keluar' : 'text-ink-faint' }}">
                                    {{ Uang::format(Uang::keDesimal($baris['sisa'])) }}
                                </td>
                                <td class="px-4 py-2.5 text-right tabular sm:px-5">
                                    {{ $baris['lunas'] }} / {{ $baris['jumlah_tagihan'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card judul="Daftar tunggakan" keterangan="Diurutkan dari yang terbesar" padat>
        @unless ($tunggakan->isEmpty())
            <x-slot:aksi>
                <x-ui.button :href="route('pengingat.index')" variant="secondary" size="sm" icon="surat">
                    Buat pengingat
                </x-ui.button>
            </x-slot:aksi>
        @endunless

        @if ($tunggakan->isEmpty())
            <x-ui.empty ikon="cek" judul="Tidak ada tunggakan">
                Seluruh tagihan yang sudah jatuh tempo sudah lunas.
            </x-ui.empty>
        @else
            <ul class="divide-y divide-line">
                @foreach ($tunggakan as $baris)
                    <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                        <span class="min-w-0">
                            <a href="{{ route('siswa.show', $baris['siswa']) }}"
                               class="font-semibold underline-offset-2 hover:text-brand hover:underline">
                                {{ $baris['siswa']->no_absen ? $baris['siswa']->no_absen.'. ' : '' }}{{ $baris['siswa']->nama }}
                            </a>
                            <span class="block text-xs text-ink-faint">
                                {{ $baris['belum_lunas'] }} tagihan belum lunas
                                @unless ($baris['siswa']->is_active) · sudah nonaktif @endunless
                            </span>
                        </span>
                        <span class="shrink-0 font-semibold text-keluar tabular">
                            {{ Uang::format(Uang::keDesimal($baris['tunggakan'])) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.card judul="Riwayat transaksi" padat>
        <x-slot:aksi>
            <x-ui.button :href="route('laporan.pdf', request()->only('dari', 'sampai'))"
                         variant="secondary" size="sm" icon="unduh">
                Unduh PDF
            </x-ui.button>
        </x-slot:aksi>

        <form method="GET" action="{{ route('laporan.index') }}"
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
                <x-ui.button :href="route('laporan.index')" variant="ghost">Hapus filter</x-ui.button>
            @endif
        </form>

        @if ($riwayat->isEmpty())
            <x-ui.empty ikon="dompet" judul="Tidak ada transaksi pada rentang ini">
                Coba longgarkan filter tanggalnya.
            </x-ui.empty>
        @else
            <ul class="divide-y divide-line">
                @foreach ($riwayat as $baris)
                    <li class="flex items-center gap-3 px-4 py-3 sm:px-5">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg
                                     {{ $baris['jenis'] === 'masuk' ? 'bg-masuk-soft text-masuk-soft-ink' : 'bg-keluar-soft text-keluar-soft-ink' }}">
                            <x-icon :name="$baris['jenis'] === 'masuk' ? 'masuk-arah' : 'keluar-arah'" class="size-4" />
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ $baris['keterangan'] }}</span>
                            <span class="block text-xs text-ink-faint">{{ $baris['tanggal']->translatedFormat('j M Y') }}</span>
                        </span>

                        <span class="shrink-0 font-semibold tabular {{ $baris['jenis'] === 'masuk' ? 'text-masuk' : 'text-keluar' }}">
                            {{ $baris['jenis'] === 'masuk' ? '+' : '−' }} {{ Uang::format(Uang::keDesimal($baris['jumlah'])) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</x-layouts.app>
