<x-layouts.app judul="Admin platform">

    {{--
        Halaman ini sengaja tidak punya satu pun tautan menuju isi kelas.
        Yang dibutuhkan untuk mengelola kapasitas server hanyalah jumlah dan
        tanggal — tidak satu pun dari itu menuntut melihat siapa membayar berapa.
    --}}
    <x-ui.alert tipe="info" judul="Halaman ini hanya menampilkan angka gabungan">
        Tidak ada jalan dari sini menuju detail transaksi kelas mana pun, dan itu batasan yang
        disengaja. Kalau ada bendahara yang butuh bantuan menelusuri datanya, minta dia mengekspor
        CSV kelasnya sendiri.
    </x-ui.alert>

    <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-ui.stat label="Kelas terdaftar" :nilai="$ringkasan['kelas']" ikon="kelas" nada="brand"
                   :keterangan="$ringkasan['kelas_aktif'].' aktif'" />
        <x-ui.stat label="Pengguna" :nilai="$ringkasan['pengguna']" ikon="siswa" />
        <x-ui.stat label="Siswa tercatat" :nilai="$ringkasan['siswa']" ikon="siswa" />
        <x-ui.stat label="Transaksi" :nilai="$ringkasan['transaksi']" ikon="bayar"
                   keterangan="Jumlah baris, bukan nilai rupiah" />
    </div>

    <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-ui.stat label="Nonaktif" :nilai="$ringkasan['kelas_nonaktif']" />
        <x-ui.stat label="Dijadwalkan hapus" :nilai="$ringkasan['kelas_dihapus']" nada="keluar" />
        <x-ui.stat label="Antre daftar tunggu" :nilai="$ringkasan['antrean']" nada="tunggak" />
        <x-ui.stat label="Sisa kuota sistem" :nilai="$kuota['sisa']"
                   :keterangan="$kuota['terpakai'].' / '.$kuota['batas']" />
    </div>

    @if ($kuota['sisa'] <= 10)
        <x-ui.alert tipe="peringatan" class="mt-4">
            Kuota hampir penuh. Setelah {{ $kuota['batas'] }} kelas, form pendaftaran otomatis diganti
            halaman daftar tunggu. Batasnya bisa dinaikkan lewat <code>KASKELAS_BATAS_KELAS_SISTEM</code>
            di <code>.env</code>, tanpa perlu deploy ulang.
        </x-ui.alert>
    @endif

    <x-ui.card judul="Daftar kelas" class="mt-4" padat
               keterangan="Hanya nama, ukuran, dan tanggal aktif terakhir.">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-line bg-surface text-left text-xs uppercase tracking-wide text-ink-faint">
                    <tr>
                        <th scope="col" class="px-4 py-2.5 font-semibold">Kelas</th>
                        <th scope="col" class="px-4 py-2.5 font-semibold">Status</th>
                        <th scope="col" class="px-4 py-2.5 text-right font-semibold">Siswa</th>
                        <th scope="col" class="px-4 py-2.5 font-semibold">Dibuat</th>
                        <th scope="col" class="px-4 py-2.5 font-semibold">Aktivitas terakhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($baris as $item)
                        @php $k = $item['kelas']; @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-ink">
                                    {{ $k->nama_kelas }}
                                    @if ($k->is_demo)
                                        <x-ui.badge tipe="info">Demo</x-ui.badge>
                                    @endif
                                </p>
                                <p class="text-xs text-ink-faint">{{ $k->sekolah }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge :tipe="match ($k->status) {
                                    'aktif' => 'lunas',
                                    'nonaktif' => 'kurang',
                                    default => 'belum',
                                }">{{ $k->status }}</x-ui.badge>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $k->students_count }}</td>
                            <td class="px-4 py-3 text-ink-soft">{{ $k->created_at?->translatedFormat('j M Y') }}</td>
                            <td class="px-4 py-3 text-ink-soft">
                                {{ $item['aktivitas_terakhir']?->translatedFormat('j M Y') ?? 'belum ada transaksi' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>
</x-layouts.app>
