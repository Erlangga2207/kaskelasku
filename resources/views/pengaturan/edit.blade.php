<x-layouts.app judul="Pengaturan kelas">
    <x-ui.card judul="Identitas kelas" keterangan="Nama ini yang muncul di halaman kelas yang dibagikan ke anggota.">
        <form method="POST" action="{{ route('pengaturan.update') }}" class="space-y-5">
            @csrf @method('PATCH')

            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Nama kelas" name="nama_kelas" :value="$kelas->nama_kelas" wajib
                            placeholder="XII TRPL 1" />

                <x-ui.field label="Nama sekolah" name="sekolah" :value="$kelas->sekolah" wajib
                            placeholder="SMKN 1 Subang" />
            </div>

            @if ($tipeTerkunci)
                <div class="space-y-1.5">
                    <p class="text-sm font-semibold text-ink">Tipe periode</p>
                    <p class="font-semibold capitalize">{{ $kelas->tipe_periode }}</p>
                    <x-ui.alert tipe="peringatan">
                        Tipe periode tidak bisa diubah lagi karena kelas ini sudah punya periode atau transaksi.
                        Mengubahnya akan membuat tagihan lama tidak bisa dipertanggungjawabkan.
                    </x-ui.alert>
                </div>
            @else
                <x-ui.select label="Tipe periode" name="tipe_periode" wajib
                             :value="$kelas->tipe_periode"
                             :opsi="['bulanan' => 'Bulanan', 'mingguan' => 'Mingguan']"
                             bantuan="Pilih sekarang — setelah ada periode atau transaksi, ini terkunci selamanya." />
            @endif

            <div x-data="{ denda: {{ old('denda_aktif', $kelas->denda_aktif) ? 'true' : 'false' }} }"
                 class="space-y-4 border-t border-line pt-5">
                <div>
                    <h3 class="font-bold">Denda keterlambatan</h3>
                    <p class="mt-0.5 text-sm text-ink-faint">
                        Bawaannya nonaktif. Hidupkan hanya kalau kelasmu memang sudah menyepakatinya —
                        denda yang muncul tiba-tiba lebih sering memicu ribut daripada membuat orang disiplin.
                    </p>
                </div>

                <label class="flex min-h-11 w-fit cursor-pointer items-center gap-2.5 text-sm font-semibold">
                    <input type="checkbox" name="denda_aktif" value="1" x-model="denda"
                           class="size-5 rounded border-line-strong text-brand">
                    Aktifkan denda
                </label>

                <div x-cloak x-show="denda" x-collapse class="space-y-5">
                    <x-ui.select label="Mode denda" name="denda_mode" :value="$kelas->denda_mode"
                                 :opsi="['tetap' => 'Tetap — sekali kena, nominalnya sama', 'harian' => 'Harian — bertambah tiap hari telat']" />

                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-ui.field label="Nominal denda" name="denda_nominal" type="number" inputmode="numeric"
                                    min="0" step="500" prefix="Rp" :value="(int) $kelas->denda_nominal" />

                        <x-ui.field label="Masa tenggang (hari)" name="grace_days" type="number" inputmode="numeric"
                                    min="0" max="365" :value="$kelas->grace_days"
                                    bantuan="Denda baru berjalan setelah lewat hari sekian." />

                        <x-ui.field label="Batas maksimum denda" name="denda_maks" type="number" inputmode="numeric"
                                    min="0" step="500" prefix="Rp"
                                    :value="$kelas->denda_maks === null ? null : (int) $kelas->denda_maks"
                                    bantuan="Kosongkan bila tanpa batas." />
                    </div>
                </div>
            </div>

            <div class="border-t border-line pt-5">
                <x-ui.button type="submit" size="lg">Simpan pengaturan</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card judul="Tautan halaman kelas"
               keterangan="Bagikan tautan ini ke grup kelas. Siapa pun yang punya tautannya bisa melihat rekap kas — tanpa login, dan tanpa bisa mengubah apa pun.">
        @php $tautan = route('publik.kelas', $kelas->public_token); @endphp

        <div x-data="{ tersalin: false }" class="space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row">
                <label for="tautan-kelas" class="sr-only">Tautan halaman kelas</label>
                <input type="text" id="tautan-kelas" value="{{ $tautan }}" readonly
                       onfocus="this.select()"
                       class="min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink-soft">

                <x-ui.button type="button" variant="secondary" class="shrink-0"
                             x-on:click="navigator.clipboard.writeText('{{ $tautan }}').then(() => {
                                 tersalin = true;
                                 setTimeout(() => tersalin = false, 2500);
                             })">
                    <x-icon name="salin" class="size-4" />
                    <span x-text="tersalin ? 'Tersalin' : 'Salin'">Salin</span>
                </x-ui.button>

                <x-ui.button :href="$tautan" target="_blank" rel="noopener" variant="secondary" class="shrink-0">
                    <x-icon name="mata" class="size-4" />
                    Lihat
                </x-ui.button>
            </div>

            <div aria-live="polite" class="sr-only" x-text="tersalin ? 'Tautan disalin ke papan klip.' : ''"></div>

            <div class="rounded-xl bg-surface p-4">
                <p class="text-sm text-ink-soft">
                    Kalau tautannya telanjur tersebar ke luar kelas, ganti tokennya. Tautan lama langsung mati
                    dan semua orang perlu tautan yang baru.
                    @if ($kelas->token_rotated_at)
                        <span class="mt-1 block text-xs text-ink-faint">
                            Terakhir diganti {{ $kelas->token_rotated_at->translatedFormat('j M Y, H:i') }}.
                        </span>
                    @endif
                </p>

                <div class="mt-3">
                    <x-ui.confirm
                        :action="route('pengaturan.token')"
                        method="PATCH"
                        judul="Ganti tautan halaman kelas?"
                        pesan="Tautan lama langsung tidak berlaku. Kamu perlu membagikan tautan baru ke grup kelas."
                        tombol="Ganti tautan"
                        variant="danger">
                        <x-icon name="putar" class="size-4" />
                        Ganti tautan kelas
                    </x-ui.confirm>
                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- ================= Template pengingat (v1.1) ================= --}}
    <x-ui.card judul="Template pengingat" id="template-pengingat"
               keterangan="Kata-kata yang dipakai saat menagih tunggakan. Milik kelas ini saja — kelas lain punya templatenya sendiri.">
        <form method="POST" action="{{ route('pengaturan.pengingat') }}" class="space-y-4"
              x-data="{
                  teks: @js(old('template_pengingat', $templatePengingat)),
                  bawaan: @js($templateBawaan),
                  contoh: @js($contohPengingat),

                  pratinjau() {
                      return this.teks
                          .replaceAll('{nama}', this.contoh.nama)
                          .replaceAll('{rincian}', this.contoh.rincian)
                          .replaceAll('{total}', this.contoh.total)
                          .replaceAll('{batas}', this.contoh.batas);
                  },
              }">
            @csrf @method('PATCH')

            <div class="space-y-1.5">
                <label for="template_pengingat" class="block text-sm font-semibold text-ink">
                    Teks pengingat
                    <span class="text-keluar" aria-hidden="true">*</span>
                    <span class="sr-only">(wajib diisi)</span>
                </label>

                <textarea id="template_pengingat" name="template_pengingat" rows="10" required
                          x-model="teks"
                          aria-describedby="template-bantuan"
                          class="block w-full rounded-xl border bg-card px-3.5 py-2.5 font-mono text-sm leading-relaxed text-ink
                                 focus:outline-2 focus:outline-offset-2 focus:outline-ring-brand
                                 {{ $errors->has('template_pengingat') ? 'border-keluar' : 'border-line-strong' }}">{{ old('template_pengingat', $templatePengingat) }}</textarea>

                <div id="template-bantuan" class="space-y-1.5 text-sm text-ink-faint">
                    <p>Bagian di dalam kurung kurawal diganti otomatis saat teks dibuat:</p>
                    <ul class="grid gap-1 sm:grid-cols-2">
                        <li><code class="font-mono font-semibold text-ink-soft">&#123;nama&#125;</code> — nama siswa</li>
                        <li><code class="font-mono font-semibold text-ink-soft">&#123;rincian&#125;</code> — daftar tagihan yang belum lunas</li>
                        <li><code class="font-mono font-semibold text-ink-soft">&#123;total&#125;</code> — jumlah seluruh tunggakan</li>
                        <li><code class="font-mono font-semibold text-ink-soft">&#123;batas&#125;</code> — tanggal batas pembayaran</li>
                    </ul>
                </div>

                @error('template_pengingat')
                    <p role="alert" class="flex items-start gap-1.5 text-sm font-medium text-keluar">
                        <x-icon name="peringatan" class="mt-0.5 size-4 shrink-0" />
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            {{-- Pratinjau memakai angka contoh, bukan data siswa sungguhan: halaman
                 ini dibuka untuk mengatur kata-kata, bukan untuk melihat tunggakan. --}}
            <div class="space-y-1.5">
                <p class="text-sm font-semibold text-ink">Pratinjau dengan angka contoh</p>
                <pre class="overflow-x-auto whitespace-pre-wrap break-words rounded-xl bg-surface p-3.5 font-sans text-sm leading-relaxed text-ink-soft"
                     x-text="pratinjau()">{{ $templatePengingat }}</pre>
            </div>

            <div class="flex flex-col gap-2 border-t border-line pt-4 sm:flex-row sm:items-center">
                <x-ui.button type="submit" size="lg">Simpan template</x-ui.button>
                <x-ui.button type="button" variant="ghost" icon="putar" @click="teks = bawaan">
                    Kembalikan ke teks bawaan
                </x-ui.button>
            </div>
        </form>

        <p class="mt-4 rounded-xl bg-surface p-3.5 text-sm text-ink-soft">
            Teks jadinya bisa dilihat dan disalin di
            <a href="{{ route('pengingat.index') }}"
               class="font-semibold text-brand underline-offset-2 hover:underline">halaman Pengingat</a>.
            Aplikasi tidak pernah mengirimnya sendiri — kamu yang menyalin dan mengirim.
        </p>
    </x-ui.card>

    {{-- ================= QRIS kelas (v1.1) ================= --}}
    <x-ui.card judul="QRIS kelas" id="qris"
               keterangan="Supaya anggota kelas bisa membayar lewat transfer tanpa bertanya nomor rekening.">
        <div class="space-y-5">
            {{-- Peringatan ini bukan formalitas: halaman kelas hanya dilindungi token,
                 dan token bisa tersebar keluar kelas begitu ada yang meneruskan tautannya. --}}
            <x-ui.alert tipe="peringatan" judul="Gambar ini akan terlihat siapa pun yang punya tautan kelas">
                Halaman kelas tidak butuh login — cukup tautannya. Kalau tautan itu diteruskan
                ke luar kelas, QRIS-mu ikut terlihat. Pakai QRIS yang memang kamu siap sebarkan,
                dan jangan mengunggah tangkapan layar yang memuat saldo atau data pribadimu.
            </x-ui.alert>

            @if ($kelas->punyaQris())
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <img src="{{ route('publik.qris', $kelas->public_token) }}"
                         alt="QRIS kelas {{ $kelas->nama_kelas }}"
                         class="w-40 shrink-0 self-start rounded-xl border border-line bg-white p-2">

                    <div class="min-w-0 flex-1 space-y-2">
                        <div>
                            <p class="text-sm text-ink-faint">Atas nama</p>
                            <p class="font-semibold">{{ $kelas->qris_nama_pemilik }}</p>
                        </div>
                        <p class="text-sm text-ink-soft">
                            Sudah tampil di halaman kelas. Konfirmasi bahwa uangnya masuk tetap kamu
                            lakukan sendiri lewat menu Bayar — aplikasi tidak tahu apa pun soal
                            transaksi di QRIS ini.
                        </p>
                        <x-ui.confirm
                            :action="route('pengaturan.qris.hapus')"
                            method="DELETE"
                            judul="Hapus QRIS kelas?"
                            pesan="Gambarnya dihapus dari server dan halaman kelas tidak lagi menampilkannya."
                            tombol="Hapus QRIS"
                            variant="danger">
                            <x-icon name="hapus" class="size-4" />
                            Hapus QRIS
                        </x-ui.confirm>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('pengaturan.qris') }}" enctype="multipart/form-data"
                  class="space-y-5 border-t border-line pt-5">
                @csrf

                <x-ui.field label="Nama pemilik QRIS" name="qris_nama_pemilik" wajib
                            :value="$kelas->qris_nama_pemilik"
                            placeholder="mis. Erlangga H. (bendahara)"
                            bantuan="Ditampilkan di bawah gambar, supaya anggota kelas yakin tidak salah tujuan." />

                <div class="space-y-1.5">
                    <label for="qris" class="block text-sm font-semibold text-ink">
                        Gambar QRIS
                        @unless ($kelas->punyaQris())
                            <span class="text-keluar" aria-hidden="true">*</span>
                            <span class="sr-only">(wajib diisi)</span>
                        @endunless
                    </label>

                    <input type="file" id="qris" name="qris" accept="image/jpeg,image/png"
                           @unless ($kelas->punyaQris()) required @endunless
                           aria-describedby="qris-bantuan"
                           class="block w-full cursor-pointer rounded-xl border border-line-strong bg-card p-2.5 text-sm
                                  file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-brand-soft
                                  file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-soft-ink">

                    <p id="qris-bantuan" class="text-sm text-ink-faint">
                        JPG atau PNG, maksimal 2 MB.
                        @if ($kelas->punyaQris())
                            Kosongkan kalau hanya ingin mengubah nama pemiliknya.
                        @endif
                    </p>

                    @error('qris')
                        <p role="alert" class="flex items-start gap-1.5 text-sm font-medium text-keluar">
                            <x-icon name="peringatan" class="mt-0.5 size-4 shrink-0" />
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                <x-ui.button type="submit" size="lg">
                    {{ $kelas->punyaQris() ? 'Perbarui QRIS' : 'Simpan QRIS' }}
                </x-ui.button>
            </form>
        </div>
    </x-ui.card>


    <x-ui.card judul="Data yang disimpan aplikasi ini">
        <ul class="space-y-2 text-sm text-ink-soft">
            <li class="flex gap-2">
                <x-icon name="cek" class="mt-0.5 size-4 shrink-0 text-masuk" />
                Nama siswa, nomor absen, dan status aktif.
            </li>
            <li class="flex gap-2">
                <x-icon name="cek" class="mt-0.5 size-4 shrink-0 text-masuk" />
                Catatan pembayaran dan pengeluaran kas kelas.
            </li>
            <li class="flex gap-2">
                <x-icon name="tutup" class="mt-0.5 size-4 shrink-0 text-keluar" />
                Tidak menyimpan NIS, NISN, nomor HP, alamat, maupun foto siswa — dan tidak akan pernah.
            </li>
        </ul>

        <div class="mt-4 rounded-xl border border-line-strong bg-surface p-4">
            <p class="text-sm font-semibold text-ink">Bawa datamu pulang kapan saja</p>
            <p class="mt-1 text-sm text-ink-soft">
                Seluruh data kelas bisa diunduh sebagai CSV, tanpa minta izin dan tanpa menunggu.
            </p>
            <div class="mt-3">
                <x-ui.button :href="route('ekspor.index')" variant="secondary" size="sm" icon="unduh">
                    Buka halaman Ekspor
                </x-ui.button>
            </div>
        </div>
    </x-ui.card>

    {{-- ================= Hapus kelas ================= --}}
    @if ($kelas->owner_id === auth()->id())
        <x-ui.card judul="Hapus kelas" id="hapus-kelas"
                   keterangan="Aksi yang paling sulit ditarik kembali di aplikasi ini.">

            <x-ui.alert tipe="galat" judul="Yang hilang bukan satu baris, tapi seluruh catatan kas kelas">
                Menghapus kelas berarti menghapus semua siswa, tagihan, pembayaran, pengeluaran, dan
                audit log-nya. Kelas akan masuk masa tenggang
                <strong>{{ config('kaskelas.daur.tenggang_hapus_hari') }} hari</strong> — selama itu masih
                bisa dipulihkan. Setelah lewat, datanya hilang permanen dan tidak bisa dikembalikan
                oleh siapa pun.
            </x-ui.alert>

            <p class="mt-4 text-sm text-ink-soft">
                Sebelum menghapus,
                <a href="{{ route('ekspor.index') }}"
                   class="font-semibold text-brand underline-offset-2 hover:underline">unduh dulu data kelasmu</a>.
                Kalau yang kamu mau sebenarnya cuma berhenti jadi bendahara, pakai
                <a href="{{ route('tutup-buku.index') }}#alih-kepemilikan"
                   class="font-semibold text-brand underline-offset-2 hover:underline">Alih kepemilikan</a>
                — kelasnya tetap hidup untuk bendahara berikutnya.
            </p>

            {{-- Mengetik nama kelas memaksa berhenti sejenak dan membaca ulang
                 kelas MANA yang sedang dihapus: satu akun bisa memegang lima. --}}
            <form method="POST" action="{{ route('kelas.destroy') }}" class="mt-4 space-y-3">
                @csrf
                @method('DELETE')

                <x-ui.field label="Ketik nama kelas untuk mengonfirmasi" name="konfirmasi_nama"
                            :placeholder="$kelas->nama_kelas"
                            bantuan="Harus sama persis dengan nama kelas ini." />

                <x-ui.button type="submit" variant="danger">Hapus kelas ini</x-ui.button>
            </form>
        </x-ui.card>
    @endif
</x-layouts.app>
