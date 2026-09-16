<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Period;
use App\Models\Student;
use App\Services\KasService;
use App\Support\Uang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menguji perintah penambal terhadap kerusakan yang SAMA seperti di produksi:
 * periode dan siswa lengkap, tagihan nol, uang menggantung sebagai deposit.
 */
class PerbaikiTagihanCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Membangun kelas rusak persis seperti TRPL 2B: periode ada, siswa aktif ada,
     * tagihan nol, dan sudah ada pembayaran yang tidak teralokasi.
     *
     * @return array{0: \App\Models\Classroom, 1: \App\Models\User, 2: Student}
     */
    private function kelasRusak(): array
    {
        [$kelas, $user] = $this->buatKelas();

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-05',
            'nominal' => 5000,
            'sampai' => '2026-03-31',
        ]);

        $siswa = $this->buatSiswa($kelas, 'Adinda', ['tgl_mulai_aktif' => '2026-01-01']);

        // Kerusakannya: siswa ada, periode ada, tagihan tidak pernah terbentuk.
        $this->dalamKelas($kelas, fn () => Bill::query()->delete());
        $this->assertSame(0, $this->dalamKelas($kelas, fn () => Bill::count()));

        // Uang yang sudah masuk tapi tidak punya tagihan untuk ditempati.
        $this->actingAs($user);
        $this->dalamKelas($kelas, fn () => Payment::create([
            'student_id' => $siswa->id,
            'tanggal' => '2026-01-10',
            'jumlah' => '10000.00',
            'metode' => 'tunai',
        ]));

        return [$kelas, $user, $siswa];
    }

    public function test_dry_run_melaporkan_rencana_tanpa_mengubah_apa_pun(): void
    {
        [$kelas] = $this->kelasRusak();

        $this->artisan('kaskelas:perbaiki-tagihan', ['--dry-run' => true])
            ->assertSuccessful()
            ->run();

        $this->assertSame(0, $this->dalamKelas($kelas, fn () => Bill::count()), '--dry-run tidak boleh menulis apa pun.');
        $this->assertSame(0, $this->dalamKelas($kelas, fn () => PaymentAllocation::count()));
    }

    public function test_menambal_tagihan_yang_hilang_dan_mengalokasikan_deposit(): void
    {
        [$kelas, , $siswa] = $this->kelasRusak();
        $kas = app(KasService::class);

        $this->artisan('kaskelas:perbaiki-tagihan')->assertSuccessful()->run();

        $jumlahPeriode = $this->dalamKelas($kelas, fn () => Period::where('is_libur', false)->count());

        $this->assertSame($jumlahPeriode, $this->dalamKelas($kelas, fn () => Bill::count()));

        // Rp 10.000 menutup dua tagihan @Rp 5.000 yang TERTUA, bukan yang terbaru.
        $this->dalamKelas($kelas, function () use ($kas, $siswa) {
            $tagihan = Bill::with(['period', 'allocations'])->get()
                ->sortBy(fn (Bill $b) => $b->period->tgl_mulai->timestamp)
                ->values();

            $this->assertSame('lunas', $kas->statusTagihan($tagihan[0]));
            $this->assertSame('lunas', $kas->statusTagihan($tagihan[1]));
            $this->assertSame('belum', $kas->statusTagihan($tagihan[2]));
            $this->assertSame(0, $kas->depositSiswa($siswa));
        });
    }

    public function test_dijalankan_dua_kali_tidak_menggandakan_tagihan_atau_alokasi(): void
    {
        [$kelas] = $this->kelasRusak();

        $this->artisan('kaskelas:perbaiki-tagihan')->assertSuccessful()->run();

        $tagihan = $this->dalamKelas($kelas, fn () => Bill::count());
        $alokasi = $this->dalamKelas($kelas, fn () => PaymentAllocation::count());
        $totalAlokasi = $this->dalamKelas($kelas, fn () => (string) PaymentAllocation::sum('jumlah'));

        $this->artisan('kaskelas:perbaiki-tagihan')->assertSuccessful()->run();

        $this->assertSame($tagihan, $this->dalamKelas($kelas, fn () => Bill::count()));
        $this->assertSame($alokasi, $this->dalamKelas($kelas, fn () => PaymentAllocation::count()));
        $this->assertSame(
            Uang::keSen($totalAlokasi),
            Uang::keSen($this->dalamKelas($kelas, fn () => (string) PaymentAllocation::sum('jumlah')))
        );
    }

    public function test_opsi_kelas_membatasi_perbaikan_ke_satu_kelas_saja(): void
    {
        [$kelasA] = $this->kelasRusak();
        [$kelasB, $userB] = $this->buatKelas('XII RPL 9', 'SMKN 2 Subang');

        $this->actingAs($userB)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-05', 'nominal' => 5000, 'sampai' => '2026-03-31',
        ]);
        $this->buatSiswa($kelasB, 'Fajar', ['tgl_mulai_aktif' => '2026-01-01']);
        $this->dalamKelas($kelasB, fn () => Bill::query()->delete());

        $this->artisan('kaskelas:perbaiki-tagihan', ['--kelas' => [$kelasA->id]])
            ->assertSuccessful()
            ->run();

        $this->assertGreaterThan(0, $this->dalamKelas($kelasA, fn () => Bill::count()));
        $this->assertSame(0, $this->dalamKelas($kelasB, fn () => Bill::count()), 'Kelas lain tidak boleh ikut tersentuh.');
    }

    public function test_kelas_tanpa_siswa_atau_tanpa_periode_dilewati_dengan_selamat(): void
    {
        [$kelasKosong, $user] = $this->buatKelas();

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-05', 'nominal' => 5000, 'sampai' => '2026-03-31',
        ]);

        $this->artisan('kaskelas:perbaiki-tagihan')->assertSuccessful()->run();

        $this->assertSame(0, $this->dalamKelas($kelasKosong, fn () => Bill::count()));
        $this->assertSame(0, $this->dalamKelas($kelasKosong, fn () => Student::count()));
    }
}
