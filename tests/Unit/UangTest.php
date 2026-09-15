<?php

namespace Tests\Unit;

use App\Support\Uang;
use PHPUnit\Framework\TestCase;

/**
 * Uji murni tanpa database: inilah satu-satunya lapisan yang menyentuh angka
 * rupiah sebelum masuk ke kolom DECIMAL(12,2).
 */
class UangTest extends TestCase
{
    public function test_penjumlahan_berulang_tidak_kehilangan_sen(): void
    {
        // Dengan float, menjumlahkan 0,1 sepuluh kali menghasilkan 0.9999999999999999.
        $total = 0;

        for ($i = 0; $i < 10; $i++) {
            $total += Uang::keSen('0.10');
        }

        $this->assertSame(100, $total);
        $this->assertSame('1.00', Uang::keDesimal($total));
    }

    public function test_konversi_bolak_balik_tetap_utuh(): void
    {
        foreach (['0.00', '0.01', '5000.00', '12345.67', '9999999999.99'] as $nilai) {
            $this->assertSame($nilai, Uang::keDesimal(Uang::keSen($nilai)), "Gagal pada {$nilai}");
        }
    }

    public function test_menerima_int_float_dan_string_kosong(): void
    {
        $this->assertSame(500000, Uang::keSen(5000));
        $this->assertSame(1234, Uang::keSen(12.34));
        $this->assertSame(0, Uang::keSen(''));
        $this->assertSame(0, Uang::keSen(null));
    }

    public function test_pecahan_lebih_dari_dua_digit_dipotong_bukan_dibulatkan(): void
    {
        // Memotong lebih jujur daripada membulatkan: uang yang tidak ada di tangan
        // tidak boleh muncul karena pembulatan.
        $this->assertSame(1299, Uang::keSen('12.999'));
        $this->assertSame(1290, Uang::keSen('12.9'));
    }

    public function test_nilai_negatif_dipertahankan(): void
    {
        $this->assertSame(-500000, Uang::keSen('-5000.00'));
        $this->assertSame('-5000.00', Uang::keDesimal(-500000));
        $this->assertSame('-Rp 5.000', Uang::format('-5000.00'));
    }

    public function test_format_memakai_gaya_rupiah_indonesia(): void
    {
        $this->assertSame('Rp 0', Uang::format('0.00'));
        $this->assertSame('Rp 5.000', Uang::format('5000.00'));
        $this->assertSame('Rp 1.250.000', Uang::format('1250000.00'));
        $this->assertSame('Rp 1.250.000,50', Uang::format('1250000.50'));
        $this->assertSame('1.250.000', Uang::format('1250000.00', denganSimbol: false));
    }
}
