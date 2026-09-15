@php use App\Support\Uang; @endphp

<x-layouts.app judul="Catat pembayaran">
    <x-ui.button :href="route('pembayaran.index')" variant="ghost" icon="kiri" size="sm">Kembali ke riwayat pembayaran</x-ui.button>

    {{-- Langkah 1 terpisah dari form utama: memilih siswa memuat ulang halaman
         supaya daftar tagihannya ikut tampil, tanpa perlu JavaScript apa pun. --}}
    <x-ui.card judul="1. Pilih siswa">
        <form method="GET" action="{{ route('pembayaran.create') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-56 flex-1">
                <x-ui.select label="Siswa" name="siswa" wajib kosong="— pilih siswa —"
                             :value="$siswaTerpilih?->id"
                             :opsi="$daftarSiswa->mapWithKeys(fn ($s) => [
                                 $s->id => ($s->no_absen ? $s->no_absen.'. ' : '').$s->nama,
                             ])->all()"
                             onchange="this.form.submit()" />
            </div>
            <x-ui.button type="submit" variant="secondary">Tampilkan tagihan</x-ui.button>
        </form>
    </x-ui.card>

    @if (! $siswaTerpilih)
        <x-ui.card>
            <x-ui.empty ikon="bayar" judul="Pilih siswa dulu">
                Setelah siswa dipilih, tagihannya yang belum lunas akan muncul di sini beserta sisa yang harus dibayar.
            </x-ui.empty>
        </x-ui.card>
    @else
        @php
            $totalSisa = $tagihan->sum(fn ($b) => $kas->sisaTagihan($b));
        @endphp

        <form method="POST" action="{{ route('pembayaran.store') }}" enctype="multipart/form-data"
              x-data="{ mode: '{{ old('mode_alokasi', 'otomatis') }}' }" class="space-y-4">
            @csrf
            <input type="hidden" name="student_id" value="{{ $siswaTerpilih->id }}">

            <x-ui.card :judul="'2. Tagihan '.$siswaTerpilih->nama" padat>
                @if ($deposit > 0)
                    <div class="px-4 pt-4 sm:px-5">
                        <x-ui.alert tipe="info" judul="Ada deposit {{ Uang::format(Uang::keDesimal($deposit)) }}">
                            Uang ini berasal dari kelebihan bayar sebelumnya dan sudah otomatis dipakai
                            untuk tagihan yang muncul kemudian.
                        </x-ui.alert>
                    </div>
                @endif

                @if ($tagihan->isEmpty())
                    <x-ui.empty ikon="cek" judul="Tidak ada tagihan yang belum lunas">
                        Pembayaran tetap bisa dicatat — uangnya akan tersimpan sebagai deposit
                        dan terpakai otomatis saat periode berikutnya dibuat.
                    </x-ui.empty>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($tagihan as $bill)
                            @php $sisa = $kas->sisaTagihan($bill); @endphp
                            <li class="grid gap-x-3 gap-y-1 px-4 py-3 sm:grid-cols-[1fr_8rem_10rem] sm:items-center sm:px-5">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold">{{ $bill->period?->label ?? 'Iuran insidental' }}</span>
                                    @if ($kas->statusTagihan($bill) === 'kurang')
                                        <x-ui.badge tipe="kurang">Kurang bayar</x-ui.badge>
                                    @endif
                                </span>

                                <span class="text-sm text-ink-soft tabular sm:text-right">
                                    sisa {{ Uang::format(Uang::keDesimal($sisa)) }}
                                </span>

                                <span x-cloak x-show="mode === 'manual'">
                                    <label class="sr-only" for="alokasi-{{ $bill->id }}">
                                        Jumlah untuk {{ $bill->period?->label }}
                                    </label>
                                    <input type="number" id="alokasi-{{ $bill->id }}"
                                           name="alokasi[{{ $bill->id }}]"
                                           value="{{ old('alokasi.'.$bill->id) }}"
                                           min="0" max="{{ intdiv($sisa, 100) }}" step="500" inputmode="numeric"
                                           placeholder="0"
                                           class="block w-full min-h-11 rounded-xl border border-line-strong bg-card px-3 py-2 text-base tabular">
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="flex items-center justify-between border-t border-line px-4 py-3 text-sm font-semibold sm:px-5">
                        <span>Total belum lunas</span>
                        <span class="tabular">{{ Uang::format(Uang::keDesimal($totalSisa)) }}</span>
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card judul="3. Rincian pembayaran">
                <div class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.field label="Jumlah dibayar" name="jumlah" type="number" inputmode="numeric"
                                    min="0" step="500" wajib prefix="Rp"
                                    :value="old('jumlah', $totalSisa > 0 ? intdiv($totalSisa, 100) : null)"
                                    bantuan="Boleh lebih besar dari total tagihan — sisanya jadi deposit." />

                        <x-ui.field label="Tanggal" name="tanggal" type="date" wajib
                                    :value="old('tanggal', now()->toDateString())"
                                    :max="now()->toDateString()" />
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.select label="Metode" name="metode" wajib :value="old('metode', 'tunai')"
                                     :opsi="['tunai' => 'Tunai', 'transfer' => 'Transfer']" />

                        <x-ui.field label="Catatan" name="catatan" :value="old('catatan')"
                                    placeholder="mis. titip lewat ketua kelas" />
                    </div>

                    <fieldset class="space-y-2">
                        <legend class="text-sm font-semibold text-ink">Cara alokasi ke tagihan</legend>

                        <label class="flex min-h-11 cursor-pointer items-start gap-2.5 rounded-xl border border-line-strong p-3 transition-colors"
                               :class="mode === 'otomatis' ? 'border-brand bg-brand-soft' : ''">
                            <input type="radio" name="mode_alokasi" value="otomatis" x-model="mode" class="mt-1 size-4">
                            <span>
                                <span class="block font-semibold">Otomatis <span class="text-ink-faint">(disarankan)</span></span>
                                <span class="block text-sm text-ink-soft">
                                    Uang menutup tagihan terlama dulu, lalu berlanjut ke tagihan berikutnya.
                                </span>
                            </span>
                        </label>

                        <label class="flex min-h-11 cursor-pointer items-start gap-2.5 rounded-xl border border-line-strong p-3 transition-colors"
                               :class="mode === 'manual' ? 'border-brand bg-brand-soft' : ''">
                            <input type="radio" name="mode_alokasi" value="manual" x-model="mode" class="mt-1 size-4">
                            <span>
                                <span class="block font-semibold">Atur sendiri</span>
                                <span class="block text-sm text-ink-soft">
                                    Isi jumlah per tagihan di daftar nomor 2 di atas.
                                </span>
                            </span>
                        </label>
                    </fieldset>

                    <div class="space-y-1.5">
                        <label for="bukti" class="block text-sm font-semibold text-ink">Bukti transfer (opsional)</label>
                        <input type="file" id="bukti" name="bukti" accept="image/jpeg,image/png,application/pdf"
                               aria-describedby="bukti-bantuan"
                               class="block w-full cursor-pointer rounded-xl border border-line-strong bg-card p-2.5 text-sm
                                      file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-brand-soft
                                      file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-soft-ink">
                        <p id="bukti-bantuan" class="text-sm text-ink-faint">
                            JPG, PNG, atau PDF, maksimal 2 MB. Bukti disimpan privat dan tidak pernah tampil
                            di halaman kelas.
                        </p>
                        @error('bukti')
                            <p role="alert" class="text-sm font-medium text-keluar">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-2 border-t border-line pt-5 sm:flex-row-reverse sm:justify-start">
                        <x-ui.button type="submit" size="lg" icon="cek">Simpan pembayaran</x-ui.button>
                        <x-ui.button :href="route('pembayaran.index')" variant="secondary" size="lg">Batal</x-ui.button>
                    </div>
                </div>
            </x-ui.card>
        </form>
    @endif
</x-layouts.app>
