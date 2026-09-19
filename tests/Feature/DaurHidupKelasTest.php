<?php

namespace Tests\Feature;

use App\Http\Middleware\SetCurrentClassroom;
use App\Models\Classroom;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Student;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Daur hidup kelas + ekspor data (Fase 10 / v2.0).
 *
 * Yang hilang saat sebuah kelas dihapus bukan satu baris, melainkan seluruh
 * catatan uang satu kelas selama setahun — dan tidak ada tombol undo untuk itu.
 * Karena itu penghapusan selalu bertenggang, dan ekspor CSV selalu tersedia.
 */
class DaurHidupKelasTest extends TestCase
{
    use RefreshDatabase;

    /** Kelas siap pakai lengkap dengan satu pembayaran, supaya ada yang bisa hilang. */
    protected function kelasBerisi(string $nama = 'XII TRPL 1', string $sekolah = 'SMKN 1 Subang'): array
    {
        [$kelas, $user] = $this->buatKelas($nama, $sekolah);
        $siswa = $this->buatSiswa($kelas, 'Adinda '.$nama);
        $this->buatPeriode($kelas);

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id, 'tanggal' => '2026-01-10', 'jumlah' => 10000, 'metode' => 'tunai',
        ])->assertSessionHasNoErrors();

        $kategori = $this->dalamKelas($kelas, fn () => ExpenseCategory::orderBy('id')->firstOrFail());
        $this->actingAs($user)->post(route('pengeluaran.store'), [
            'tanggal' => '2026-01-11', 'category_id' => $kategori->id,
            'jumlah' => 3000, 'keterangan' => 'beli spidol',
        ])->assertSessionHasNoErrors();

        $this->flushSession();

        return [$kelas, $user, $siswa];
    }

    /*
    |--------------------------------------------------------------------------
    | Hapus kelas dengan tenggang
    |--------------------------------------------------------------------------
    */

    public function test_hapus_kelas_menjadwalkan_bukan_melenyapkan(): void
    {
        [$kelas, $user] = $this->kelasBerisi();

        $this->actingAs($user)->delete(route('kelas.destroy'), [
            'konfirmasi_nama' => 'XII TRPL 1',
        ])->assertRedirect(route('kelas.terhapus'));

        $tersimpan = CurrentClassroom::withoutTenancy(fn () => Classroom::findOrFail($kelas->id));

        $this->assertSame('dihapus', $tersimpan->status);
        $this->assertNotNull($tersimpan->dihapus_pada);

        // Datanya masih utuh — yang berubah baru statusnya.
        $this->assertSame(1, DB::table('payments')->where('classroom_id', $kelas->id)->count());
        $this->assertSame(1, DB::table('students')->where('classroom_id', $kelas->id)->count());

        // Kelas aktif dilepas dari session; kelas yang dihapus tidak boleh jadi
        // tempat mendarat request berikutnya.
        $this->assertNull(session(SetCurrentClassroom::SESSION_KEY));
    }

    /** Mengetik nama kelasnya memaksa berhenti sejenak — satu akun bisa punya lima kelas. */
    public function test_nama_kelas_yang_salah_membatalkan_penghapusan(): void
    {
        [$kelas, $user] = $this->kelasBerisi();

        $this->actingAs($user)->delete(route('kelas.destroy'), [
            'konfirmasi_nama' => 'XI IPA 3',
        ])->assertSessionHas('galat');

        $this->assertSame('aktif', CurrentClassroom::withoutTenancy(
            fn () => Classroom::findOrFail($kelas->id)->status
        ));

        $this->actingAs($user)->delete(route('kelas.destroy'), [])
            ->assertSessionHasErrors('konfirmasi_nama');
    }

    /** Hanya pemilik. Bendahara kedua yang diundang tidak boleh menghapus kelas orang. */
    public function test_bendahara_bukan_pemilik_tidak_bisa_menghapus_kelas(): void
    {
        [$kelas] = $this->kelasBerisi();
        [, $userLain] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $kelas->users()->attach($userLain->id, ['peran' => 'bendahara', 'created_at' => now()]);
        session([SetCurrentClassroom::SESSION_KEY => $kelas->id]);

        $this->actingAs($userLain)->delete(route('kelas.destroy'), [
            'konfirmasi_nama' => 'XII TRPL 1',
        ])->assertForbidden();

        $this->assertSame('aktif', CurrentClassroom::withoutTenancy(
            fn () => Classroom::findOrFail($kelas->id)->status
        ));
    }

    public function test_kelas_terhapus_bisa_dipulihkan_utuh(): void
    {
        [$kelas, $user] = $this->kelasBerisi();

        $this->actingAs($user)->delete(route('kelas.destroy'), ['konfirmasi_nama' => 'XII TRPL 1']);

        $this->actingAs($user)->get(route('kelas.terhapus'))
            ->assertOk()
            ->assertSee('XII TRPL 1');

        $this->actingAs($user)->patch(route('kelas.restore', $kelas->id))
            ->assertRedirect(route('dashboard'));

        $pulih = CurrentClassroom::withoutTenancy(fn () => Classroom::findOrFail($kelas->id));

        $this->assertSame('aktif', $pulih->status);
        $this->assertNull($pulih->dihapus_pada);
        $this->assertSame(1, $this->dalamKelas($pulih, fn () => Payment::count()));
    }

    /** Isolasi: kelas terhapus milik orang lain tidak boleh bisa dipulihkan. */
    public function test_memulihkan_kelas_orang_lain_menghasilkan_404(): void
    {
        [$kelasB, $userB] = $this->kelasBerisi('XI IPA 3', 'SMAN 2 Bandung');
        [, $userA] = $this->kelasBerisi('XII TRPL 1', 'SMKN 1 Subang');

        $this->actingAs($userB)->delete(route('kelas.destroy'), ['konfirmasi_nama' => 'XI IPA 3']);
        $this->flushSession();

        $this->actingAs($userA)->patch(route('kelas.restore', $kelasB->id))->assertNotFound();

        $this->assertSame('dihapus', CurrentClassroom::withoutTenancy(
            fn () => Classroom::findOrFail($kelasB->id)->status
        ));
    }

    /** Kelas yang sedang dalam tenggang tidak boleh bisa dibuka lagi seperti biasa. */
    public function test_kelas_terhapus_tidak_bisa_dibuka_lagi(): void
    {
        [, $user] = $this->kelasBerisi();

        $this->actingAs($user)->delete(route('kelas.destroy'), ['konfirmasi_nama' => 'XII TRPL 1']);

        // Tidak punya kelas aktif lain → diantar ke wizard, bukan masuk diam-diam.
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('wizard.kelas'));
    }

    /*
    |--------------------------------------------------------------------------
    | Perintah perawatan terjadwal
    |--------------------------------------------------------------------------
    */

    public function test_kelas_dihapus_permanen_setelah_tenggang_lewat(): void
    {
        config(['kaskelas.daur.tenggang_hapus_hari' => 30]);

        [$kelasLama, $userLama] = $this->kelasBerisi('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasBaru, $userBaru] = $this->kelasBerisi('XI IPA 3', 'SMAN 2 Bandung');

        $this->actingAs($userLama)->delete(route('kelas.destroy'), ['konfirmasi_nama' => 'XII TRPL 1']);
        $this->flushSession();
        $this->actingAs($userBaru)->delete(route('kelas.destroy'), ['konfirmasi_nama' => 'XI IPA 3']);
        $this->flushSession();

        // Yang satu sudah lewat tenggang, yang satu baru kemarin.
        CurrentClassroom::withoutTenancy(function () use ($kelasLama, $kelasBaru) {
            Classroom::findOrFail($kelasLama->id)->forceFill(['dihapus_pada' => now()->subDays(31)])->save();
            Classroom::findOrFail($kelasBaru->id)->forceFill(['dihapus_pada' => now()->subDay()])->save();
        });

        // Dry-run dulu: melaporkan, tapi tidak boleh mengubah apa pun.
        $this->artisan('kaskelas:rawat-kelas', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(1, DB::table('classrooms')->where('id', $kelasLama->id)->count(),
            'Dry-run tidak boleh menghapus apa pun.');

        $this->artisan('kaskelas:rawat-kelas')->assertSuccessful();

        // Kelas lama lenyap SELURUHNYA, termasuk baris anaknya.
        $this->assertSame(0, DB::table('classrooms')->where('id', $kelasLama->id)->count());
        foreach (['students', 'periods', 'bills', 'payments', 'payment_allocations',
            'expenses', 'audit_logs', 'classroom_user'] as $tabel) {
            $this->assertSame(0, DB::table($tabel)->where('classroom_id', $kelasLama->id)->count(),
                "Sisa baris di tabel {$tabel} berarti kelas tidak benar-benar terhapus.");
        }

        // Kelas yang tenggangnya belum lewat tidak boleh ikut tersapu.
        $this->assertSame(1, DB::table('classrooms')->where('id', $kelasBaru->id)->count());
        $this->assertSame(1, DB::table('payments')->where('classroom_id', $kelasBaru->id)->count());
    }

    public function test_kelas_tanpa_aktivitas_dua_belas_bulan_ditandai_nonaktif(): void
    {
        config(['kaskelas.daur.nonaktif_setelah_bulan' => 12]);

        [$terlantar] = $this->kelasBerisi('XII TRPL 1', 'SMKN 1 Subang');
        [$masihDipakai] = $this->kelasBerisi('XI IPA 3', 'SMAN 2 Bandung');

        CurrentClassroom::withoutTenancy(function () use ($terlantar, $masihDipakai) {
            foreach ([$terlantar, $masihDipakai] as $kelas) {
                Classroom::findOrFail($kelas->id)->forceFill(['created_at' => now()->subMonths(18)])->save();
            }
        });

        // Transaksi kelas terlantar didorong mundur lebih dari 12 bulan;
        // kelas satunya baru saja dipakai.
        DB::table('payments')->where('classroom_id', $terlantar->id)
            ->update(['created_at' => now()->subMonths(14)]);
        DB::table('expenses')->where('classroom_id', $terlantar->id)
            ->update(['created_at' => now()->subMonths(14)]);

        $this->artisan('kaskelas:rawat-kelas')->assertSuccessful();

        $this->assertSame('nonaktif', CurrentClassroom::withoutTenancy(
            fn () => Classroom::findOrFail($terlantar->id)->status
        ));
        $this->assertSame('aktif', CurrentClassroom::withoutTenancy(
            fn () => Classroom::findOrFail($masihDipakai->id)->status
        ));
    }

    /** Kelas nonaktif juga menutup halaman kelas publiknya — 404 polos, tanpa petunjuk. */
    public function test_kelas_nonaktif_menutup_halaman_publiknya(): void
    {
        config(['kaskelas.daur.nonaktif_setelah_bulan' => 12]);

        [$kelas] = $this->kelasBerisi();

        CurrentClassroom::withoutTenancy(
            fn () => Classroom::findOrFail($kelas->id)->forceFill(['created_at' => now()->subMonths(18)])->save()
        );
        DB::table('payments')->where('classroom_id', $kelas->id)->update(['created_at' => now()->subMonths(14)]);
        DB::table('expenses')->where('classroom_id', $kelas->id)->update(['created_at' => now()->subMonths(14)]);

        $this->artisan('kaskelas:rawat-kelas')->assertSuccessful();

        $this->get(route('publik.kelas', $kelas->public_token))->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Ekspor CSV
    |--------------------------------------------------------------------------
    */

    public function test_seluruh_data_kelas_bisa_diunduh_sebagai_csv(): void
    {
        [$kelas, $user] = $this->kelasBerisi();

        $this->actingAs($user)->get(route('ekspor.index'))->assertOk();

        foreach ([
            'siswa', 'periode', 'tagihan', 'pembayaran', 'alokasi',
            'pengeluaran', 'campaign', 'tutup-buku',
        ] as $jenis) {
            $respons = $this->actingAs($user)->get(route('ekspor.unduh', $jenis));

            $respons->assertOk();
            $respons->assertHeader('content-type', 'text/csv; charset=UTF-8');
            $respons->assertHeader('x-content-type-options', 'nosniff');

            $isi = $respons->streamedContent();

            // BOM UTF-8: tanpa ini Excel di Windows merusak nama bertanda baca.
            $this->assertStringStartsWith("\xEF\xBB\xBF", $isi, "Ekspor {$jenis} kehilangan BOM.");
        }

        $siswaCsv = $this->actingAs($user)->get(route('ekspor.unduh', 'siswa'))->streamedContent();
        $this->assertStringContainsString('Adinda XII TRPL 1', $siswaCsv);

        $pembayaranCsv = $this->actingAs($user)->get(route('ekspor.unduh', 'pembayaran'))->streamedContent();
        $this->assertStringContainsString('10000.00', $pembayaranCsv);
    }

    public function test_jenis_ekspor_yang_tidak_dikenal_menghasilkan_404(): void
    {
        [, $user] = $this->kelasBerisi();

        $this->actingAs($user)->get('/ekspor/users.csv')->assertNotFound();
        $this->actingAs($user)->get('/ekspor/audit_logs.csv')->assertNotFound();
    }

    /** Isolasi: ekspor selalu berisi kelas aktif si pengunduh, tidak pernah kelas lain. */
    public function test_ekspor_kelas_a_tidak_memuat_satu_baris_pun_kelas_b(): void
    {
        [, $userA] = $this->kelasBerisi('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB] = $this->kelasBerisi('XI IPA 3', 'SMAN 2 Bandung');

        $siswaB = $this->dalamKelas($kelasB, fn () => Student::firstOrFail());

        $csv = $this->actingAs($userA)->get(route('ekspor.unduh', 'siswa'))->streamedContent();

        $this->assertStringContainsString('Adinda XII TRPL 1', $csv);
        $this->assertStringNotContainsString($siswaB->nama, $csv);
        $this->assertStringNotContainsString('SMAN 2 Bandung', $csv);
    }
}
