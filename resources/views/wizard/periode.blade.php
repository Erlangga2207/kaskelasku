<x-layouts.guest judul="Buat periode iuran">
    <x-ui.card>
        <div class="space-y-5">
            <div>
                <x-wizard.langkah aktif="3" />
                <h1 class="mt-4 text-xl font-bold">Terakhir: buat periode iuran</h1>
                <p class="mt-1 text-sm text-ink-faint">
                    {{ $jumlahSiswa }} siswa sudah masuk. Langkah ini yang menerbitkan tagihannya.
                </p>
            </div>

            <x-ui.flash />

            {{--
                Peringatan ini yang paling penting di seluruh wizard. Melewati
                langkah ini tidak memunculkan galat apa pun — hanya laporan yang
                diam-diam nol, berminggu-minggu.
            --}}
            <x-ui.alert tipe="peringatan" judul="Jangan lewati langkah ini">
                Tanpa periode, <strong>tidak ada tagihan yang terbentuk</strong>. Pembayaran yang kamu
                catat nanti akan mengendap sebagai deposit: uangnya tercatat masuk, tapi rekap per
                periode dan daftar tunggakan tetap menunjukkan nol — tanpa pesan galat apa pun.
            </x-ui.alert>

            <form method="POST" action="{{ route('periode.store') }}" class="space-y-4" novalidate>
                @csrf

                <x-ui.field label="Iuran mulai ditarik sejak" name="tgl_mulai" type="date" wajib
                            :value="old('tgl_mulai', now()->startOfMonth()->toDateString())"
                            bantuan="Biasanya awal tahun ajaran, atau awal bulan ini kalau kas kelas baru dimulai." />

                <x-ui.field label="Nominal per siswa" name="nominal" type="number" wajib
                            prefix="Rp" min="0" step="500"
                            :value="old('nominal')"
                            bantuan="Nominal per periode. Kalau suatu bulan berbeda, bisa diubah per periode nanti." />

                <x-ui.field label="Sampai tanggal" name="sampai" type="date"
                            :value="old('sampai', $akhirTahunAjaran->toDateString())"
                            bantuan="Bawaannya sampai akhir tahun ajaran. Periode bisa ditambah lagi kapan saja." />

                <x-ui.button type="submit" size="lg">Buat periode &amp; selesai</x-ui.button>
            </form>
        </div>
    </x-ui.card>
</x-layouts.guest>
