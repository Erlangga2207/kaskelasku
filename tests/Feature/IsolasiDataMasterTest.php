<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\Student;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test isolasi tingkat HTTP untuk modul siswa & periode (PRD 14.1).
 * Setiap modul baru wajib punya test seperti ini — bukan sekali saja di Fase 1.
 */
class IsolasiDataMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_bendahara_a_membuka_id_siswa_kelas_b_mendapat_404(): void
    {
        [, $userA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $siswaB = $this->buatSiswa($kelasB, 'Fajar Kelas B');

        $this->actingAs($userA)->get(route('siswa.edit', $siswaB))->assertNotFound();
        $this->actingAs($userA)->put(route('siswa.update', $siswaB), [
            'nama' => 'Diubah Paksa',
            'tgl_mulai_aktif' => '2026-01-01',
        ])->assertNotFound();
        $this->actingAs($userA)->delete(route('siswa.destroy', $siswaB))->assertNotFound();

        $this->assertSame('Fajar Kelas B', CurrentClassroom::withoutTenancy(
            fn () => Student::withoutGlobalScopes()->find($siswaB->id)->nama
        ));
    }

    public function test_daftar_siswa_kelas_a_tidak_memuat_siswa_kelas_b(): void
    {
        [$kelasA, $userA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $this->buatSiswa($kelasA, 'Adinda Kelas A');
        $this->buatSiswa($kelasB, 'Fajar Kelas B');

        $this->actingAs($userA)->get(route('siswa.index'))
            ->assertOk()
            ->assertSee('Adinda Kelas A')
            ->assertDontSee('Fajar Kelas B');
    }

    public function test_bendahara_a_tidak_bisa_mengubah_periode_kelas_b(): void
    {
        [, $userA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB, $userB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $this->actingAs($userB)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-05', 'nominal' => 5000, 'sampai' => '2026-01-31',
        ]);

        $periodeB = $this->dalamKelas($kelasB, fn () => Period::firstOrFail());

        $this->actingAs($userA)->patch(route('periode.update', $periodeB), [
            'label' => 'Dibajak', 'nominal' => 1,
        ])->assertNotFound();

        $this->actingAs($userA)->patch(route('periode.libur', $periodeB), ['libur' => '1'])->assertNotFound();

        $this->assertSame('5000.00', $this->dalamKelas($kelasB, fn () => Period::find($periodeB->id)->nominal));
    }

    public function test_classroom_id_di_form_tidak_memindahkan_siswa(): void
    {
        [$kelasA, $userA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $this->actingAs($userA)->post(route('siswa.store'), [
            'nama' => 'Siswa Selundupan',
            'no_absen' => 7,
            'tgl_mulai_aktif' => '2026-01-01',
            'classroom_id' => $kelasB->id, // hidden input yang diutak-atik
        ])->assertRedirect(route('siswa.index'));

        $siswa = $this->dalamKelas($kelasA, fn () => Student::where('nama', 'Siswa Selundupan')->first());

        $this->assertNotNull($siswa);
        $this->assertSame($kelasA->id, $siswa->classroom_id);
        $this->assertSame(0, $this->dalamKelas($kelasB, fn () => Student::count()));
    }
}
