<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji asap: memastikan setiap halaman bendahara benar-benar ter-render.
 * Galat Blade tidak tertangkap oleh test logika, karena test logika jarang
 * menyentuh view-nya.
 */
class HalamanBendaharaTest extends TestCase
{
    use RefreshDatabase;

    public function test_semua_halaman_bendahara_terbuka(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda Ayu');

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-03-31',
        ]);
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id, 'tanggal' => '2026-01-10', 'jumlah' => 7000, 'metode' => 'tunai',
        ]);

        $kategori = $this->dalamKelas($kelas, fn () => ExpenseCategory::whereNull('classroom_id')->firstOrFail());

        $this->actingAs($user)->post(route('pengeluaran.store'), [
            'tanggal' => '2026-01-11', 'category_id' => $kategori->id,
            'jumlah' => 2000, 'keterangan' => 'beli spidol',
        ]);

        $pembayaran = $this->dalamKelas($kelas, fn () => Payment::firstOrFail());
        $pengeluaran = $this->dalamKelas($kelas, fn () => Expense::firstOrFail());

        $halaman = [
            route('dashboard'),
            route('siswa.index'),
            route('siswa.create'),
            route('siswa.edit', $siswa),
            route('siswa.show', $siswa),
            route('siswa.massal'),
            route('periode.index'),
            route('pembayaran.index'),
            route('pembayaran.create'),
            route('pembayaran.create', ['siswa' => $siswa->id]),
            route('pengeluaran.index'),
            route('pengeluaran.create'),
            route('pengeluaran.edit', $pengeluaran),
            route('pengingat.index'),
            route('pengaturan.edit'),
        ];

        foreach ($halaman as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }

        $this->assertNotNull($pembayaran);
        $this->assertGreaterThan(0, $this->dalamKelas($kelas, fn () => Bill::count()));
    }
}
