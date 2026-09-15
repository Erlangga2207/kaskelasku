<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Services\KasService;
use App\Support\CurrentClassroom;
use App\Support\Uang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PelaporanTest extends TestCase
{
    use RefreshDatabase;

    /** Syarat "Selesai bila" Fase 4: saldo dashboard = SUM(payments) - SUM(expenses). */
    public function test_saldo_dashboard_sama_dengan_hitungan_sql_manual(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $adinda = $this->buatSiswa($kelas, 'Adinda', ['no_absen' => 1]);
        $bagas = $this->buatSiswa($kelas, 'Bagas', ['no_absen' => 2]);

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-03-31',
        ]);

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $adinda->id, 'tanggal' => '2026-01-10', 'jumlah' => 15000, 'metode' => 'tunai',
        ]);
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $bagas->id, 'tanggal' => '2026-01-12', 'jumlah' => 5000, 'metode' => 'transfer',
        ]);

        $kategori = $this->dalamKelas($kelas, fn () => ExpenseCategory::whereNull('classroom_id')->firstOrFail());
        $this->actingAs($user)->post(route('pengeluaran.store'), [
            'tanggal' => '2026-01-15', 'category_id' => $kategori->id,
            'jumlah' => 6000, 'keterangan' => 'beli spidol',
        ]);

        $kas = app(KasService::class);
        $ringkasan = CurrentClassroom::runFor($kelas, fn () => $kas->ringkasan($kelas));

        // Hitungan manual langsung ke SQL, tanpa lewat model & global scope.
        $masuk = (string) DB::table('payments')->where('classroom_id', $kelas->id)->whereNull('deleted_at')->sum('jumlah');
        $keluar = (string) DB::table('expenses')->where('classroom_id', $kelas->id)->whereNull('deleted_at')->sum('jumlah');
        $saldoManual = Uang::keSen($masuk) - Uang::keSen($keluar);

        $this->assertSame($saldoManual, $ringkasan['saldo']);
        $this->assertSame(1400000, $ringkasan['saldo']); // Rp20.000 − Rp6.000
        $this->assertSame(2000000, $ringkasan['masuk']);
        $this->assertSame(600000, $ringkasan['keluar']);
    }

    public function test_daftar_tunggakan_diurutkan_dari_terbesar(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $adinda = $this->buatSiswa($kelas, 'Adinda', ['no_absen' => 1]);
        $this->buatSiswa($kelas, 'Bagas', ['no_absen' => 2]);
        $this->buatSiswa($kelas, 'Citra', ['no_absen' => 3]);

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-03-31',
        ]);

        // Adinda melunasi semuanya, dua lainnya belum bayar sama sekali.
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $adinda->id, 'tanggal' => '2026-01-10', 'jumlah' => 15000, 'metode' => 'tunai',
        ]);

        $kas = app(KasService::class);
        $tunggakan = CurrentClassroom::runFor($kelas, fn () => $kas->daftarTunggakan($kelas));

        $this->assertCount(2, $tunggakan);
        $this->assertNotContains('Adinda', $tunggakan->pluck('siswa.nama')->all());
        $this->assertGreaterThanOrEqual($tunggakan->last()['tunggakan'], $tunggakan->first()['tunggakan']);
    }

    public function test_rekap_periode_menghitung_tertagih_dan_terkumpul(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $adinda = $this->buatSiswa($kelas, 'Adinda', ['no_absen' => 1]);
        $this->buatSiswa($kelas, 'Bagas', ['no_absen' => 2]);

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-02-28',
        ]);

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $adinda->id, 'tanggal' => '2026-01-10', 'jumlah' => 5000, 'metode' => 'tunai',
        ]);

        $kas = app(KasService::class);
        $rekap = CurrentClassroom::runFor($kelas, fn () => $kas->rekapPeriode());

        $januari = $rekap->firstWhere(fn ($b) => $b['periode']->label === 'Januari 2026');

        $this->assertSame(1000000, $januari['tertagih']);  // 2 siswa x Rp5.000
        $this->assertSame(500000, $januari['terkumpul']);
        $this->assertSame(500000, $januari['sisa']);
        $this->assertSame(1, $januari['lunas']);
        $this->assertSame(2, $januari['jumlah_tagihan']);
    }

    public function test_halaman_laporan_dan_audit_terbuka(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-01-31',
        ]);
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id, 'tanggal' => '2026-01-10', 'jumlah' => 5000, 'metode' => 'tunai',
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Saldo kas');
        $this->actingAs($user)->get(route('laporan.index'))->assertOk()->assertSee('Rekap per periode');
        $this->actingAs($user)->get(route('audit.index'))->assertOk()->assertSee('hanya bisa dibaca');
        $this->actingAs($user)->get(route('laporan.index', ['dari' => '2026-01-01', 'sampai' => '2026-01-31']))->assertOk();
    }

    public function test_laporan_pdf_terunduh(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-01-31',
        ]);
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id, 'tanggal' => '2026-01-10', 'jumlah' => 5000, 'metode' => 'tunai',
        ]);

        $respons = $this->actingAs($user)->get(route('laporan.pdf'));

        $respons->assertOk();
        $respons->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respons->getContent());
    }

    public function test_audit_log_kelas_a_tidak_memuat_jejak_kelas_b(): void
    {
        [, $userA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB, $userB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $this->buatSiswa($kelasB, 'Fajar Kelas B');

        $this->actingAs($userB)->post(route('siswa.store'), [
            'nama' => 'Gita Kelas B', 'no_absen' => 9, 'tgl_mulai_aktif' => '2026-01-01',
        ]);

        // Session di test dipakai bersama; pesan flash milik bendahara B harus
        // dibuang dulu supaya yang diuji benar-benar isi halaman, bukan sisa notifikasi.
        $this->flushSession();

        $this->actingAs($userA)->get(route('audit.index'))
            ->assertOk()
            ->assertDontSee('Gita Kelas B');
    }

    public function test_laporan_kelas_a_tidak_mencampur_angka_kelas_b(): void
    {
        [$kelasA, $userA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB, $userB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $siswaA = $this->buatSiswa($kelasA, 'Adinda Kelas A');
        $siswaB = $this->buatSiswa($kelasB, 'Fajar Kelas B');

        $this->actingAs($userA)->post(route('pembayaran.store'), [
            'student_id' => $siswaA->id, 'tanggal' => '2026-01-10', 'jumlah' => 5000, 'metode' => 'tunai',
        ]);
        $this->actingAs($userB)->post(route('pembayaran.store'), [
            'student_id' => $siswaB->id, 'tanggal' => '2026-01-10', 'jumlah' => 90000, 'metode' => 'tunai',
        ]);

        $kas = app(KasService::class);

        $this->assertSame(500000, CurrentClassroom::runFor($kelasA, fn () => $kas->ringkasan($kelasA)['saldo']));
        $this->assertSame(9000000, CurrentClassroom::runFor($kelasB, fn () => $kas->ringkasan($kelasB)['saldo']));

        $this->flushSession();

        $this->actingAs($userA)->get(route('laporan.index'))
            ->assertOk()
            ->assertDontSee('Fajar Kelas B')
            ->assertDontSee('Rp 90.000');
    }

    public function test_riwayat_transaksi_tersaring_tanggal(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id, 'tanggal' => '2026-01-10', 'jumlah' => 5000, 'metode' => 'tunai',
        ]);
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id, 'tanggal' => '2026-03-10', 'jumlah' => 7000, 'metode' => 'tunai',
        ]);

        $kas = app(KasService::class);

        $januari = CurrentClassroom::runFor(
            $kelas,
            fn () => $kas->riwayatTransaksi('2026-01-01', '2026-01-31')
        );

        $this->assertCount(1, $januari);
        $this->assertSame(500000, $januari->first()['jumlah']);

        $this->assertCount(2, CurrentClassroom::runFor($kelas, fn () => $kas->riwayatTransaksi()));
        $this->assertSame(0, $this->dalamKelas($kelas, fn () => Expense::count()));
        $this->assertSame(2, $this->dalamKelas($kelas, fn () => Payment::count()));
    }
}
