<x-layouts.app judul="Beranda">
    <x-ui.card judul="Kelas aktif" keterangan="Semua data di aplikasi ini disaring ke kelas tersebut.">
        <dl class="grid gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-sm text-ink-faint">Nama kelas</dt>
                <dd class="mt-0.5 font-semibold">{{ $kelas->nama_kelas }}</dd>
            </div>
            <div>
                <dt class="text-sm text-ink-faint">Sekolah</dt>
                <dd class="mt-0.5 font-semibold">{{ $kelas->sekolah }}</dd>
            </div>
            <div>
                <dt class="text-sm text-ink-faint">Siswa aktif</dt>
                <dd class="mt-0.5 font-semibold tabular">{{ $jumlahSiswaAktif }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.alert tipe="info" judul="Fase 1 — Fondasi & Tenancy">
        Ringkasan saldo, tunggakan, dan rekap periode dibangun di Fase 4, setelah mesin
        perhitungan uang selesai dan lolos pengujian.
    </x-ui.alert>
</x-layouts.app>
