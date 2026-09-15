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

            <div class="border-t border-line pt-5">
                <x-ui.button type="submit" size="lg">Simpan pengaturan</x-ui.button>
            </div>
        </form>
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
