<x-layouts.guest judul="Buat kelas">
    <x-ui.card>
        <div class="space-y-5">
            <div>
                <x-wizard.langkah aktif="1" />
                <h1 class="mt-4 text-xl font-bold">
                    {{ $punyaKelasLain ? 'Buat kelas baru' : 'Buat kelas pertamamu' }}
                </h1>
                <p class="mt-1 text-sm text-ink-faint">
                    Tiga langkah singkat: buat kelas, masukkan daftar siswa, lalu buat periode iuran.
                </p>
            </div>

            <x-ui.flash />

            <form method="POST" action="{{ route('wizard.kelas.store') }}" class="space-y-4" novalidate>
                @csrf

                <x-ui.field label="Nama kelas" name="nama_kelas" wajib autofocus
                            placeholder="XII TRPL 1"
                            bantuan="Tulis seperti yang biasa dipakai di sekolah." />

                <x-ui.field label="Nama sekolah" name="sekolah" wajib
                            placeholder="SMKN 1 Subang" />

                <x-ui.select label="Iuran ditarik setiap" name="tipe_periode" wajib
                             :opsi="['bulanan' => 'Sebulan sekali', 'mingguan' => 'Seminggu sekali']"
                             :value="old('tipe_periode', 'bulanan')"
                             bantuan="Bisa diubah nanti, tapi periode yang sudah dibuat tidak ikut berubah." />

                {{--
                    Persetujuan data diminta DI SINI, bukan saat mendaftar akun.
                    Saat mendaftar, bendahara menyerahkan datanya sendiri; di
                    langkah ini dia mulai memegang data puluhan anak yang tidak
                    pernah ditanya pendapatnya.
                --}}
                <div class="rounded-xl border border-line-strong bg-surface p-4">
                    <p class="text-sm font-semibold text-ink">Soal data siswa</p>
                    <p class="mt-1 text-sm text-ink-soft">
                        Aplikasi ini hanya menyimpan <strong>nama, nomor absen, dan status iuran</strong>.
                        Jangan memasukkan NIS, nomor HP, alamat, atau foto — kolomnya memang tidak ada,
                        dan itu disengaja.
                    </p>

                    <label class="mt-3 flex items-start gap-3">
                        <input type="checkbox" name="persetujuan_data" value="1" @checked(old('persetujuan_data'))
                               class="mt-0.5 size-5 shrink-0 rounded border-line-strong text-brand">
                        <span class="text-sm text-ink-soft">
                            Saya bertanggung jawab atas data siswa yang saya masukkan, dan akan membagikan
                            tautan kelas hanya kepada anggota kelas.
                        </span>
                    </label>
                    @error('persetujuan_data')
                        <p role="alert" class="mt-2 text-sm font-medium text-keluar">{{ $message }}</p>
                    @enderror
                </div>

                <x-ui.button type="submit" size="lg">Buat kelas &amp; lanjut</x-ui.button>
            </form>
        </div>
    </x-ui.card>
</x-layouts.guest>
