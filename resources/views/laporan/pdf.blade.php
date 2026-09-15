@php use App\Support\Uang; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Kas {{ $kelas->nama_kelas }}</title>
    {{--
        PDF dirender dompdf, yang hanya mengenal CSS lawas — jadi gaya di sini
        sengaja ditulis sederhana dan terpisah dari Tailwind milik aplikasi.
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
        .kaki { margin-top: 24px; font-size: 9px; color: #6b7280; }
        .ttd { margin-top: 30px; width: 100%; }
        .ttd td { border: none; height: 60px; vertical-align: top; text-align: center; font-size: 9px; }
    </style>
</head>
<body>
    <h1>Laporan Kas Kelas {{ $kelas->nama_kelas }}</h1>
    <p class="keterangan">
        {{ $kelas->sekolah }} ·
        @if ($dari || $sampai)
            Rentang {{ $dari ? \Carbon\Carbon::parse($dari)->translatedFormat('j M Y') : 'awal' }}
            s.d. {{ $sampai ? \Carbon\Carbon::parse($sampai)->translatedFormat('j M Y') : 'sekarang' }}
        @else
            Seluruh periode
        @endif
        · Dicetak {{ $dicetakPada->translatedFormat('j F Y, H:i') }} WIB
    </p>

    <h2>Ringkasan</h2>
    <table class="ringkas">
        <tr>
            <td>Total uang masuk</td>
            <td class="kanan masuk nilai">{{ Uang::format(Uang::keDesimal($ringkasan['masuk'])) }}</td>
        </tr>
        <tr>
            <td>Total uang keluar</td>
            <td class="kanan keluar nilai">{{ Uang::format(Uang::keDesimal($ringkasan['keluar'])) }}</td>
        </tr>
        <tr>
            <td><strong>Saldo kas</strong></td>
            <td class="kanan nilai">{{ Uang::format(Uang::keDesimal($ringkasan['saldo'])) }}</td>
        </tr>
        <tr>
            <td>Tunggakan ({{ $ringkasan['penunggak'] }} siswa)</td>
            <td class="kanan">{{ Uang::format(Uang::keDesimal($ringkasan['total_tunggakan'])) }}</td>
        </tr>
    </table>

    @if ($rekap->isNotEmpty())
        <h2>Rekap per periode</h2>
        <table>
            <thead>
                <tr>
                    <th>Periode</th>
                    <th class="kanan">Tertagih</th>
                    <th class="kanan">Terkumpul</th>
                    <th class="kanan">Sisa</th>
                    <th class="kanan">Lunas</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rekap as $baris)
                    <tr>
                        <td>{{ $baris['periode']->label }}{{ $baris['periode']->is_libur ? ' (libur)' : '' }}</td>
                        <td class="kanan">{{ Uang::format(Uang::keDesimal($baris['tertagih'])) }}</td>
                        <td class="kanan masuk">{{ Uang::format(Uang::keDesimal($baris['terkumpul'])) }}</td>
                        <td class="kanan">{{ Uang::format(Uang::keDesimal($baris['sisa'])) }}</td>
                        <td class="kanan">{{ $baris['lunas'] }} / {{ $baris['jumlah_tagihan'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($tunggakan->isNotEmpty())
        <h2>Daftar tunggakan</h2>
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama siswa</th>
                    <th class="kanan">Tagihan belum lunas</th>
                    <th class="kanan">Tunggakan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tunggakan as $baris)
                    <tr>
                        <td>{{ $baris['siswa']->no_absen ?? '—' }}</td>
                        <td>{{ $baris['siswa']->nama }}</td>
                        <td class="kanan">{{ $baris['belum_lunas'] }}</td>
                        <td class="kanan keluar">{{ Uang::format(Uang::keDesimal($baris['tunggakan'])) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Riwayat transaksi</h2>
    @if ($riwayat->isEmpty())
        <p class="keterangan">Tidak ada transaksi pada rentang ini.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th class="kanan">Masuk</th>
                    <th class="kanan">Keluar</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($riwayat as $baris)
                    <tr>
                        <td>{{ $baris['tanggal']->translatedFormat('j M Y') }}</td>
                        <td>{{ $baris['keterangan'] }}</td>
                        <td class="kanan masuk">
                            {{ $baris['jenis'] === 'masuk' ? Uang::format(Uang::keDesimal($baris['jumlah'])) : '' }}
                        </td>
                        <td class="kanan keluar">
                            {{ $baris['jenis'] === 'keluar' ? Uang::format(Uang::keDesimal($baris['jumlah'])) : '' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="ttd">
        <tr>
            <td>Bendahara<br><br><br>( ........................... )</td>
            <td>Ketua Kelas<br><br><br>( ........................... )</td>
            <td>Wali Kelas<br><br><br>( ........................... )</td>
        </tr>
    </table>

    <p class="kaki">
        Laporan ini dihasilkan otomatis oleh KasKelas dari catatan transaksi kelas
        {{ $kelas->nama_kelas }}. Seluruh angka dihitung ulang saat pencetakan.
    </p>
</body>
</html>
