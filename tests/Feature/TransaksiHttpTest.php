<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Period;
use App\Services\KasService;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransaksiHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_pembayaran_lewat_form_teralokasi_otomatis(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-03-31',
        ]);

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => '2026-02-05',
            'jumlah' => 12000,
            'metode' => 'tunai',
        ])->assertRedirect(route('pembayaran.index'));

        $kas = app(KasService::class);

        $status = $this->dalamKelas($kelas, fn () => Bill::with(['period', 'allocations'])
            ->get()
            ->mapWithKeys(fn (Bill $b) => [$b->period->label => $kas->statusTagihan($b)])
            ->all());

        $this->assertSame('lunas', $status['Januari 2026']);
        $this->assertSame('lunas', $status['Februari 2026']);
        $this->assertSame('kurang', $status['Maret 2026']);
    }

    public function test_alokasi_manual_dipakai_saat_dipilih(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-03-31',
        ]);

        $maret = $this->dalamKelas($kelas, fn () => Bill::with('period')->get()
            ->firstWhere(fn (Bill $b) => $b->period->label === 'Maret 2026'));

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => '2026-02-05',
            'jumlah' => 5000,
            'metode' => 'tunai',
            'mode_alokasi' => 'manual',
            'alokasi' => [$maret->id => 5000],
        ])->assertRedirect(route('pembayaran.index'));

        $kas = app(KasService::class);

        $status = $this->dalamKelas($kelas, fn () => Bill::with(['period', 'allocations'])
            ->get()
            ->mapWithKeys(fn (Bill $b) => [$b->period->label => $kas->statusTagihan($b)])
            ->all());

        $this->assertSame('belum', $status['Januari 2026']);
        $this->assertSame('lunas', $status['Maret 2026']);
    }

    public function test_bukti_disimpan_privat_per_kelas_dengan_nama_acak(): void
    {
        Storage::fake('local');

        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');
        $this->buatPeriode($kelas);

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => '2026-02-05',
            'jumlah' => 5000,
            'metode' => 'transfer',
            'bukti' => UploadedFile::fake()->image('bukti transfer asli.jpg'),
        ])->assertRedirect(route('pembayaran.index'));

        $path = $this->dalamKelas($kelas, fn () => Payment::firstOrFail()->bukti_path);

        $this->assertStringStartsWith('kelas-'.$kelas->id.'/', $path);
        $this->assertStringNotContainsString('bukti transfer asli', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_bukti_kelas_lain_tidak_bisa_diunduh(): void
    {
        Storage::fake('local');

        [$kelasA, $userA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB, $userB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');
        $siswaB = $this->buatSiswa($kelasB, 'Fajar');

        // Kedua kelas disiapkan penuh: route /pembayaran/... ada di balik
        // middleware 'siap', dan yang harus diuji di sini adalah 404 karena
        // isolasi tenant -- bukan 302 karena wizard belum selesai.
        $this->buatSiswa($kelasA, 'Adinda');
        $this->buatPeriode($kelasA);
        $this->buatPeriode($kelasB);

        $this->actingAs($userB)->post(route('pembayaran.store'), [
            'student_id' => $siswaB->id,
            'tanggal' => '2026-02-05',
            'jumlah' => 5000,
            'metode' => 'transfer',
            'bukti' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $pembayaranB = $this->dalamKelas($kelasB, fn () => Payment::firstOrFail());

        $this->actingAs($userA)->get(route('pembayaran.bukti', $pembayaranB))->assertNotFound();
        $this->actingAs($userB)->get(route('pembayaran.bukti', $pembayaranB))->assertOk();
    }

    public function test_upload_berkas_berbahaya_ditolak(): void
    {
        Storage::fake('local');

        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');
        $this->buatPeriode($kelas);

        // Berkas skrip yang menyamar sebagai unggahan biasa: ditolak baik oleh
        // aturan ekstensi (mimes) maupun aturan jenis isi (mimetypes).
        $berkas = UploadedFile::fake()->create('skrip.php', 10, 'application/x-httpd-php');

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => '2026-02-05',
            'jumlah' => 5000,
            'metode' => 'transfer',
            'bukti' => $berkas,
        ])->assertSessionHasErrors('bukti');

        $this->assertSame(0, $this->dalamKelas($kelas, fn () => Payment::count()));
    }

    public function test_pengeluaran_melebihi_saldo_kas_ditolak_server(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');
        $this->buatPeriode($kelas, '2026-02-01', 5000, '2026-02-28');

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id, 'tanggal' => '2026-02-05', 'jumlah' => 10000, 'metode' => 'tunai',
        ]);

        $kategori = $this->dalamKelas($kelas, fn () => ExpenseCategory::whereNull('classroom_id')->firstOrFail());

        $this->actingAs($user)->post(route('pengeluaran.store'), [
            'tanggal' => '2026-02-06',
            'category_id' => $kategori->id,
            'jumlah' => 15000,
            'keterangan' => 'beli proyektor',
        ])->assertSessionHasErrors('jumlah');

        $this->assertSame(0, $this->dalamKelas($kelas, fn () => Expense::count()));

        $this->actingAs($user)->post(route('pengeluaran.store'), [
            'tanggal' => '2026-02-06',
            'category_id' => $kategori->id,
            'jumlah' => 4000,
            'keterangan' => 'beli spidol',
        ])->assertRedirect(route('pengeluaran.index'));

        $this->assertSame(1, $this->dalamKelas($kelas, fn () => Expense::count()));
    }

    public function test_pembebasan_tagihan_wajib_menyertakan_alasan(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-01-31',
        ]);

        $bill = $this->dalamKelas($kelas, fn () => Bill::firstOrFail());

        $this->actingAs($user)
            ->from(route('siswa.show', $siswa))
            ->patch(route('tagihan.bebas', $bill), ['bebas' => '1'])
            ->assertSessionHasErrors('alasan_bebas');

        $this->assertFalse($this->dalamKelas($kelas, fn () => Bill::find($bill->id)->is_bebas));

        $this->actingAs($user)->patch(route('tagihan.bebas', $bill), [
            'bebas' => '1',
            'alasan_bebas' => 'keringanan dari wali kelas',
        ]);

        $this->assertTrue($this->dalamKelas($kelas, fn () => Bill::find($bill->id)->is_bebas));
    }

    /** Test isolasi wajib untuk modul pembayaran & pengeluaran (PRD 14.1). */
    public function test_bendahara_a_tidak_bisa_menyentuh_transaksi_kelas_b(): void
    {
        [$kelasA, $userA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB, $userB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $siswaB = $this->buatSiswa($kelasB, 'Fajar Kelas B');

        // Kelas A juga disiapkan penuh, supaya penolakan yang diuji di bawah
        // benar-benar 404 isolasi tenant dan bukan pantulan wizard.
        $this->buatSiswa($kelasA, 'Adinda Kelas A');
        $this->buatPeriode($kelasA);

        $this->actingAs($userB)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-01-31',
        ]);
        $this->actingAs($userB)->post(route('pembayaran.store'), [
            'student_id' => $siswaB->id, 'tanggal' => '2026-01-10', 'jumlah' => 5000, 'metode' => 'tunai',
        ]);

        $kategori = $this->dalamKelas($kelasB, fn () => ExpenseCategory::whereNull('classroom_id')->firstOrFail());
        $this->actingAs($userB)->post(route('pengeluaran.store'), [
            'tanggal' => '2026-01-11', 'category_id' => $kategori->id,
            'jumlah' => 2000, 'keterangan' => 'spidol kelas B',
        ]);

        $pembayaranB = $this->dalamKelas($kelasB, fn () => Payment::firstOrFail());
        $pengeluaranB = $this->dalamKelas($kelasB, fn () => Expense::firstOrFail());
        $tagihanB = $this->dalamKelas($kelasB, fn () => Bill::firstOrFail());

        // Kelas A mencoba menyentuh ketiganya.
        $this->actingAs($userA)->delete(route('pembayaran.destroy', $pembayaranB))->assertNotFound();
        $this->actingAs($userA)->get(route('pengeluaran.edit', $pengeluaranB))->assertNotFound();
        $this->actingAs($userA)->delete(route('pengeluaran.destroy', $pengeluaranB))->assertNotFound();
        $this->actingAs($userA)->patch(route('tagihan.bebas', $tagihanB), [
            'bebas' => '1', 'alasan_bebas' => 'dibajak',
        ])->assertNotFound();

        // Data kelas B tetap utuh.
        $this->assertSame(1, $this->dalamKelas($kelasB, fn () => Payment::count()));
        $this->assertSame(1, $this->dalamKelas($kelasB, fn () => Expense::count()));
        $this->assertFalse($this->dalamKelas($kelasB, fn () => Bill::find($tagihanB->id)->is_bebas));

        // Dan saldo kelas A tidak terpengaruh transaksi kelas B sama sekali.
        $kas = app(KasService::class);
        $this->assertSame(0, CurrentClassroom::runFor($kelasA, fn () => $kas->saldoKas()));
        $this->assertSame(300000, CurrentClassroom::runFor($kelasB, fn () => $kas->saldoKas()));
    }

    public function test_pembayaran_untuk_siswa_kelas_lain_ditolak_validasi(): void
    {
        [$kelasA, $userA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $siswaB = $this->buatSiswa($kelasB, 'Fajar Kelas B');

        $this->buatSiswa($kelasA, 'Adinda Kelas A');
        $this->buatPeriode($kelasA);

        $this->actingAs($userA)->post(route('pembayaran.store'), [
            'student_id' => $siswaB->id,
            'tanggal' => '2026-02-05',
            'jumlah' => 5000,
            'metode' => 'tunai',
        ])->assertSessionHasErrors('student_id');

        $this->assertSame(0, $this->dalamKelas($kelasB, fn () => Payment::count()));
    }

    public function test_hapus_pembayaran_memulihkan_saldo_dan_status(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-01-31',
        ]);
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id, 'tanggal' => '2026-01-10', 'jumlah' => 5000, 'metode' => 'tunai',
        ]);

        $payment = $this->dalamKelas($kelas, fn () => Payment::firstOrFail());

        $this->actingAs($user)->delete(route('pembayaran.destroy', $payment))
            ->assertRedirect(route('pembayaran.index'));

        $kas = app(KasService::class);

        $this->assertSame(0, CurrentClassroom::runFor($kelas, fn () => $kas->saldoKas()));
        $this->assertSame('belum', CurrentClassroom::runFor(
            $kelas,
            fn () => $kas->statusTagihan(Bill::with('allocations')->firstOrFail())
        ));
        // Soft delete: barisnya masih ada untuk jejak audit.
        $this->assertNotNull($this->dalamKelas($kelas, fn () => Payment::withTrashed()->find($payment->id)));
    }

    public function test_periode_yang_sudah_dibayar_tidak_bisa_ditandai_libur(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-01-31',
        ]);
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id, 'tanggal' => '2026-01-10', 'jumlah' => 5000, 'metode' => 'tunai',
        ]);

        $periode = $this->dalamKelas($kelas, fn () => Period::firstOrFail());

        $this->actingAs($user)
            ->from(route('periode.index'))
            ->patch(route('periode.libur', $periode), ['libur' => '1'])
            ->assertRedirect(route('periode.index'));

        $this->assertFalse($this->dalamKelas($kelas, fn () => Period::find($periode->id)->is_libur));
        $this->assertSame(1, $this->dalamKelas($kelas, fn () => Bill::count()));
    }
}
