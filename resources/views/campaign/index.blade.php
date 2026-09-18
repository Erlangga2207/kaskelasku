@php use App\Support\Uang; @endphp

<x-layouts.app judul="Iuran insidental">
    {{-- Pemisahan saldo ditaruh paling atas: inilah alasan utama fitur ini ada. --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <x-ui.stat label="Saldo kas" ikon="dompet" nada="netral"
                   :nilai="Uang::format(Uang::keDesimal($saldoKas))"
                   keterangan="Seluruh uang yang ada di tangan bendahara" />

        <x-ui.stat label="Saldo bebas" ikon="cek" nada="masuk"
                   :nilai="Uang::format(Uang::keDesimal($saldoBebas))"
                   keterangan="Boleh dipakai untuk keperluan umum kelas" />

        <x-ui.stat label="Dana campaign" ikon="kunci" nada="brand"
                   :nilai="Uang::format(Uang::keDesimal($danaCampaign))"
                   keterangan="Sudah ada peruntukannya, belum dibelanjakan" />
    </div>

    <x-ui.card judul="Campaign iuran insidental"
               :keterangan="$rekap->count().' campaign'" padat>
        <x-slot:aksi>
            <x-ui.button :href="route('campaign.create')" size="sm" icon="tambah">Campaign baru</x-ui.button>
        </x-slot:aksi>

        @if ($rekap->isEmpty())
            <x-ui.empty ikon="dompet" judul="Belum ada iuran insidental">
                Iuran insidental dipakai untuk penggalangan sekali jalan — studi tour, perpisahan,
                atau bingkisan guru — dengan nominal sama per peserta, terpisah dari iuran rutin.
            </x-ui.empty>
        @else
            <ul class="divide-y divide-line">
                @foreach ($rekap as $baris)
                    @php $campaign = $baris['campaign']; @endphp
                    <li class="px-4 py-4 sm:px-5">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <a href="{{ route('campaign.show', $campaign) }}"
                               class="min-w-0 font-semibold underline-offset-2 hover:text-brand hover:underline">
                                {{ $campaign->nama }}
                                <span class="block text-xs font-normal text-ink-faint">
                                    {{ $baris['peserta'] }} peserta &middot;
                                    {{ Uang::format($campaign->nominal_per_siswa) }} per siswa
                                    @if ($campaign->deadline)
                                        &middot; batas {{ $campaign->deadline->translatedFormat('j M Y') }}
                                    @endif
                                </span>
                            </a>

                            <span class="flex shrink-0 items-center gap-2">
                                @if ($campaign->status === 'aktif')
                                    <x-ui.badge tipe="info">Aktif</x-ui.badge>
                                @elseif ($campaign->status === 'selesai')
                                    <x-ui.badge tipe="lunas">Selesai</x-ui.badge>
                                @else
                                    <x-ui.badge tipe="netral">Dibatalkan</x-ui.badge>
                                @endif
                            </span>
                        </div>

                        @if (! $campaign->isDibatalkan())
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface"
                                     role="progressbar" aria-valuenow="{{ $baris['persen'] }}"
                                     aria-valuemin="0" aria-valuemax="100"
                                     aria-label="Ketercapaian {{ $campaign->nama }}">
                                    <div class="h-full rounded-full bg-masuk transition-[width] duration-300"
                                         style="width: {{ min($baris['persen'], 100) }}%"></div>
                                </div>
                                <span class="w-12 shrink-0 text-right text-xs font-semibold tabular text-ink-soft">
                                    {{ $baris['persen'] }}%
                                </span>
                            </div>

                            <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-sm sm:grid-cols-4">
                                <div>
                                    <dt class="text-xs text-ink-faint">Target</dt>
                                    <dd class="font-semibold tabular">{{ Uang::format(Uang::keDesimal($baris['tertagih'])) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-ink-faint">Terkumpul</dt>
                                    <dd class="font-semibold tabular text-masuk">{{ Uang::format(Uang::keDesimal($baris['terkumpul'])) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-ink-faint">Terpakai</dt>
                                    <dd class="font-semibold tabular text-keluar">{{ Uang::format(Uang::keDesimal($baris['terpakai'])) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-ink-faint">Sisa</dt>
                                    <dd class="font-semibold tabular">{{ Uang::format(Uang::keDesimal($baris['sisa'])) }}</dd>
                                </div>
                            </dl>
                        @else
                            <p class="mt-2 text-sm text-ink-faint">
                                Tagihannya sudah ditarik. Uang yang terlanjur dibayar tidak dihapus —
                                sisanya menjadi deposit siswa.
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</x-layouts.app>
