<?php

namespace App\Http\Middleware;

use App\Support\CurrentClassroom;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menahan halaman yang angkanya baru masuk akal setelah penyiapan selesai.
 *
 * Dipasang pada Bayar, Keluar, Laporan, Pengingat, Iuran insidental, dan Tutup
 * buku — bukan pada Siswa, Periode, atau Pengaturan, karena justru di sanalah
 * penyiapannya diselesaikan.
 *
 * Alasannya konkret: mencatat pembayaran sebelum ada periode menghasilkan
 * deposit menggantung — uangnya tercatat, laporannya nol, dan tidak ada galat
 * yang muncul. Bendahara baru sadar berminggu-minggu kemudian. Menyembunyikan
 * menunya saja tidak cukup; URL-nya masih bisa diketik langsung.
 */
class PastikanSetupSelesai
{
    public function handle(Request $request, Closure $next): Response
    {
        $kelas = CurrentClassroom::get();

        // Tanpa kelas aktif, SetCurrentClassroom yang mengurus — bukan di sini.
        if ($kelas === null) {
            return $next($request);
        }

        $langkah = $kelas->langkahWizardBerikutnya();

        if ($langkah === null) {
            return $next($request);
        }

        return redirect()->route($langkah)->with('peringatan', match ($langkah) {
            'wizard.siswa' => 'Kelas ini belum punya siswa. Masukkan daftar siswanya dulu — '
                .'tanpa siswa tidak ada tagihan, dan semua angka akan tetap nol.',
            default => 'Kelas ini belum punya periode iuran. Buat periodenya dulu — '
                .'tanpa periode tidak ada tagihan, jadi pembayaran yang dicatat sekarang '
                .'hanya akan mengendap sebagai deposit dan laporan tetap menunjukkan nol.',
        });
    }
}
