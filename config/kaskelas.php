<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Batas kapasitas
    |--------------------------------------------------------------------------
    | Ini REM DARURAT, bukan alat monetisasi. Aplikasi berjalan di shared
    | hosting satu paket; kalau pertumbuhannya melewati kemampuan server,
    | yang rusak bukan cuma kelas baru — kelas lama yang sudah memakainya
    | untuk uang sungguhan ikut melambat.
    |
    | Semuanya bisa dinaikkan lewat .env tanpa deploy ulang. Kalau angkanya
    | ditulis langsung di kode, menaikkan batas berarti menunggu jendela
    | deploy — padahal saat batas tercapai justru waktunya paling mendesak.
    */

    'batas' => [
        // Seluruh sistem. Setelah tercapai, pendaftaran diganti waiting list.
        'kelas_terdaftar' => (int) env('KASKELAS_BATAS_KELAS_SISTEM', 100),

        // Per akun bendahara.
        'kelas_per_akun' => (int) env('KASKELAS_BATAS_KELAS_AKUN', 5),

        // Per kelas.
        'siswa_per_kelas' => (int) env('KASKELAS_BATAS_SISWA', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Daur hidup kelas
    |--------------------------------------------------------------------------
    */

    'daur' => [
        // Tenggang sebelum kelas yang dihapus benar-benar lenyap. Bendahara
        // yang salah pencet punya waktu sebulan untuk sadar.
        'tenggang_hapus_hari' => (int) env('KASKELAS_TENGGANG_HAPUS', 30),

        // Kelas tanpa transaksi selama ini ditandai nonaktif.
        'nonaktif_setelah_bulan' => (int) env('KASKELAS_NONAKTIF_BULAN', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kelas demo
    |--------------------------------------------------------------------------
    | Kelas contoh read-only untuk calon pengguna yang ingin melihat isinya
    | sebelum mendaftar. Datanya fiktif seluruhnya dan direset berkala.
    */

    'demo' => [
        'aktif' => (bool) env('KASKELAS_DEMO_AKTIF', true),
        'nama_kelas' => 'XI RPL 2 (Demo)',
        'sekolah' => 'SMK Contoh Nusantara',
    ],

];
