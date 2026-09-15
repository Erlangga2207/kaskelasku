<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Pengujian wajib PRD bagian 14.1 untuk fondasi tenancy.
 *
 * Test ini berlaku di lapisan model — tiap modul (siswa, pembayaran, pengeluaran)
 * masih wajib punya test isolasi sendiri di lapisan HTTP pada fasenya masing-masing.
 */
class IsolasiTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_kelas_a_tidak_pernah_mengembalikan_baris_kelas_b(): void
    {
        [$kelasA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $this->buatSiswa($kelasA, 'Adinda Kelas A');
        $this->buatSiswa($kelasB, 'Fajar Kelas B');

        CurrentClassroom::set($kelasA);

        $namaTerlihat = Student::pluck('nama')->all();

        $this->assertSame(['Adinda Kelas A'], $namaTerlihat);
        $this->assertSame(2, CurrentClassroom::withoutTenancy(fn () => Student::count()));
    }

    public function test_mencari_id_milik_kelas_lain_menghasilkan_null(): void
    {
        [$kelasA] = $this->buatKelas('XII TRPL 1');
        [$kelasB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $siswaB = $this->buatSiswa($kelasB, 'Fajar Kelas B');

        CurrentClassroom::set($kelasA);

        $this->assertNull(Student::find($siswaB->id));
        $this->assertNull($kelasA->students()->find($siswaB->id));
    }

    public function test_tanpa_kelas_aktif_query_gagal_tertutup(): void
    {
        [$kelasA] = $this->buatKelas();
        $this->buatSiswa($kelasA, 'Adinda Kelas A');

        CurrentClassroom::forget();

        // Bukan "semua baris", bukan pula error yang membocorkan jumlah data: nol baris.
        $this->assertSame(0, Student::count());
    }

    public function test_classroom_id_dari_input_tidak_dipakai_saat_menyimpan(): void
    {
        [$kelasA] = $this->buatKelas('XII TRPL 1');
        [$kelasB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        CurrentClassroom::set($kelasA);

        $siswa = new Student([
            'nama' => 'Siswa Selundupan',
            'no_absen' => 9,
            'tgl_mulai_aktif' => now()->toDateString(),
        ]);
        $siswa->classroom_id = $kelasB->id; // meniru hidden input yang diutak-atik
        $siswa->save();

        $this->assertSame($kelasA->id, $siswa->fresh()->classroom_id);
    }

    public function test_classroom_id_tidak_bisa_dipindah_setelah_tersimpan(): void
    {
        [$kelasA] = $this->buatKelas('XII TRPL 1');
        [$kelasB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $siswa = $this->buatSiswa($kelasA, 'Adinda Kelas A');

        CurrentClassroom::set($kelasA);

        $this->expectException(RuntimeException::class);

        $siswa->classroom_id = $kelasB->id;
        $siswa->save();
    }

    public function test_menyimpan_tanpa_kelas_aktif_ditolak(): void
    {
        CurrentClassroom::forget();

        $this->expectException(RuntimeException::class);

        Student::create([
            'nama' => 'Siswa Tanpa Kelas',
            'no_absen' => 1,
            'tgl_mulai_aktif' => now()->toDateString(),
        ]);
    }
}
