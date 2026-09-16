<?php

/**
 * Dompdf mencari asetnya di base_path('public') — folder yang TIDAK ADA lagi
 * pada susunan deploy Hostinger (isi public/ dipindah ke document root, lihat
 * bootstrap/app.php). Tanpa baris ini realpath() gagal dan halaman "Unduh PDF"
 * menjadi 500 di produksi.
 *
 * Nilainya sengaja mengikuti public_path() supaya tetap benar di kedua susunan.
 */
return [
    'public_path' => public_path(),
];
