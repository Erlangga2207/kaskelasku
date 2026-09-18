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

            // Seluruh angka yang dipegang Alpine dalam satuan RUPIAH BULAT, sama
            // seperti yang diketik bendahara. Konversi ke sen tetap di PHP.
            $sisaRupiah = $tagihan->mapWithKeys(fn ($b) => [$b->id => intdiv($kas->sisaTagihan($b), 100)])->all();

            // Urutan ini persis urutan alokasi otomatis (terlama dulu), jadi
            // tombol "hitung otomatis" di form memberi hasil yang sama dengan server.
            $urutan = $tagihan->pluck('id')->map(fn ($id) => (int) $id)->all();

            $alokasiLama = collect(old('alokasi', []))->filter(fn ($v) => is_numeric($v) && (float) $v > 0);
            $nilaiAwal = $tagihan->mapWithKeys(fn ($b) => [$b->id => (string) ($alokasiLama->get($b->id) ?? '')])->all();
            $pilihAwal = $tagihan->mapWithKeys(fn ($b) => [$b->id => $alokasiLama->has($b->id)])->all();

            $jumlahAwal = (string) old('jumlah', $totalSisa > 0 ? intdiv($totalSisa, 100) : '');

            $bagian = collect([
                ['judul' => 'Iuran kas', 'ikon' => 'periode', 'keterangan' => 'iuran rutin per periode', 'daftar' => $tagihanRutin],
                ['judul' => 'Iuran insidental', 'ikon' => 'dompet', 'keterangan' => 'penggalangan di luar kas rutin', 'daftar' => $tagihanInsidental],
            ])->filter(fn ($b) => $b['daftar']->isNotEmpty());
        @endphp

        <form method="POST" action="{{ route('pembayaran.store') }}" enctype="multipart/form-data" class="space-y-4"
              x-data="{
                  mode: @js(old('mode_alokasi', 'otomatis')),
                  jumlah: @js($jumlahAwal),
                  pilih: @js($pilihAwal),
                  nilai: @js($nilaiAwal),
                  sisa: @js($sisaRupiah),
                  urutan: @js($urutan),

                  angka(n) { return Math.max(0, Math.floor(Number(n) || 0)); },
                  uang(n) { return (n < 0 ? '−Rp ' : 'Rp ') + new Intl.NumberFormat('id-ID').format(Math.abs(n)); },

                  get dibayar() { return this.angka(this.jumlah); },
                  get totalAlokasi() {
                      return this.urutan.reduce((t, id) => t + (this.pilih[id] ? this.angka(this.nilai[id]) : 0), 0);
                  },
                  get belum() { return this.dibayar - this.totalAlokasi; },

                  /* Cerminan aturan server: tagihan terlama ditutup lebih dulu. */
                  hitungOtomatis() {
                      let uang = this.dibayar;
                      for (const id of this.urutan) {
                          const porsi = Math.min(uang, this.sisa[id]);
                          this.pilih[id] = porsi > 0;
                          this.nilai[id] = porsi > 0 ? String(porsi) : '';
                          uang -= porsi > 0 ? porsi : 0;
                      }
                  },
                  keManual() {
                      this.mode = 'manual';
                      if (this.totalAlokasi === 0) this.hitungOtomatis();
                  },
                  /* Mencentang tagihan langsung mengisikan angka yang paling masuk akal. */
                  ganti(id, dicentang) {
                      this.pilih[id] = dicentang;
                      if (! dicentang) { this.nilai[id] = ''; return; }
                      if (this.angka(this.nilai[id]) === 0) {
                          this.nilai[id] = String(Math.min(this.sisa[id], Math.max(0, this.belum)));
                      }
                  },
              }">
            @csrf
            <input type="hidden" name="student_id" value="{{ $siswaTerpilih->id }}">

            <x-ui.card :judul="'2. Uang dari '.$siswaTerpilih->nama">
                <div class="space-y-5">
                    @if ($deposit > 0)
                        <x-ui.alert tipe="info" judul="Sudah ada deposit {{ Uang::format(Uang::keDesimal($deposit)) }}">
                            Uang ini berasal dari kelebihan bayar sebelumnya dan sudah otomatis dipakai
                            untuk tagihan yang muncul kemudian.
                        </x-ui.alert>
                    @endif

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.field label="Jumlah dibayar" name="jumlah" type="number" inputmode="numeric"
                                    min="0" step="500" wajib prefix="Rp"
                                    x-model="jumlah" :value="$jumlahAwal"
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

                    {{-- Bukti transfer jarang dipakai saat menerima tunai di kelas,
                         jadi disembunyikan di balik satu ketukan. --}}
                    <details class="rounded-xl border border-line-strong" @error('bukti') open @enderror>
                        <summary class="flex min-h-11 cursor-pointer items-center gap-2 px-3.5 py-2.5 text-sm font-semibold text-ink">
                            <x-icon name="tambah" class="size-4 shrink-0" />
                            Lampirkan bukti transfer (opsional)
                        </summary>

                        <div class="space-y-1.5 border-t border-line px-3.5 py-3">
                            <label for="bukti" class="sr-only">Bukti transfer</label>
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
                    </details>
                </div>
            </x-ui.card>

            <x-ui.card judul="3. Alokasi ke tagihan" padat>
                <div class="space-y-3 px-4 py-4 sm:px-5">
                    <fieldset class="grid gap-2 sm:grid-cols-2">
                        <legend class="sr-only">Cara alokasi ke tagihan</legend>

                        <label class="flex min-h-11 cursor-pointer items-start gap-2.5 rounded-xl border p-3 transition-colors"
                               :class="mode === 'otomatis' ? 'border-brand bg-brand-soft' : 'border-line-strong'">
                            <input type="radio" name="mode_alokasi" value="otomatis" class="mt-1 size-4"
                                   @checked(old('mode_alokasi', 'otomatis') === 'otomatis')
                                   :checked="mode === 'otomatis'" @change="mode = 'otomatis'">
                            <span>
                                <span class="block font-semibold">Otomatis <span class="text-ink-faint">(disarankan)</span></span>
                                <span class="block text-sm text-ink-soft">Tagihan terlama ditutup lebih dulu.</span>
                            </span>
                        </label>

                        <label class="flex min-h-11 cursor-pointer items-start gap-2.5 rounded-xl border p-3 transition-colors"
                               :class="mode === 'manual' ? 'border-brand bg-brand-soft' : 'border-line-strong'">
                            <input type="radio" name="mode_alokasi" value="manual" class="mt-1 size-4"
                                   @checked(old('mode_alokasi') === 'manual')
                                   :checked="mode === 'manual'" @change="keManual()">
                            <span>
                                <span class="block font-semibold">Atur sendiri</span>
                                <span class="block text-sm text-ink-soft">Pilih tagihan mana yang dibayar dan berapa.</span>
                            </span>
                        </label>
                    </fieldset>

                    @error('alokasi')
                        <x-ui.alert tipe="galat">{{ $message }}</x-ui.alert>
                    @enderror

                    <div x-cloak x-show="mode === 'manual'" class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm text-ink-soft">Centang tagihannya, lalu isi jumlah untuk masing-masing.</p>
                        <x-ui.button type="button" variant="secondary" size="sm" icon="putar" @click="hitungOtomatis()">
                            Hitung ulang otomatis
                        </x-ui.button>
                    </div>
                </div>

                @if ($tagihan->isEmpty())
                    <x-ui.empty ikon="cek" judul="Tidak ada tagihan yang belum lunas">
                        Pembayaran tetap bisa dicatat — uangnya akan tersimpan sebagai deposit
                        dan terpakai otomatis saat periode atau iuran insidental berikutnya dibuat.
                    </x-ui.empty>
                @else
                    @foreach ($bagian as $grup)
                        <div class="flex items-center gap-2 border-y border-line bg-surface px-4 py-2 sm:px-5">
                            <x-icon :name="$grup['ikon']" class="size-4 shrink-0 text-ink-faint" />
                            <h3 class="text-sm font-bold text-ink">{{ $grup['judul'] }}</h3>
                            <span class="truncate text-xs text-ink-faint">{{ $grup['keterangan'] }}</span>
                        </div>

                        <ul class="divide-y divide-line">
                            @foreach ($grup['daftar'] as $bill)
                                @php
                                    $sisa = $kas->sisaTagihan($bill);
                                    $nama = $bill->period?->label ?? ($bill->campaign?->nama ?? 'Iuran insidental');
                                @endphp
                                <li class="px-4 py-2.5 sm:px-5">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                        <label class="flex min-h-11 flex-1 items-center gap-2.5"
                                               :class="mode === 'manual' ? 'cursor-pointer' : ''">
                                            <input type="checkbox" class="size-4 shrink-0" x-cloak x-show="mode === 'manual'"
                                                   :checked="pilih[{{ $bill->id }}]"
                                                   @change="ganti({{ $bill->id }}, $event.target.checked)">
                                            <span class="min-w-0">
                                                <span class="flex flex-wrap items-center gap-1.5">
                                                    <span class="font-semibold">{{ $nama }}</span>
                                                    @if ($kas->statusTagihan($bill) === 'kurang')
                                                        <x-ui.badge tipe="kurang">Kurang bayar</x-ui.badge>
                                                    @endif
                                                    @if ($bill->campaign?->deadline)
                                                        <x-ui.badge>s.d. {{ $bill->campaign->deadline->translatedFormat('j M Y') }}</x-ui.badge>
                                                    @endif
                                                </span>
                                                <span class="block text-sm text-ink-soft tabular">
                                                    sisa {{ Uang::format(Uang::keDesimal($sisa)) }}
                                                </span>
                                            </span>
                                        </label>

                                        <div x-cloak x-show="mode === 'manual'" class="w-full sm:w-44 sm:shrink-0">
                                            <div class="relative">
                                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm text-ink-faint">Rp</span>
                                                <input type="number" name="alokasi[{{ $bill->id }}]"
                                                       x-model="nilai[{{ $bill->id }}]"
                                                       :disabled="! pilih[{{ $bill->id }}]"
                                                       aria-label="Jumlah untuk {{ $nama }}"
                                                       min="0" max="{{ intdiv($sisa, 100) }}" step="500" inputmode="numeric"
                                                       placeholder="0"
                                                       class="block w-full min-h-11 rounded-xl border border-line-strong bg-card px-3.5 py-2.5 pl-11 text-base tabular
                                                              disabled:cursor-not-allowed disabled:bg-surface disabled:text-ink-faint">
                                            </div>
                                            @error('alokasi.'.$bill->id)
                                                <p role="alert" class="mt-1 text-sm font-medium text-keluar">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach

                    <div class="flex items-center justify-between border-t border-line px-4 py-3 text-sm font-semibold sm:px-5">
                        <span>Total belum lunas</span>
                        <span class="tabular">{{ Uang::format(Uang::keDesimal($totalSisa)) }}</span>
                    </div>
                @endif
            </x-ui.card>

            {{-- Menempel di bawah layar: sisa yang belum dialokasikan dan tombol simpan
                 selalu terlihat, jadi bendahara tidak perlu menggulir balik untuk
                 mengecek hitungannya. bottom-20 memberi ruang untuk bottom nav ponsel. --}}
            <div class="sticky bottom-20 z-10 rounded-card border border-line bg-elevated/95 px-4 py-3 shadow-lg backdrop-blur lg:bottom-4 sm:px-5">
                <div x-cloak x-show="mode === 'manual'" class="mb-3 space-y-1.5 border-b border-line pb-3">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-sm font-semibold text-ink">Belum dialokasikan</span>
                        <span class="text-xl font-extrabold tabular"
                              :class="belum < 0 ? 'text-keluar' : (belum === 0 ? 'text-masuk' : 'text-tunggak')"
                              x-text="uang(belum)">Rp 0</span>
                    </div>

                    <p class="flex items-baseline justify-between gap-3 text-xs text-ink-faint">
                        <span>Dibayar <span class="tabular" x-text="uang(dibayar)"></span></span>
                        <span>Dialokasikan <span class="tabular" x-text="uang(totalAlokasi)"></span></span>
                    </p>

                    <p x-cloak x-show="belum < 0" role="alert" class="text-sm font-medium text-keluar">
                        Total alokasi melebihi uang yang diterima. Kurangi salah satu, atau naikkan jumlah dibayar.
                    </p>
                    <p x-cloak x-show="belum > 0" class="text-sm text-ink-soft">
                        Sisa ini disimpan sebagai deposit {{ $siswaTerpilih->nama }} dan otomatis dipakai
                        untuk tagihan berikutnya.
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row-reverse sm:justify-start">
                    <x-ui.button type="submit" size="lg" icon="cek"
                                 ::disabled="mode === 'manual' && belum < 0">Simpan pembayaran</x-ui.button>
                    <x-ui.button :href="route('pembayaran.index')" variant="secondary" size="lg">Batal</x-ui.button>
                </div>
            </div>
        </form>
    @endif
</x-layouts.app>
