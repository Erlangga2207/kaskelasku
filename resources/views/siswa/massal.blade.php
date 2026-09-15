<x-layouts.app judul="Tempel daftar nama">
    <x-ui.button :href="route('siswa.index')" variant="ghost" icon="kiri" size="sm">Kembali ke daftar siswa</x-ui.button>

    <x-ui.card judul="Tambah banyak siswa sekaligus"
               keterangan="Salin daftar absen dari grup kelas atau Excel, lalu tempel di kotak ini — satu nama per baris.">

        <form method="POST" action="{{ route('siswa.massal.store') }}" class="space-y-5">
            @csrf

            <div class="space-y-1.5">
                <label for="daftar" class="block text-sm font-semibold text-ink">
                    Daftar nama <span class="text-keluar" aria-hidden="true">*</span>
                    <span class="sr-only">(wajib diisi)</span>
                </label>

                <textarea id="daftar" name="daftar" rows="12" required
                          aria-describedby="daftar-bantuan {{ $errors->has('daftar') ? 'daftar-error' : '' }}"
                          placeholder="1. Adinda Ayu Lestari&#10;2. Bagas Pratama&#10;3. Citra Maharani"
                          class="block w-full rounded-xl border bg-card px-3.5 py-2.5 text-base leading-relaxed
                                 placeholder:text-ink-faint focus:outline-2 focus:outline-offset-2 focus:outline-ring-brand
                                 {{ $errors->has('daftar') ? 'border-keluar' : 'border-line-strong' }}">{{ old('daftar') }}</textarea>

                <p id="daftar-bantuan" class="text-sm text-ink-faint">
                    Nomor di depan nama ikut terbaca sebagai nomor absen. Tanpa nomor pun tidak apa-apa —
                    penomoran dilanjutkan otomatis dari {{ $nomorBerikutnya }}. Nama yang sudah ada dilewati.
                </p>

                @error('daftar')
                    <p id="daftar-error" role="alert" class="flex items-start gap-1.5 text-sm font-medium text-keluar">
                        <x-icon name="peringatan" class="mt-0.5 size-4 shrink-0" />
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            <x-ui.field label="Tanggal mulai aktif untuk semua nama di atas" name="tgl_mulai_aktif" type="date" wajib
                        :value="old('tgl_mulai_aktif', now()->startOfYear()->toDateString())"
                        bantuan="Bisa diubah per siswa setelah tersimpan." />

            <div class="flex flex-col gap-2 border-t border-line pt-5 sm:flex-row-reverse sm:justify-start">
                <x-ui.button type="submit" size="lg">Tambahkan semua</x-ui.button>
                <x-ui.button :href="route('siswa.index')" variant="secondary" size="lg">Batal</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
