<?php

namespace App\Support;

/**
 * Semua hitungan uang dilakukan dalam satuan sen (integer), lalu dikembalikan
 * ke string DECIMAL(12,2) saat disimpan.
 *
 * Alasannya: float tidak bisa menyimpan 0.1 secara tepat, sehingga penjumlahan
 * berulang menghasilkan selisih satu-dua rupiah yang mustahil ditelusuri.
 * Integer tidak punya masalah itu, dan tidak menuntut ekstensi bcmath yang
 * belum tentu ada di shared hosting.
 */
class Uang
{
    /** Batas DECIMAL(12,2) = 9.999.999.999,99 → 999.999.999.999 sen. */
    public const MAKS_SEN = 999_999_999_999;

    public static function keSen(string|int|float|null $nilai): int
    {
        if ($nilai === null || $nilai === '') {
            return 0;
        }

        if (is_int($nilai)) {
            return $nilai * 100;
        }

        $teks = is_float($nilai) ? number_format($nilai, 2, '.', '') : trim((string) $nilai);
        $negatif = str_starts_with($teks, '-');
        $teks = ltrim($teks, '+-');

        [$bulat, $pecahan] = array_pad(explode('.', $teks, 2), 2, '0');

        $bulat = (int) preg_replace('/\D/', '', $bulat);
        $pecahan = (int) str_pad(substr(preg_replace('/\D/', '', $pecahan), 0, 2), 2, '0');

        $sen = $bulat * 100 + $pecahan;

        return $negatif ? -$sen : $sen;
    }

    /** Bentuk siap simpan ke kolom DECIMAL(12,2). */
    public static function keDesimal(int $sen): string
    {
        $negatif = $sen < 0;
        $sen = abs($sen);

        return ($negatif ? '-' : '').intdiv($sen, 100).'.'.str_pad((string) ($sen % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Tampilan untuk pengguna: "Rp 50.000" atau "Rp 50.000,50" bila ada sen. */
    public static function format(string|int|float|null $nilai, bool $denganSimbol = true): string
    {
        $sen = static::keSen($nilai);
        $negatif = $sen < 0;
        $sen = abs($sen);

        $rupiah = number_format(intdiv($sen, 100), 0, ',', '.');
        $pecahan = $sen % 100;

        $teks = $rupiah.($pecahan > 0 ? ','.str_pad((string) $pecahan, 2, '0', STR_PAD_LEFT) : '');

        return ($negatif ? '-' : '').($denganSimbol ? 'Rp '.$teks : $teks);
    }
}
