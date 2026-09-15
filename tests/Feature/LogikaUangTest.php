<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Classroom;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Period;
use App\Models\Student;
use App\Services\KasService;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian wajib PRD bagian 14.2 — logika uang.
 *
 * Seluruh angka di fase pelaporan bergantung pada berkas ini. Satu test merah
 * di sini berarti angka di dashboard dan halaman kelas juga salah.
 */
class LogikaUangTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $kelas;

    private Student $siswa;

    private KasService $kas;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->kelas, $user] = $this->buatKelas();
        $this->actingAs($user);

        CurrentClassroom::set($this->kelas);

        $this->kas = app(KasService::class);
        $this->siswa = $this->buatSiswa($this->kelas, 'Adinda');

        CurrentClassroom::set($this->kelas);
    }

    /** Tiga periode bulanan @ Rp5.000, jatuh tempo akhir Januari/Februari/Maret 2026. */
    private function siapkanTigaPeriode(): void
    {
        $this->kas->generatePeriode($this->kelas, '2026-01-01', 5000, '2026-03-31');

        Period::urutWaktu()->get()->each(fn (Period $p) => $this->kas->generateTagihanPeriode($p));
    }

    private function bayar(string $jumlah, string $tanggal = '2026-01-10'): Payment
    {
        $payment = Payment::create([
            'student_id' => $this->siswa->id,
            'tanggal' => $tanggal,
            'jumlah' => $jumlah,
            'metode' => 'tunai',
        ]);

        $this->kas->alokasikanDeposit($this->siswa);

        return $payment->fresh();
    }

    /** @return array<string, string> label periode => status */
    private function statusTagihan(): array
    {
        return Bill::with(['period', 'allocations'])
            ->get()
            ->mapWithKeys(fn (Bill $b) => [$b->period->label => $this->kas->statusTagihan($b)])
            ->all();
    }

    public function test_bayar_pas_menjadikan_tagihan_lunas_dan_deposit_nol(): void
    {
        $this->siapkanTigaPeriode();
        $this->bayar('5000.00');

        $this->assertSame('lunas', $this->statusTagihan()['Januari 2026']);
        $this->assertSame('belum', $this->statusTagihan()['Februari 2026']);
        $this->assertSame(0, $this->kas->depositSiswa($this->siswa));
    }

    public function test_bayar_rapel_tiga_periode_melunasi_ketiganya(): void
    {
        $this->siapkanTigaPeriode();
        $this->bayar('15000.00');

        $this->assertSame(
            ['Januari 2026' => 'lunas', 'Februari 2026' => 'lunas', 'Maret 2026' => 'lunas'],
            $this->statusTagihan()
        );
        $this->assertSame(0, $this->kas->depositSiswa($this->siswa));
    }

    public function test_bayar_sebagian_menyisakan_kurang_bayar_yang_akurat(): void
    {
        $this->siapkanTigaPeriode();
        $this->bayar('2000.00');

        $this->assertSame('kurang', $this->statusTagihan()['Januari 2026']);

        $januari = Bill::with(['period', 'allocations'])->get()
            ->firstWhere(fn (Bill $b) => $b->period->label === 'Januari 2026');

        $this->assertSame(300000, $this->kas->sisaTagihan($januari)); // Rp3.000 dalam sen
    }

    public function test_kelebihan_bayar_jadi_deposit_dan_terpakai_di_tagihan_berikutnya(): void
    {
        // Hanya Januari yang ada saat pembayaran dilakukan.
        $this->kas->generatePeriode($this->kelas, '2026-01-01', 5000, '2026-01-31');
        Period::urutWaktu()->get()->each(fn (Period $p) => $this->kas->generateTagihanPeriode($p));

        $this->bayar('12000.00');

        $this->assertSame('lunas', $this->statusTagihan()['Januari 2026']);
        $this->assertSame(700000, $this->kas->depositSiswa($this->siswa)); // sisa Rp7.000

        // Periode berikutnya dibuat — deposit harus terpakai otomatis.
        $this->kas->generatePeriode($this->kelas, '2026-02-01', 5000, '2026-03-31');
        Period::urutWaktu()->get()->each(fn (Period $p) => $this->kas->generateTagihanPeriode($p));

        $this->assertSame(
            ['Januari 2026' => 'lunas', 'Februari 2026' => 'lunas', 'Maret 2026' => 'kurang'],
            $this->statusTagihan()
        );
        $this->assertSame(0, $this->kas->depositSiswa($this->siswa));
    }

    public function test_tagihan_bebas_tidak_dihitung_sebagai_tunggakan(): void
    {
        $this->kas->generatePeriode($this->kelas, '2026-01-01', 5000, '2026-01-31');
        Period::urutWaktu()->get()->each(fn (Period $p) => $this->kas->generateTagihanPeriode($p));

        $bill = Bill::firstOrFail();

        $this->assertGreaterThan(0, $this->kas->tunggakanSiswa($this->siswa, $this->kelas));

        $bill->update(['is_bebas' => true, 'alasan_bebas' => 'keringanan wali kelas']);

        $this->assertSame(0, $this->kas->tunggakanSiswa($this->siswa, $this->kelas));
        $this->assertSame('bebas', $this->kas->statusTagihan($bill->fresh()));
    }

    public function test_denda_nonaktif_selalu_nol(): void
    {
        $this->kas->generatePeriode($this->kelas, '2026-01-01', 5000, '2026-01-31');
        Period::urutWaktu()->get()->each(fn (Period $p) => $this->kas->generateTagihanPeriode($p));

        $bill = Bill::with('period')->firstOrFail();

        $this->assertFalse($this->kelas->denda_aktif);
        $this->assertSame(0, $this->kas->dendaTagihan($bill, $this->kelas));
    }

    public function test_denda_belum_muncul_sebelum_lewat_masa_tenggang(): void
    {
        $this->kelas->update([
            'denda_aktif' => true,
            'denda_mode' => 'tetap',
            'denda_nominal' => '2000.00',
            'grace_days' => 7,
        ]);

        $this->kas->generatePeriode($this->kelas, '2026-01-01', 5000, '2026-01-31');
        Period::urutWaktu()->get()->each(fn (Period $p) => $this->kas->generateTagihanPeriode($p));

        $bill = Bill::with(['period', 'allocations'])->firstOrFail();
        $jatuhTempo = $bill->period->jatuh_tempo; // 31 Januari 2026

        $this->assertSame(0, $this->kas->dendaTagihan($bill, $this->kelas->fresh(), $jatuhTempo->copy()->addDays(7)));
        $this->assertSame(200000, $this->kas->dendaTagihan($bill, $this->kelas->fresh(), $jatuhTempo->copy()->addDays(8)));
    }

    public function test_denda_harian_bertambah_dan_dibatasi_denda_maks(): void
    {
        $this->kelas->update([
            'denda_aktif' => true,
            'denda_mode' => 'harian',
            'denda_nominal' => '1000.00',
            'grace_days' => 3,
            'denda_maks' => '5000.00',
        ]);

        $this->kas->generatePeriode($this->kelas, '2026-01-01', 5000, '2026-01-31');
        Period::urutWaktu()->get()->each(fn (Period $p) => $this->kas->generateTagihanPeriode($p));

        $bill = Bill::with(['period', 'allocations'])->firstOrFail();
        $jatuhTempo = $bill->period->jatuh_tempo;
        $kelas = $this->kelas->fresh();

        // 5 hari telat − 3 hari tenggang = 2 x Rp1.000
        $this->assertSame(200000, $this->kas->dendaTagihan($bill, $kelas, $jatuhTempo->copy()->addDays(5)));
        // 30 hari telat seharusnya Rp27.000, tapi dibatasi Rp5.000
        $this->assertSame(500000, $this->kas->dendaTagihan($bill, $kelas, $jatuhTempo->copy()->addDays(30)));
    }

    public function test_pembayaran_dihapus_memulihkan_status_tagihan(): void
    {
        $this->siapkanTigaPeriode();
        $payment = $this->bayar('15000.00');

        $this->assertSame('lunas', $this->statusTagihan()['Maret 2026']);

        $this->kas->hapusPembayaran($payment);

        $this->assertSame(
            ['Januari 2026' => 'belum', 'Februari 2026' => 'belum', 'Maret 2026' => 'belum'],
            $this->statusTagihan()
        );
        $this->assertSame(0, $this->kas->depositSiswa($this->siswa));
        $this->assertSame(0, $this->kas->saldoKas());
    }

    public function test_saldo_kas_sama_dengan_pembayaran_dikurangi_pengeluaran(): void
    {
        $this->siapkanTigaPeriode();
        $this->bayar('15000.00');

        $kategori = ExpenseCategory::whereNull('classroom_id')->first()
            ?? tap(new ExpenseCategory(['nama' => 'Konsumsi']), fn ($k) => $k->save());

        \App\Models\Expense::create([
            'tanggal' => '2026-02-01',
            'category_id' => $kategori->id,
            'jumlah' => '4000.00',
            'keterangan' => 'beli spidol',
        ]);

        $this->assertSame(1100000, $this->kas->saldoKas()); // Rp11.000
        $this->assertSame(1500000, $this->kas->totalMasuk());
        $this->assertSame(400000, $this->kas->totalKeluar());
    }

    public function test_alokasi_manual_tidak_boleh_melebihi_sisa_tagihan(): void
    {
        $this->siapkanTigaPeriode();

        $payment = Payment::create([
            'student_id' => $this->siswa->id,
            'tanggal' => '2026-01-10',
            'jumlah' => '15000.00',
            'metode' => 'tunai',
        ]);

        $januari = Bill::with('period')->get()
            ->firstWhere(fn (Bill $b) => $b->period->label === 'Januari 2026');

        $this->expectException(\RuntimeException::class);

        $this->kas->alokasikanManual($payment, [$januari->id => '9000']);
    }
}
