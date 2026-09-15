@php use App\Support\Uang; @endphp

<x-layouts.app :judul="'Kartu iuran '.$siswa->nama">
    <x-ui.button :href="route('siswa.index')" variant="ghost" icon="kiri" size="sm">Kembali ke daftar siswa</x-ui.button>

    <div class="grid gap-3 sm:grid-cols-3">
        <x-ui.card>
            <p class="text-sm text-ink-faint">Tunggakan</p>
            <p class="mt-1 text-2xl font-extrabold tabular {{ $tunggakan > 0 ? 'text-keluar' : 'text-masuk' }}">
                {{ Uang::format(Uang::keDesimal($tunggakan)) }}
            </p>
            <p class="mt-1 text-xs text-ink-faint">
                {{ $kelas->denda_aktif ? 'Termasuk denda keterlambatan.' : 'Denda tidak aktif di kelas ini.' }}
            </p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm text-ink-faint">Deposit</p>
            <p class="mt-1 text-2xl font-extrabold tabular text-brand">
                {{ Uang::format(Uang::keDesimal($deposit)) }}
            </p>
            <p class="mt-1 text-xs text-ink-faint">Kelebihan bayar, terpakai otomatis di tagihan berikutnya.</p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm text-ink-faint">Status</p>
            <p class="mt-1">
                @if ($siswa->is_active)
                    <x-ui.badge tipe="lunas" ikon="cek">Aktif</x-ui.badge>
                @else
                    <x-ui.badge tipe="netral">Nonaktif</x-ui.badge>
                @endif
            </p>
            <p class="mt-2 text-xs text-ink-faint">
                Absen {{ $siswa->no_absen ?? '—' }} · aktif sejak
                {{ $siswa->tgl_mulai_aktif->translatedFormat('j M Y') }}
            </p>
        </x-ui.card>
    </div>

    <x-ui.card judul="Tagihan" :keterangan="$tagihan->count().' tagihan'" padat>
        <x-slot:aksi>
            <x-ui.button :href="route('pembayaran.create', ['siswa' => $siswa->id])" size="sm" icon="tambah">
                Catat pembayaran
            </x-ui.button>
        </x-slot:aksi>

        @if ($tagihan->isEmpty())
            <x-ui.empty ikon="periode" judul="Belum ada tagihan">
                Tagihan muncul setelah periode iuran dibuat.
            </x-ui.empty>
        @else
            <ul class="divide-y divide-line">
                @foreach ($tagihan as $bill)
                    @php
                        $status = $kas->statusTagihan($bill);
                        $sisa = $kas->sisaTagihan($bill);
                        $denda = $kas->dendaTagihan($bill, $kelas);
                    @endphp
                    <li x-data="{ panel: false }" class="px-4 py-3 sm:px-5">
                        <div class="grid gap-x-3 gap-y-1 sm:grid-cols-[1fr_8rem_8rem_7rem] sm:items-center">
                            <span class="font-semibold">{{ $bill->period?->label ?? 'Iuran insidental' }}</span>

                            <span class="tabular text-sm text-ink-soft">
                                {{ Uang::format($bill->nominal) }}
                                @if ($denda > 0)
                                    <span class="block text-xs text-tunggak">
                                        + denda {{ Uang::format(Uang::keDesimal($denda)) }}
                                    </span>
                                @endif
                            </span>

                            <span>
                                @switch($status)
                                    @case('lunas')<x-ui.badge tipe="lunas" ikon="cek">Lunas</x-ui.badge>@break
                                    @case('kurang')<x-ui.badge tipe="kurang">Kurang {{ Uang::format(Uang::keDesimal($sisa)) }}</x-ui.badge>@break
                                    @case('bebas')<x-ui.badge tipe="info">Dibebaskan</x-ui.badge>@break
                                    @default<x-ui.badge tipe="belum">Belum bayar</x-ui.badge>
                                @endswitch
                            </span>

                            <span class="sm:text-right">
                                <button type="button" @click="panel = !panel" :aria-expanded="panel"
                                        class="inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-brand transition-colors hover:bg-brand-soft">
                                    <x-icon name="pengaturan" class="size-4" />
                                    <span>Pembebasan</span>
                                </button>
                            </span>
                        </div>

                        @if ($bill->is_bebas && $bill->alasan_bebas)
                            <p class="mt-1 text-sm text-ink-faint">Alasan: {{ $bill->alasan_bebas }}</p>
                        @endif

                        <div x-cloak x-show="panel" x-collapse class="mt-3 rounded-xl bg-surface p-4">
                            @if ($bill->is_bebas)
                                <form method="POST" action="{{ route('tagihan.bebas', $bill) }}" class="space-y-3">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="bebas" value="0">
                                    <p class="text-sm text-ink-soft">
                                        Membatalkan pembebasan akan menagihkan kembali
                                        {{ Uang::format($bill->nominal) }} kepada {{ $siswa->nama }}.
                                    </p>
                                    <x-ui.button type="submit" variant="secondary" size="sm">Batalkan pembebasan</x-ui.button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('tagihan.bebas', $bill) }}" class="space-y-3">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="bebas" value="1">

                                    <x-ui.field label="Alasan pembebasan" name="alasan_bebas" wajib
                                                :id="'alasan-'.$bill->id"
                                                placeholder="mis. keringanan dari wali kelas"
                                                bantuan="Alasan wajib diisi supaya keputusan ini bisa dipertanggungjawabkan." />

                                    <x-ui.button type="submit" size="sm">Bebaskan tagihan ini</x-ui.button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.card judul="Riwayat pembayaran" padat>
        @if ($pembayaran->isEmpty())
            <x-ui.empty ikon="bayar" judul="Belum pernah membayar">
                Catat pembayaran pertamanya lewat tombol di atas.
            </x-ui.empty>
        @else
            <ul class="divide-y divide-line">
                @foreach ($pembayaran as $bayar)
                    <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                        <span>
                            <span class="block font-semibold">{{ $bayar->tanggal->translatedFormat('j M Y') }}</span>
                            <span class="block text-sm capitalize text-ink-faint">
                                {{ $bayar->metode }}{{ $bayar->catatan ? ' · '.$bayar->catatan : '' }}
                            </span>
                        </span>
                        <span class="font-semibold text-masuk tabular">+ {{ Uang::format($bayar->jumlah) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</x-layouts.app>
