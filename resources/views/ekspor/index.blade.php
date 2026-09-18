@php
    $label = [
        'siswa' => ['Daftar siswa', 'Nama, nomor absen, tanggal aktif, dan status.'],
        'periode' => ['Periode iuran', 'Label periode, tanggal, jatuh tempo, dan nominalnya.'],
        'tagihan' => ['Tagihan', 'Setiap tagihan per siswa per periode, termasuk yang dibebaskan.'],
        'pembayaran' => ['Pembayaran', 'Semua uang masuk: tanggal, siswa, jumlah, dan metodenya.'],
        'alokasi' => ['Alokasi pembayaran', 'Rincian uang mana dipakai untuk melunasi tagihan mana.'],
        'pengeluaran' => ['Pengeluaran', 'Semua uang keluar beserta kategori dan keterangannya.'],
        'campaign' => ['Iuran insidental', 'Daftar campaign, nominal per siswa, dan statusnya.'],
        'tutup-buku' => ['Tutup buku', 'Snapshot saldo tiap periode yang sudah ditutup.'],
    ];
@endphp

<x-layouts.app judul="Ekspor data">

    {{--
        Nada halaman ini sengaja mendorong, bukan sekadar menyediakan. Syarat
        Layanan menyebut layanan ini "apa adanya"; janji seperti itu baru jujur
        kalau penggunanya benar-benar didorong menyimpan salinannya sendiri.
    --}}
    <x-ui.alert tipe="peringatan" judul="Simpan salinannya sendiri, minimal sebulan sekali">
        Uang kas kelas itu uang sungguhan milik puluhan orang, dan catatannya tidak boleh hanya ada
        di satu tempat — termasuk kalau tempat itu aplikasi ini. Unduh CSV-nya, simpan di Google Drive
        atau laptop. Wajib dilakukan sebelum tutup buku dan sebelum serah terima bendahara.
    </x-ui.alert>

    <x-ui.card judul="Unduh data {{ $kelas->nama_kelas }}" class="mt-4" padat
               keterangan="Berkas CSV, bisa langsung dibuka di Excel atau Google Sheets.">
        <ul class="divide-y divide-line">
            @foreach ($jenis as $j)
                @php [$judul, $keterangan] = $label[$j]; @endphp
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-5">
                    <div class="min-w-0">
                        <p class="font-semibold text-ink">
                            {{ $judul }}
                            <span class="ml-1 text-sm font-normal text-ink-faint">({{ $jumlah[$j] }} baris)</span>
                        </p>
                        <p class="mt-0.5 text-sm text-ink-faint">{{ $keterangan }}</p>
                    </div>

                    @if ($jumlah[$j] > 0)
                        <x-ui.button :href="route('ekspor.unduh', $j)" variant="secondary" size="sm" icon="unduh">
                            Unduh CSV
                        </x-ui.button>
                    @else
                        <span class="text-sm text-ink-faint">Belum ada isinya</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-ui.card>

    <x-ui.card judul="Catatan" class="mt-4">
        <ul class="list-disc space-y-1.5 pl-5 text-sm text-ink-soft">
            <li>Berkas CSV dibuat dengan penanda UTF-8 supaya nama berhuruf khusus tidak berantakan di Excel.</li>
            <li>Bukti transfer dan foto struk <strong>tidak ikut</strong> dalam CSV — kalau kamu membutuhkannya, unduh satu per satu dari halaman transaksi.</li>
            <li>Data yang sudah dihapus (soft delete) tidak ikut diekspor.</li>
        </ul>
    </x-ui.card>
</x-layouts.app>
