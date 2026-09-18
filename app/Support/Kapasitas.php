<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\User;

/**
 * Rem darurat kapasitas (PRD bagian 5).
 *
 * Ini BUKAN alat monetisasi. Aplikasi berjalan di satu paket shared hosting;
 * kalau jumlah kelas melewati kemampuan server, yang rusak bukan cuma kelas
 * baru — kelas lama yang sudah memakainya untuk uang sungguhan ikut melambat.
 * Menolak pendaftaran ke-101 jauh lebih sopan daripada membuat 100 kelas
 * pertama jadi lambat.
 *
 * Seluruh angkanya dari config/kaskelas.php, bisa dinaikkan lewat .env tanpa
 * deploy ulang — karena saat batas tercapai, itu justru saat paling mendesak.
 */
class Kapasitas
{
    /*
    |--------------------------------------------------------------------------
    | Kuota se-sistem
    |--------------------------------------------------------------------------
    */

    public static function batasKelasSistem(): int
    {
        return (int) config('kaskelas.batas.kelas_terdaftar');
    }

    /**
     * Kelas yang menghitung terhadap kuota.
     *
     * Kelas demo tidak ikut dihitung (bukan milik pengguna), dan kelas yang
     * sedang dalam tenggang hapus tetap dihitung — datanya masih ada di server,
     * jadi masih memakan tempat yang sama.
     */
    public static function jumlahKelasSistem(): int
    {
        return CurrentClassroom::withoutTenancy(
            fn () => Classroom::query()->where('is_demo', false)->count()
        );
    }

    public static function kuotaSistemPenuh(): bool
    {
        return static::jumlahKelasSistem() >= static::batasKelasSistem();
    }

    public static function sisaKuotaSistem(): int
    {
        return max(0, static::batasKelasSistem() - static::jumlahKelasSistem());
    }

    /*
    |--------------------------------------------------------------------------
    | Kuota per akun
    |--------------------------------------------------------------------------
    */

    public static function batasKelasPerAkun(): int
    {
        return (int) config('kaskelas.batas.kelas_per_akun');
    }

    /** Kelas yang masih dipegang user ini — yang sedang dihapus tidak dihitung. */
    public static function jumlahKelasAkun(User $user): int
    {
        return $user->classrooms()
            ->where('classrooms.status', '!=', 'dihapus')
            ->count();
    }

    public static function akunSudahPenuh(User $user): bool
    {
        return static::jumlahKelasAkun($user) >= static::batasKelasPerAkun();
    }

    /*
    |--------------------------------------------------------------------------
    | Kuota siswa per kelas
    |--------------------------------------------------------------------------
    */

    public static function batasSiswaPerKelas(): int
    {
        return (int) config('kaskelas.batas.siswa_per_kelas');
    }

    /**
     * Siswa yang menghitung terhadap kuota kelas.
     *
     * Siswa nonaktif TETAP dihitung: barisnya masih ada, riwayat tagihannya
     * masih dipakai laporan, dan menghapusnya bukan pilihan. Yang sudah
     * di-soft delete tidak dihitung.
     */
    public static function jumlahSiswa(?Classroom $kelas = null): int
    {
        $kelas ??= CurrentClassroom::getOrFail();

        return $kelas->students()->count();
    }

    public static function sisaKuotaSiswa(?Classroom $kelas = null): int
    {
        return max(0, static::batasSiswaPerKelas() - static::jumlahSiswa($kelas));
    }

    public static function kelasSudahPenuh(?Classroom $kelas = null): bool
    {
        return static::sisaKuotaSiswa($kelas) <= 0;
    }
}
