@php use App\Support\Uang; @endphp

<x-layouts.app judul="Periode iuran">
    <x-ui.card judul="Buat periode"
               keterangan="Periode dibuat berurutan sampai akhir tahun ajaran ({{ $akhirTahunAjaran->translatedFormat('j F Y') }}). Periode yang sudah ada dilewati.">

        <form method="POST" action="{{ route('periode.store') }}" class="space-y-5">
            @csrf

            <div class="grid gap-5 sm:grid-cols-3">
                <x-ui.field label="Mulai dari tanggal" name="tgl_mulai" type="date" wajib
                            :value="old('tgl_mulai', now()->toDateString())" />

                <x-ui.field label="Nominal per periode" name="nominal" type="number" inputmode="numeric"
                            min="0" step="500" wajib prefix="Rp"
                            :value="old('nominal', $nominalTerakhir ? (int) $nominalTerakhir : 5000)"
                            bantuan="Nominal melekat pada periode." />

                <x-ui.field label="Sampai tanggal" name="sampai" type="date"
                            :value="old('sampai')"
                            bantuan="Kosongkan untuk sampai akhir tahun ajaran." />
            </div>

            <div class="border-t border-line pt-5">
                <x-ui.button type="submit" size="lg" icon="tambah">Buat periode &amp; tagihannya</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card judul="Daftar periode" :keterangan="$daftarPeriode->count().' periode'" padat>
        @if ($daftarPeriode->isEmpty())
            <x-ui.empty ikon="periode" judul="Belum ada periode">
                Buat periode dulu di atas. Tanpa periode, belum ada tagihan yang bisa dibayar siswa.
            </x-ui.empty>
        @else
            <div class="hidden border-b border-line px-5 py-2 text-xs font-semibold uppercase tracking-wide text-ink-faint sm:grid sm:grid-cols-[1fr_9rem_8rem_7rem_6rem]">
                <span>Periode</span>
                <span>Jatuh tempo</span>
                <span class="text-right">Nominal</span>
                <span class="text-right">Tagihan</span>
                <span class="text-right">Aksi</span>
            </div>

            <ul class="divide-y divide-line">
                @foreach ($daftarPeriode as $periode)
                    <li x-data="{ ubah: false }" class="px-4 py-3 sm:px-5">
                        <div class="grid gap-x-3 gap-y-1.5 sm:grid-cols-[1fr_9rem_8rem_7rem_6rem] sm:items-center">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-ink">{{ $periode->label }}</span>
                                @if ($periode->is_libur)
                                    <x-ui.badge tipe="netral">Libur</x-ui.badge>
                                @elseif ($periode->sudahJatuhTempo())
                                    <x-ui.badge tipe="kurang">Sudah jatuh tempo</x-ui.badge>
                                @endif
                            </span>

                            <span class="text-sm text-ink-soft tabular">
                                {{ $periode->jatuh_tempo->translatedFormat('j M Y') }}
                            </span>

                            <span class="font-semibold tabular sm:text-right">
                                {{ $periode->is_libur ? '—' : Uang::format($periode->nominal) }}
                            </span>

                            <span class="text-sm text-ink-faint tabular sm:text-right">
                                {{ $periode->bills_count }} tagihan
                                @if ($periode->bills_dibayar_count > 0)
                                    <span class="block text-xs text-masuk">{{ $periode->bills_dibayar_count }} sudah dibayar</span>
                                @endif
                            </span>

                            <span class="flex items-center gap-1 sm:justify-end">
                                <button type="button" @click="ubah = !ubah"
                                        :aria-expanded="ubah"
                                        class="inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-brand transition-colors hover:bg-brand-soft">
                                    <x-icon name="ubah" class="size-4" />
                                    <span class="sr-only sm:not-sr-only">Ubah</span>
                                </button>
                            </span>
                        </div>

                        {{-- Panel ubah baru muncul saat dibutuhkan, supaya daftar tetap enak dibaca. --}}
                        <div x-cloak x-show="ubah" x-collapse class="mt-3 rounded-xl bg-surface p-4">
                            <form method="POST" action="{{ route('periode.update', $periode) }}"
                                  class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                @csrf @method('PATCH')

                                <x-ui.field label="Label periode" :name="'label'" :id="'label-'.$periode->id"
                                            :value="$periode->label" wajib />

                                <x-ui.field label="Nominal" :name="'nominal'" :id="'nominal-'.$periode->id"
                                            type="number" inputmode="numeric" min="0" step="500" wajib prefix="Rp"
                                            :value="(int) $periode->nominal"
                                            bantuan="Hanya periode ini yang berubah." />

                                <x-ui.button type="submit">Simpan</x-ui.button>
                            </form>

                            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-line pt-4">
                                <form method="POST" action="{{ route('periode.libur', $periode) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="libur" value="{{ $periode->is_libur ? '0' : '1' }}">
                                    <x-ui.button type="submit" variant="secondary" size="sm">
                                        {{ $periode->is_libur ? 'Tagihkan kembali' : 'Tandai libur' }}
                                    </x-ui.button>
                                </form>

                                <p class="text-sm text-ink-faint">
                                    {{ $periode->is_libur
                                        ? 'Tagihan periode ini akan dibuat ulang untuk siswa yang aktif.'
                                        : 'Periode libur tidak menagih siapa pun. Tagihan yang sudah dibayar menghalangi perubahan ini.' }}
                                </p>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</x-layouts.app>
