@php use App\Support\Uang; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Serah Terima Kas {{ $kelas->nama_kelas }}</title>
    {{--
        Gaya ditulis lawas dan terpisah dari Tailwind: dompdf tidak mengenal CSS
        modern. Struktur ini sengaja dibuat mirip laporan/pdf.blade.php supaya
        kedua berkas terasa satu keluarga saat dicetak berdampingan.
    --}}
    <style>
        @page { margin: 22mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; line-height: 1.5; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        h2 { font-size: 11px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #d1d5db; }
        .keterangan { color: #6b7280; font-size: 9px; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: .03em; }
        .kanan { text-align: right; }
        .masuk { color: #047857; }
        .keluar { color: #b91c1c; }
        .ringkas td { border: none; padding: 3px 0; }
        .nilai { font-size: 13px; font-weight: bold; }
        .catatan { margin-top: 6px; padding: 6px 8px; background: #f9fafb; border: 1px solid #e5e7eb; font-size: 9px; }
        .kaki { margin-top: 18px; font-size: 9px; color: #6b7280; }
        .ttd { margin-top: 26px; width: 100%; }
        .ttd td { border: none; vertical-align: top; text-align: center; font-size: 9px; width: 33%; }
        .garis-ttd { margin-top: 52px; border-top: 1px solid #9ca3af; padding-top: 3px; }
    </style>
</head>
<body>
    <h1>Berita Acara Serah Terima Kas Kelas</h1>
    <p class="keterangan">
        {{ $kelas->nama_kelas }} · {{ $kelas->sekolah }} ·
        Periode <strong>{{ $closing->label }}</strong> ({{ $closing->rentangTeks() }}) ·
        Dicetak {{ $dicetakPada->translatedFormat('j F Y, H:i') }} WIB
    </p>

    {{--
        Angka di bawah diambil dari snapshot yang disimpan saat tutup buku,
        BUKAN dihitung ulang saat PDF dicetak. Kalau dihitung ulang, dokumen yang
        sudah ditandatangani bisa mencetak angka berbeda setiap kali dibuka.
    --}}
    <h2>Ringkasan periode (angka saat buku ditutup)</h2>
    <table class="ringkas">
        <tr>
            <td>Saldo awal periode</td>
            <td class="kanan nilai">{{ Uang::format($closing->saldo_awal) }}</td>
        </tr>
        <tr>
            <td>Total uang masuk</td>
            <td class="kanan masuk nilai">{{ Uang::format($closing->total_masuk) }}</td>
        </tr>
        <tr>
            <td>Total uang keluar</td>
            <td class="kanan keluar nilai">{{ Uang::format($closing->total_keluar) }}</td>
        </tr>
        <tr>
            <td><strong>Saldo akhir yang diserahterimakan</strong></td>
            <td class="kanan nilai">{{ Uang::format($closing->saldo_akhir) }}</td>
        </tr>
    </table>

    @if ($closing->catatan)
        <div class="catatan"><strong>Catatan bendahara:</strong> {{ $closing->catatan }}</div>
    @endif

    <h2>Rincian pengeluaran per kategori</h2>
    @if ($kategori->isEmpty())
        <p class="keterangan">Tidak ada pengeluaran yang tercatat pada periode ini.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th class="kanan">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kategori as $baris)
                    <tr>
                        <td>{{ $baris['nama'] }}</td>
                        <td class="kanan keluar">{{ Uang::format(Uang::keDesimal($baris['jumlah'])) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td><strong>Total</strong></td>
                    <td class="kanan"><strong>{{ Uang::format(Uang::keDesimal($kategori->sum('jumlah'))) }}</strong></td>
                </tr>
            </tbody>
        </table>
    @endif

    {{--
        Tunggakan dicetak apa adanya per hari cetak, bukan per tanggal tutup buku:
        yang diwariskan ke bendahara baru adalah piutang yang MASIH terbuka hari
        ini, bukan foto piutang beberapa bulan lalu.
    --}}
    <h2>Tunggakan yang masih terbuka (per {{ $dicetakPada->translatedFormat('j F Y') }})</h2>
    @php $penunggak = $tunggakan->filter(fn ($b) => $b['tunggakan'] > 0)->values(); @endphp

    @if ($penunggak->isEmpty())
        <p class="keterangan">Tidak ada tunggakan. Seluruh tagihan yang sudah jatuh tempo sudah lunas.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Nama siswa</th>
                    <th class="kanan">Tagihan belum lunas</th>
                    <th class="kanan">Jumlah tunggakan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($penunggak as $baris)
                    <tr>
                        <td>{{ $baris['siswa']->nama }}</td>
                        <td class="kanan">{{ $baris['belum_lunas'] }}</td>
                        <td class="kanan keluar">{{ Uang::format(Uang::keDesimal($baris['tunggakan'])) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td><strong>Total ({{ $penunggak->count() }} siswa)</strong></td>
                    <td></td>
                    <td class="kanan"><strong>{{ Uang::format(Uang::keDesimal($penunggak->sum('tunggakan'))) }}</strong></td>
                </tr>
            </tbody>
        </table>
    @endif

    <p class="kaki">
        Dengan ditandatanganinya berita acara ini, saldo kas sebesar
        <strong>{{ Uang::format($closing->saldo_akhir) }}</strong> beserta seluruh catatan
        transaksinya dinyatakan telah diserahkan dan diterima dalam keadaan sesuai.
    </p>

    <table class="ttd">
        <tr>
            <td>
                Bendahara Lama
                <div class="garis-ttd">( ......................................... )</div>
            </td>
            <td>
                Bendahara Baru
                <div class="garis-ttd">( ......................................... )</div>
            </td>
            <td>
                Ketua Kelas
                <div class="garis-ttd">( ......................................... )</div>
            </td>
        </tr>
    </table>
</body>
</html>
