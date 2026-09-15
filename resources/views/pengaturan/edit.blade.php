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
    </x-ui.card>
</x-layouts.app>
