<?php

namespace Tests\Feature;

use App\Exceptions\PeriodeTerkunciException;
use App\Models\AuditLog;
use App\Models\BookClosing;
use App\Models\Classroom;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\User;
use App\Support\Uang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tutup buku & serah terima (Fase 9 / v1.2).
 *
 * Fase ini sulit diuji lewat pemakaian nyata — datanya baru terkumpul beberapa
 * minggu, sedangkan tutup buku baru terasa gunanya setelah satu semester. Karena
 * itu test di berkas ini sengaja lebih rewel dari fase lain, terutama bagian
 * penguncian: kalau kunci bisa ditembus lewat satu jalur saja, seluruh gunanya
 * hilang dan tidak ada yang akan sadar sampai sengketa terjadi.
 *
 * Penguncian diuji dari DUA arah:
 *   1. lewat HTTP, seperti bendahara sungguhan;
 *   2. langsung ke model, mewakili jalur tulis mana pun yang ditambahkan nanti.
 */
class TutupBukuTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Snapshot
    |--------------------------------------------------------------------------
    */

    public function test_snapshot_tutup_buku_cocok_dengan_hitungan_manual(): void
    {
        [$kelas, $user] = $this->siapkan();

        // Sebelum rentang — menentukan saldo awal.
        $this->catatPembayaran($user, '2025-12-10', 20000);
        $this->catatPengeluaran($user, '2025-12-15', 5000);

        // Di dalam rentang.
        $this->catatPembayaran($user, '2026-02-05', 15000);
        $this->catatPengeluaran($user, '2026-02-10', 6000);
        $this->catatPengeluaran($user, '2026-03-20', 1500);

        // Sesudah rentang — tidak boleh ikut terhitung.
        $this->catatPembayaran($user, '2026-04-02', 9000);

        $closing = $this->tutupBuku($user, '2026-01-01', '2026-03-31');

        // Hitung ulang manual lewat query mentah, bukan lewat service yang diuji.
        $manual = $this->hitungManual($kelas, '2026-01-01', '2026-03-31');

        $this->assertSame($manual['saldo_awal'], Uang::keSen($closing->saldo_awal), 'Saldo awal harus sama dengan hitungan manual.');
        $this->assertSame($manual['total_masuk'], Uang::keSen($closing->total_masuk), 'Total masuk harus sama dengan hitungan manual.');
        $this->assertSame($manual['total_keluar'], Uang::keSen($closing->total_keluar), 'Total keluar harus sama dengan hitungan manual.');
        $this->assertSame($manual['saldo_akhir'], Uang::keSen($closing->saldo_akhir), 'Saldo akhir harus sama dengan hitungan manual.');

        // Angka konkretnya, supaya kekeliruan tanda/pembulatan tidak lolos hanya
        // karena hitungan manual dan service sama-sama salah dengan cara yang sama.
        $this->assertSame(1500000, Uang::keSen($closing->saldo_awal));   // 20.000 - 5.000
        $this->assertSame(1500000, Uang::keSen($closing->total_masuk));  // 15.000
        $this->assertSame(750000, Uang::keSen($closing->total_keluar));  // 6.000 + 1.500
        $this->assertSame(2250000, Uang::keSen($closing->saldo_akhir));  // 15.000 + 15.000 - 7.500
    }

    public function test_snapshot_tidak_ikut_berubah_saat_ada_transaksi_baru(): void
    {
        [$kelas, $user] = $this->siapkan();

        $this->catatPembayaran($user, '2026-02-05', 15000);
        $closing = $this->tutupBuku($user, '2026-01-01', '2026-03-31');

        $saldoAkhirSaatDitutup = $closing->saldo_akhir;

        // Transaksi baru DI LUAR rentang: saldo berjalan berubah, snapshot tidak.
        $this->catatPembayaran($user, '2026-04-10', 50000);

        $this->assertSame(
            Uang::keSen($saldoAkhirSaatDitutup),
            Uang::keSen($closing->fresh()->saldo_akhir),
            'Snapshot adalah catatan historis — angkanya tidak boleh ikut bergerak.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Penguncian — lewat HTTP
    |--------------------------------------------------------------------------
    */

    public function test_pembayaran_baru_bertanggal_di_rentang_tertutup_ditolak(): void
    {
        [$kelas, $user] = $this->siapkan();
        $siswa = $this->siswa($kelas);
        $this->tutupBuku($user, '2026-01-01', '2026-03-31');

        $sebelum = $this->dalamKelas($kelas, fn () => Payment::count());

        $response = $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => '2026-02-20',
            'jumlah' => 5000,
            'metode' => 'tunai',
        ]);

        $response->assertSessionHasErrors('tanggal');
        $this->assertSame($sebelum, $this->dalamKelas($kelas, fn () => Payment::count()),
            'Tidak satu pun pembayaran boleh tersimpan di rentang yang sudah ditutup.');

        // Pesannya wajib menyebut rentang mana yang mengunci — "ditolak" saja
        // membuat bendahara mencoba lagi dengan tanggal yang sama.
        $pesan = session('errors')->first('tanggal');
        $this->assertStringContainsString('Semester Ganjil', $pesan);
        $this->assertStringContainsString('penyesuaian', $pesan, 'Pesan harus menyebutkan jalan keluarnya.');
    }

    public function test_hapus_pembayaran_di_rentang_tertutup_ditolak_server(): void
    {
        [$kelas, $user] = $this->siapkan();
        $this->catatPembayaran($user, '2026-02-05', 15000);

        $payment = $this->dalamKelas($kelas, fn () => Payment::firstOrFail());
        $this->tutupBuku($user, '2026-01-01', '2026-03-31');

        $this->actingAs($user)
            ->delete(route('pembayaran.destroy', $payment->id))
            ->assertRedirect();

        $this->assertNotNull(
            $this->dalamKelas($kelas, fn () => Payment::find($payment->id)),
            'Pembayaran di rentang tertutup tidak boleh terhapus.'
        );
    }

    public function test_hapus_pengeluaran_di_rentang_tertutup_ditolak_server(): void
    {
        [$kelas, $user] = $this->siapkan();
        $this->catatPembayaran($user, '2026-02-05', 15000);
        $this->catatPengeluaran($user, '2026-02-10', 6000);

        $expense = $this->dalamKelas($kelas, fn () => Expense::firstOrFail());
        $this->tutupBuku($user, '2026-01-01', '2026-03-31');

        $this->actingAs($user)
            ->delete(route('pengeluaran.destroy', $expense->id))
            ->assertRedirect();

        $this->assertNotNull(
            $this->dalamKelas($kelas, fn () => Expense::find($expense->id)),
            'Pengeluaran di rentang tertutup tidak boleh terhapus.'
        );
    }

    public function test_ubah_pengeluaran_di_rentang_tertutup_ditolak_server(): void
    {
        [$kelas, $user] = $this->siapkan();
        $this->catatPembayaran($user, '2026-02-05', 15000);
        $this->catatPengeluaran($user, '2026-02-10', 6000);

        $expense = $this->dalamKelas($kelas, fn () => Expense::firstOrFail());
        $this->tutupBuku($user, '2026-01-01', '2026-03-31');

        $this->actingAs($user)->put(route('pengeluaran.update', $expense->id), [
            'tanggal' => '2026-02-10',
            'category_id' => $expense->category_id,
            'jumlah' => 9999,
            'keterangan' => 'Diubah diam-diam',
        ])->assertSessionHasErrors('tanggal');

        $this->assertSame(
            600000,
            Uang::keSen($this->dalamKelas($kelas, fn () => Expense::findOrFail($expense->id))->jumlah),
            'Nominal pengeluaran di rentang tertutup harus tetap seperti semula.'
        );
    }

    public function test_pengeluaran_tertutup_tidak_bisa_dipindahkan_keluar_dari_rentang(): void
    {
        [$kelas, $user] = $this->siapkan();
        $this->catatPembayaran($user, '2026-02-05', 15000);
        $this->catatPengeluaran($user, '2026-02-10', 6000);

        $expense = $this->dalamKelas($kelas, fn () => Expense::firstOrFail());
        $this->tutupBuku($user, '2026-01-01', '2026-03-31');

        // Memindahkan tanggalnya ke luar rentang juga termasuk mengubah sejarah:
        // saldo periode yang sudah ditandatangani akan berubah.
        $this->actingAs($user)->put(route('pengeluaran.update', $expense->id), [
            'tanggal' => '2026-05-01',
            'category_id' => $expense->category_id,
            'jumlah' => 6000,
            'keterangan' => 'Dipindah keluar',
        ])->assertSessionHasErrors('tanggal');

        $this->assertSame(
            '2026-02-10',
            $this->dalamKelas($kelas, fn () => Expense::findOrFail($expense->id))->tanggal->toDateString(),
            'Tanggal pengeluaran di rentang tertutup tidak boleh bergeser.'
        );
    }

    public function test_transaksi_di_luar_rentang_tertutup_tetap_bisa_diubah(): void
    {
        [$kelas, $user] = $this->siapkan();
        $this->catatPembayaran($user, '2026-02-05', 15000);
        $this->tutupBuku($user, '2026-01-01', '2026-03-31');

        // Pembayaran baru sesudah rentang tertutup — harus lolos seperti biasa.
        $this->catatPembayaran($user, '2026-04-05', 20000);
        $this->catatPengeluaran($user, '2026-04-06', 7000);

        $expense = $this->dalamKelas($kelas, fn () => Expense::whereDate('tanggal', '2026-04-06')->firstOrFail());

        $this->actingAs($user)->put(route('pengeluaran.update', $expense->id), [
            'tanggal' => '2026-04-07',
            'category_id' => $expense->category_id,
            'jumlah' => 8000,
            'keterangan' => 'Beli spidol lagi',
        ])->assertSessionHasNoErrors();

        $segar = $this->dalamKelas($kelas, fn () => Expense::findOrFail($expense->id));
        $this->assertSame(800000, Uang::keSen($segar->jumlah));
        $this->assertSame('2026-04-07', $segar->tanggal->toDateString());

        $this->actingAs($user)
            ->delete(route('pengeluaran.destroy', $expense->id))
            ->assertRedirect(route('pengeluaran.index'));

        $this->assertNull(
            $this->dalamKelas($kelas, fn () => Expense::find($expense->id)),
            'Pengeluaran di luar rentang tertutup harus tetap bisa dihapus.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Penguncian — langsung ke model
    |--------------------------------------------------------------------------
    | Mewakili jalur tulis yang belum ada hari ini. Kalau kuncinya cuma dipasang
    | di controller, test di bagian ini yang akan merah lebih dulu.
    */

    public function test_model_menolak_ubah_pembayaran_di_rentang_tertutup(): void
    {
        [$kelas, $user] = $this->siapkan();
        $this->catatPembayaran($user, '2026-02-05', 15000);
        $this->tutupBuku($user, '2026-01-01', '2026-03-31');

        $this->expectException(PeriodeTerkunciException::class);

        $this->dalamKelas($kelas, function () {
            Payment::firstOrFail()->update(['jumlah' => '99999.00']);
        });
    }

    public function test_model_menolak_hapus_alokasi_pembayaran_di_rentang_tertutup(): void
    {
        [$kelas, $user] = $this->siapkan();
        $this->catatPembayaran($user, '2026-02-05', 15000);
        $this->tutupBuku($user, '2026-01-01', '2026-03-31');

        $this->expectException(PeriodeTerkunciException::class);

        $this->dalamKelas($kelas, function () {
            PaymentAllocation::firstOrFail()->delete();
        });
    }

    public function test_alokasi_deposit_dari_pembayaran_baru_tetap_boleh_berjalan(): void
    {
        [$kelas, $user] = $this->siapkan();
        $this->tutupBuku($user, '2026-01-01', '2026-01-31');

        // Pembayaran bertanggal di luar rentang tertutup, tapi tagihan yang
        // ditutupinya (Januari) ada DI DALAM rentang itu. Ini harus tetap jalan:
        // yang dikunci adalah uang yang sudah tercatat, bukan kemampuan menagih
        // tunggakan lama. Kalau ini ikut terkunci, tunggakan periode tertutup
        // mustahil dilunasi selamanya.
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $this->siswa($kelas)->id,
            'tanggal' => '2026-04-05',
            'jumlah' => 15000,
            'metode' => 'tunai',
        ])->assertSessionHasNoErrors();

        $this->assertGreaterThan(
            0,
            $this->dalamKelas($kelas, fn () => PaymentAllocation::count()),
            'Pembayaran baru harus tetap bisa melunasi tagihan periode yang sudah ditutup.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Buka kembali
    |--------------------------------------------------------------------------
    */

    public function test_buka_kembali_closing_terakhir_membuka_lagi_penguncian(): void
    {
        [$kelas, $user] = $this->siapkan();
        $this->catatPembayaran($user, '2026-02-05', 15000);
        $this->catatPengeluaran($user, '2026-02-10', 6000);

        $expense = $this->dalamKelas($kelas, fn () => Expense::firstOrFail());
        $closing = $this->tutupBuku($user, '2026-01-01', '2026-03-31');

        $this->actingAs($user)
            ->delete(route('tutup-buku.destroy', $closing->id))
            ->assertRedirect(route('tutup-buku.index'));

        $this->assertNull(
            $this->dalamKelas($kelas, fn () => BookClosing::find($closing->id)),
            'Closing yang dibuka kembali harus hilang dari daftar.'
        );

        // Yang tadinya ditolak sekarang harus lolos.
        $this->actingAs($user)->put(route('pengeluaran.update', $expense->id), [
            'tanggal' => '2026-02-10',
            'category_id' => $expense->category_id,
            'jumlah' => 7000,
            'keterangan' => 'Dikoreksi setelah buku dibuka',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            700000,
            Uang::keSen($this->dalamKelas($kelas, fn () => Expense::findOrFail($expense->id))->jumlah)
        );
    }

    public function test_buka_kembali_tercatat_di_audit_log_beserta_pelakunya(): void
    {
        [$kelas, $user] = $this->siapkan();
        $this->catatPembayaran($user, '2026-02-05', 15000);
        $closing = $this->tutupBuku($user, '2026-01-01', '2026-03-31');

        $this->actingAs($user)->delete(route('tutup-buku.destroy', $closing->id))->assertRedirect();

        $jejak = $this->dalamKelas($kelas, fn () => AuditLog::where('aksi', 'reopen_book')->firstOrFail());

        $this->assertSame($user->id, $jejak->user_id, 'Audit log harus menyebut siapa yang membuka.');
        $this->assertSame($kelas->id, $jejak->classroom_id);
        $this->assertSame('Semester Ganjil 2025/2026', $jejak->data_lama['label']);

        // Penutupannya sendiri juga harus punya jejak.
        $this->assertTrue(
            $this->dalamKelas($kelas, fn () => AuditLog::where('aksi', 'close_book')->exists()),
            'Tutup buku wajib tercatat di audit log.'
        );
    }

    public function test_closing_yang_bukan_terakhir_tidak_bisa_dibuka(): void
    {
        [$kelas, $user] = $this->siapkan();
        $this->catatPembayaran($user, '2026-02-05', 15000);

        $pertama = $this->tutupBuku($user, '2026-01-01', '2026-03-31');
        $kedua = $this->tutupBuku($user, '2026-04-01', '2026-06-30', 'Semester Genap 2025/2026');

        $this->actingAs($user)
            ->delete(route('tutup-buku.destroy', $pertama->id))
            ->assertRedirect();

        $this->assertNotNull(
            $this->dalamKelas($kelas, fn () => BookClosing::find($pertama->id)),
            'Closing di tengah tidak boleh bisa dibuka.'
        );
        $this->assertNotNull($this->dalamKelas($kelas, fn () => BookClosing::find($kedua->id)));

        // Yang terakhir tetap boleh dibuka.
        $this->actingAs($user)->delete(route('tutup-buku.destroy', $kedua->id))->assertRedirect();
        $this->assertNull($this->dalamKelas($kelas, fn () => BookClosing::find($kedua->id)));
    }

    public function test_rentang_yang_tumpang_tindih_ditolak(): void
    {
        [$kelas, $user] = $this->siapkan();
        $this->tutupBuku($user, '2026-01-01', '2026-03-31');

        $this->actingAs($user)->post(route('tutup-buku.store'), [
            'label' => 'Rentang menabrak',
            'tgl_mulai' => '2026-03-01',
            'tgl_selesai' => '2026-05-31',
        ])->assertSessionHas('galat');

        $this->assertSame(1, $this->dalamKelas($kelas, fn () => BookClosing::count()));
    }

    /*
    |--------------------------------------------------------------------------
    | Laporan serah terima
    |--------------------------------------------------------------------------
    */

    public function test_laporan_serah_terima_terunduh(): void
    {
        [$kelas, $user] = $this->siapkan();
        $this->catatPembayaran($user, '2026-02-05', 15000);
        $this->catatPengeluaran($user, '2026-02-10', 6000);

        $closing = $this->tutupBuku($user, '2026-01-01', '2026-03-31');

        $response = $this->actingAs($user)->get(route('tutup-buku.serah-terima', $closing->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_halaman_tutup_buku_butuh_login(): void
    {
        // buatKelas() sengaja dipakai langsung: siapkan() melakukan actingAs,
        // dan sesi itu akan terbawa sehingga test ini berhenti menguji apa pun.
        [, $user] = $this->buatKelas();

        $this->get(route('tutup-buku.index'))->assertRedirect(route('login'));
        $this->post(route('tutup-buku.store'), ['label' => 'X'])->assertRedirect(route('login'));
        $this->post(route('tutup-buku.transfer'), ['email' => 'x@y.test'])->assertRedirect(route('login'));

        $this->actingAs($user)->get(route('tutup-buku.index'))->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Alih kepemilikan
    |--------------------------------------------------------------------------
    */

    public function test_alih_kepemilikan_memindahkan_akses_sepenuhnya(): void
    {
        [$kelas, $lama] = $this->siapkan();

        $this->actingAs($lama)->post(route('tutup-buku.transfer'), [
            'email' => 'bendahara-baru@kaskelas.test',
            'nama' => 'Bendahara Baru',
            'password' => 'RahasiaKuat123',
            'password_confirmation' => 'RahasiaKuat123',
            'konfirmasi' => '1',
        ])->assertSessionHasNoErrors();

        $baru = User::where('email', 'bendahara-baru@kaskelas.test')->firstOrFail();

        $this->assertSame($baru->id, $kelas->fresh()->owner_id, 'owner_id harus pindah.');
        $this->assertTrue($baru->classrooms()->where('classrooms.id', $kelas->id)->exists(),
            'Bendahara baru harus terhubung lewat classroom_user.');
        $this->assertFalse($lama->fresh()->classrooms()->where('classrooms.id', $kelas->id)->exists(),
            'Bendahara lama harus kehilangan akses.');

        // Bendahara lama tidak punya kelas lain, jadi tidak boleh bisa masuk ke
        // dashboard kelas mana pun.
        $this->actingAs($lama)->get(route('dashboard'))->assertForbidden();

        // Bendahara baru mendapat akses penuh.
        $this->actingAs($baru)->get(route('dashboard'))->assertOk();
        $this->actingAs($baru)->get(route('pengaturan.edit'))->assertOk();
    }

    public function test_alih_kepemilikan_ke_akun_yang_sudah_terdaftar(): void
    {
        [$kelas, $lama] = $this->siapkan();
        [, $calon] = $this->buatKelas('XI IPA 2', 'SMAN 3 Bandung');

        $this->actingAs($lama)->post(route('tutup-buku.transfer'), [
            'email' => $calon->email,
            'konfirmasi' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame($calon->id, $kelas->fresh()->owner_id);
        $this->assertTrue($calon->classrooms()->where('classrooms.id', $kelas->id)->exists());
        $this->assertFalse($lama->fresh()->classrooms()->where('classrooms.id', $kelas->id)->exists());
    }

    public function test_alih_kepemilikan_tercatat_di_audit_log(): void
    {
        [$kelas, $lama] = $this->siapkan();

        $this->actingAs($lama)->post(route('tutup-buku.transfer'), [
            'email' => 'penerus@kaskelas.test',
            'nama' => 'Penerus',
            'password' => 'RahasiaKuat123',
            'password_confirmation' => 'RahasiaKuat123',
            'konfirmasi' => '1',
        ])->assertSessionHasNoErrors();

        $baru = User::where('email', 'penerus@kaskelas.test')->firstOrFail();

        $jejak = $this->dalamKelas($kelas, fn () => AuditLog::where('aksi', 'transfer_owner')->firstOrFail());

        $this->assertSame($lama->id, $jejak->user_id, 'Pelakunya adalah bendahara lama.');
        $this->assertSame($kelas->id, $jejak->classroom_id);
        $this->assertSame($lama->id, $jejak->data_lama['owner_id']);
        $this->assertSame($baru->id, $jejak->data_baru['owner_id']);
    }

    public function test_alih_kepemilikan_tanpa_konfirmasi_ditolak(): void
    {
        [$kelas, $lama] = $this->siapkan();

        $this->actingAs($lama)->post(route('tutup-buku.transfer'), [
            'email' => 'penerus@kaskelas.test',
            'nama' => 'Penerus',
            'password' => 'RahasiaKuat123',
            'password_confirmation' => 'RahasiaKuat123',
        ])->assertSessionHasErrors('konfirmasi');

        $this->assertSame($lama->id, $kelas->fresh()->owner_id, 'Kepemilikan tidak boleh pindah.');
        $this->assertFalse(User::where('email', 'penerus@kaskelas.test')->exists(),
            'Akun baru tidak boleh telanjur dibuat saat validasinya gagal.');
    }

    /*
    |--------------------------------------------------------------------------
    | Isolasi tenant
    |--------------------------------------------------------------------------
    */

    public function test_closing_kelas_a_tidak_memengaruhi_kelas_b(): void
    {
        [$kelasA, $userA] = $this->siapkan();
        [$kelasB, $userB] = $this->siapkan('XI IPA 3', 'SMAN 2 Bandung', 'Budi');

        $this->catatPembayaran($userA, '2026-02-05', 15000);
        $this->catatPembayaran($userB, '2026-02-05', 15000);

        // Label sengaja dibuat khas: teks bantuan di formulir memakai contoh
        // "Semester Ganjil 2025/2026", jadi label bawaan tidak bisa membuktikan
        // ada-tidaknya kebocoran.
        $this->tutupBuku($userA, '2026-01-01', '2026-03-31', 'Rahasia Kelas A');

        // Kelas B tidak ikut terkunci meskipun tanggalnya sama persis.
        $this->actingAs($userB)->post(route('pembayaran.store'), [
            'student_id' => $this->siswa($kelasB)->id,
            'tanggal' => '2026-02-20',
            'jumlah' => 5000,
            'metode' => 'tunai',
        ])->assertSessionHasNoErrors();

        // Kelas A tetap terkunci di tanggal yang sama.
        $this->actingAs($userA)->post(route('pembayaran.store'), [
            'student_id' => $this->siswa($kelasA)->id,
            'tanggal' => '2026-02-20',
            'jumlah' => 5000,
            'metode' => 'tunai',
        ])->assertSessionHasErrors('tanggal');

        // Daftar closing kelas B kosong, dan halamannya tidak membocorkan milik A.
        $this->assertSame(0, $this->dalamKelas($kelasB, fn () => BookClosing::count()));
        $this->actingAs($userB)->get(route('tutup-buku.index'))
            ->assertOk()
            ->assertDontSee('Rahasia Kelas A');
    }

    public function test_bendahara_kelas_b_tidak_bisa_menyentuh_closing_kelas_a(): void
    {
        [, $userA] = $this->siapkan();
        [$kelasB, $userB] = $this->siapkan('XI IPA 3', 'SMAN 2 Bandung', 'Budi');

        $this->catatPembayaran($userA, '2026-02-05', 15000);
        $closingA = $this->tutupBuku($userA, '2026-01-01', '2026-03-31');

        // ID milik kelas lain berakhir 404, bukan data orang lain.
        $this->actingAs($userB)->delete(route('tutup-buku.destroy', $closingA->id))->assertNotFound();
        $this->actingAs($userB)->get(route('tutup-buku.serah-terima', $closingA->id))->assertNotFound();

        $this->assertNotNull(
            $this->dalamKelas($kelasB, fn () => BookClosing::withoutGlobalScopes()->find($closingA->id)),
            'Closing kelas A harus tetap utuh.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pembantu
    |--------------------------------------------------------------------------
    */

    /**
     * Kelas + satu siswa + periode Januari–Juni 2026 @ Rp 5.000.
     *
     * @return array{0: Classroom, 1: User}
     */
    private function siapkan(
        string $namaKelas = 'XII TRPL 1',
        string $sekolah = 'SMKN 1 Subang',
        string $namaSiswa = 'Adinda',
    ): array {
        [$kelas, $user] = $this->buatKelas($namaKelas, $sekolah);
        $this->buatSiswa($kelas, $namaSiswa, ['tgl_mulai_aktif' => '2025-12-01']);

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-06-30',
        ])->assertSessionHasNoErrors();

        return [$kelas, $user];
    }

    private function siswa(Classroom $kelas): Student
    {
        return $this->dalamKelas($kelas, fn () => Student::firstOrFail());
    }

    private function catatPembayaran(User $user, string $tanggal, int $jumlah): void
    {
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $this->siswa($user->classrooms()->firstOrFail())->id,
            'tanggal' => $tanggal,
            'jumlah' => $jumlah,
            'metode' => 'tunai',
        ])->assertSessionHasNoErrors();
    }

    private function catatPengeluaran(User $user, string $tanggal, int $jumlah): void
    {
        $kelas = $user->classrooms()->firstOrFail();
        $kategori = $this->dalamKelas($kelas, fn () => ExpenseCategory::whereNull('classroom_id')->firstOrFail());

        $this->actingAs($user)->post(route('pengeluaran.store'), [
            'tanggal' => $tanggal,
            'category_id' => $kategori->id,
            'jumlah' => $jumlah,
            'keterangan' => 'Pengeluaran uji '.$tanggal,
        ])->assertSessionHasNoErrors();
    }

    private function tutupBuku(
        User $user,
        string $dari,
        string $sampai,
        string $label = 'Semester Ganjil 2025/2026',
    ): BookClosing {
        $this->actingAs($user)->post(route('tutup-buku.store'), [
            'label' => $label,
            'tgl_mulai' => $dari,
            'tgl_selesai' => $sampai,
        ])->assertSessionHasNoErrors()->assertSessionMissing('galat');

        $kelas = $user->classrooms()->firstOrFail();

        return $this->dalamKelas($kelas, fn () => BookClosing::where('label', $label)->firstOrFail());
    }

    /**
     * Hitungan pembanding, sengaja memakai query mentah tanpa global scope dan
     * tanpa service yang sedang diuji.
     *
     * @return array{saldo_awal: int, total_masuk: int, total_keluar: int, saldo_akhir: int}
     */
    private function hitungManual(Classroom $kelas, string $dari, string $sampai): array
    {
        $jumlah = fn (string $tabel, ?string $awal, string $akhir) => Uang::keSen((string) DB::table($tabel)
            ->where('classroom_id', $kelas->id)
            ->whereNull('deleted_at')
            ->when($awal, fn ($q) => $q->whereDate('tanggal', '>=', $awal))
            ->whereDate('tanggal', '<=', $akhir)
            ->sum('jumlah'));

        $sebelum = date('Y-m-d', strtotime($dari.' -1 day'));

        $saldoAwal = $jumlah('payments', null, $sebelum) - $jumlah('expenses', null, $sebelum);
        $masuk = $jumlah('payments', $dari, $sampai);
        $keluar = $jumlah('expenses', $dari, $sampai);

        return [
            'saldo_awal' => $saldoAwal,
            'total_masuk' => $masuk,
            'total_keluar' => $keluar,
            'saldo_akhir' => $saldoAwal + $masuk - $keluar,
        ];
    }
}
