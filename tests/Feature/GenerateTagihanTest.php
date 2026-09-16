<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Classroom;
use App\Models\Period;
use App\Models\Student;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Meniru URUTAN NYATA yang dipakai bendahara di produksi, bukan urutan factory.
 *
 * Test lama selalu membuat siswa DULU lalu periode, sehingga yang teruji cuma
 * generateTagihanPeriode() — jalur yang membaca siswa dari database. Jalur
 * sebaliknya (periode dulu, siswa menyusul) memakai generateTagihanSiswa() pada
 * objek yang BARU saja dibuat, dan justru itulah yang rusak di produksi.
 */
class GenerateTagihanTest extends TestCase
{
    use RefreshDatabase;

    /** Meniru TRPL 2B: kelas mingguan, periode dibuat lebih dulu, siswa menyusul. */
    public function test_tempel_daftar_siswa_setelah_periode_membuat_tagihan_lengkap(): void
    {
        [$kelas, $user] = $this->buatKelas('XII TRPL 2B', 'SMKN 1 Subang');
        $this->jadikanMingguan($kelas);

        // Langkah 1 — bendahara menekan "Buat periode & tagihannya" saat kelas
        // masih kosong, persis seperti yang diarahkan halaman periode.
        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-09-01',
            'nominal' => 5000,
            'sampai' => '2027-01-24',
        ])->assertRedirect(route('periode.index'));

        $jumlahPeriode = $this->dalamKelas($kelas, fn () => Period::where('is_libur', false)->count());
        $this->assertGreaterThan(1, $jumlahPeriode, 'Persiapan gagal: periodenya tidak terbentuk.');

        // Langkah 2 — daftar siswa ditempel lewat menu massal, tanggal mulai
        // aktifnya jauh SEBELUM periode pertama.
        $this->actingAs($user)->post(route('siswa.massal.store'), [
            'daftar' => "1. Adinda Ayu\n2. Bagas Pratama\n3. Citra Lestari",
            'tgl_mulai_aktif' => '2026-01-01',
        ])->assertRedirect(route('siswa.index'));

        $siswaAktif = $this->dalamKelas($kelas, fn () => Student::where('is_active', true)->count());
        $this->assertSame(3, $siswaAktif);

        $this->assertSame(
            $siswaAktif * $jumlahPeriode,
            $this->dalamKelas($kelas, fn () => Bill::count()),
            'Siswa yang ditambahkan setelah periode ada harus tetap kebagian tagihan setiap periode.'
        );
    }

    /** Jalur satu-satu lewat form siswa, bukan tempel daftar. */
    public function test_siswa_ditambah_satu_per_satu_setelah_periode_juga_dapat_tagihan(): void
    {
        [$kelas, $user] = $this->buatKelas();

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-05',
            'nominal' => 5000,
            'sampai' => '2026-03-31',
        ]);

        $jumlahPeriode = $this->dalamKelas($kelas, fn () => Period::where('is_libur', false)->count());
        $this->assertSame(3, $jumlahPeriode);

        $this->actingAs($user)->post(route('siswa.store'), [
            'nama' => 'Siswa Menyusul',
            'no_absen' => 1,
            'tgl_mulai_aktif' => '2026-01-01',
        ])->assertRedirect(route('siswa.index'));

        $siswa = $this->dalamKelas($kelas, fn () => Student::where('nama', 'Siswa Menyusul')->firstOrFail());

        $this->assertTrue($siswa->is_active);
        $this->assertSame(
            3,
            $this->dalamKelas($kelas, fn () => Bill::where('student_id', $siswa->id)->count())
        );
    }

    /**
     * Akar masalahnya, dipatok langsung: objek hasil Student::create() harus
     * sudah tahu dirinya aktif, tidak menunggu dibaca ulang dari database.
     */
    public function test_objek_siswa_baru_langsung_tahu_dirinya_aktif(): void
    {
        [$kelas] = $this->buatKelas();

        $siswa = $this->dalamKelas($kelas, fn () => Student::create([
            'nama' => 'Siswa Baru',
            'no_absen' => 1,
            'tgl_mulai_aktif' => '2026-01-01',
        ]));

        $this->assertTrue(
            $siswa->is_active,
            'is_active pada objek hasil create() bernilai null — setiap pemeriksaan "siswa aktif?" akan gagal senyap.'
        );
    }

    /** Periode libur tidak boleh ikut ditagihkan ke siswa yang menyusul. */
    public function test_periode_libur_dilewati_untuk_siswa_yang_menyusul(): void
    {
        [$kelas, $user] = $this->buatKelas();

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-05',
            'nominal' => 5000,
            'sampai' => '2026-03-31',
        ]);

        $periode = $this->dalamKelas($kelas, fn () => Period::urutWaktu()->firstOrFail());
        $this->actingAs($user)->patch(route('periode.libur', $periode), ['libur' => '1']);

        $this->actingAs($user)->post(route('siswa.store'), [
            'nama' => 'Siswa Menyusul',
            'no_absen' => 1,
            'tgl_mulai_aktif' => '2026-01-01',
        ]);

        $siswa = $this->dalamKelas($kelas, fn () => Student::where('nama', 'Siswa Menyusul')->firstOrFail());

        $this->assertSame(2, $this->dalamKelas($kelas, fn () => Bill::where('student_id', $siswa->id)->count()));
        $this->assertSame(0, $this->dalamKelas($kelas, fn () => Bill::where('period_id', $periode->id)->count()));
    }

    /*
    |--------------------------------------------------------------------------
    | Peringatan UX — bug ini gagal senyap, jadi kesunyiannya ikut diuji
    |--------------------------------------------------------------------------
    */

    public function test_periode_yang_menghasilkan_nol_tagihan_memperingatkan_bendahara(): void
    {
        [$kelas, $user] = $this->buatKelas();

        // Kelas masih kosong: 0 tagihan itu wajar, tapi TIDAK boleh dilaporkan sukses.
        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-05',
            'nominal' => 5000,
            'sampai' => '2026-03-31',
        ])->assertRedirect(route('periode.index'))
            ->assertSessionHas('peringatan')
            ->assertSessionMissing('sukses');

        $this->actingAs($user)->get(route('periode.index'))
            ->assertSee('TIDAK ADA satu pun tagihan', false)
            ->assertSee('belum punya siswa aktif', false);
    }

    public function test_menu_bayar_tanpa_periode_diarahkan_membuat_periode_dulu(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->get(route('pembayaran.create'))
            ->assertRedirect(route('periode.index'))
            ->assertSessionHas('peringatan');

        $this->actingAs($user)->get(route('periode.index'))
            ->assertSee('belum punya periode iuran', false);
    }

    public function test_menu_bayar_terbuka_normal_begitu_periode_ada(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-05', 'nominal' => 5000, 'sampai' => '2026-01-31',
        ]);

        $this->actingAs($user)->get(route('pembayaran.create'))->assertOk();
    }

    private function jadikanMingguan(Classroom $kelas): void
    {
        CurrentClassroom::withoutTenancy(fn () => $kelas->forceFill(['tipe_periode' => 'mingguan'])->save());
    }
}
