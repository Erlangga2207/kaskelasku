<x-layouts.guest judul="Masukkan daftar siswa">
    <x-ui.card>
        <div class="space-y-5">
            <div>
                <x-wizard.langkah aktif="2" />
                <h1 class="mt-4 text-xl font-bold">Masukkan daftar siswa</h1>
                <p class="mt-1 text-sm text-ink-faint">
                    Kelas <strong class="text-ink">{{ $kelas->nama_kelas }}</strong> sudah dibuat.
                    Sekarang tempel daftar absennya — tidak perlu mengetik satu per satu.
                </p>
            </div>

            <x-ui.flash />

            <x-ui.alert tipe="info" judul="Tempel saja dari daftar absen">
                Salin daftar nama dari WhatsApp, Excel, atau catatan, lalu tempel di kotak di bawah.
                Bentuk <code>1. Budi</code>, <code>1 Budi</code>, atau <code>Budi</code> sama-sama dikenali.
            </x-ui.alert>

            {{-- Mengirim ke endpoint yang sudah ada, bukan ke endpoint wizard
                 tersendiri: satu logika penguraian nama, satu tempat. --}}
            <form method="POST" action="{{ route('siswa.massal.store') }}" class="space-y-4">
                @csrf

                <div class="space-y-1.5">
                    <label for="daftar" class="block text-sm font-semibold text-ink">
                        Daftar nama <span class="text-keluar" aria-hidden="true">*</span>
                    </label>
                    <textarea id="daftar" name="daftar" rows="10" required
                              aria-describedby="daftar-bantuan"
                              placeholder="1. Adinda Rahmawati&#10;2. Bagas Pratama&#10;3. Citra Ayu Lestari"
                              class="block w-full rounded-xl border bg-card px-3.5 py-2.5 text-base text-ink transition-colors focus:outline-2 focus:outline-offset-2 focus:outline-ring-brand {{ $errors->has('daftar') ? 'border-keluar' : 'border-line-strong' }}">{{ old('daftar') }}</textarea>
                    <p id="daftar-bantuan" class="text-sm text-ink-faint">
                        Satu nama per baris. Maksimal {{ $batasSiswa }} siswa per kelas.
                    </p>
                    @error('daftar')
                        <p role="alert" class="text-sm font-medium text-keluar">{{ $message }}</p>
                    @enderror
                </div>

                <x-ui.field label="Mulai ditagih sejak" name="tgl_mulai_aktif" type="date" wajib
                            :value="old('tgl_mulai_aktif', now()->startOfMonth()->toDateString())"
                            bantuan="Tanggal ini menentukan sejak periode mana siswa mulai punya tagihan. Biasanya awal tahun ajaran." />

                <x-ui.button type="submit" size="lg">Simpan siswa &amp; lanjut</x-ui.button>
            </form>
        </div>
    </x-ui.card>
</x-layouts.guest>
