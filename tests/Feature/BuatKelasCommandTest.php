<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Classroom;
use App\Models\Period;
use App\Models\Student;
use App\Models\User;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BuatKelasCommandTest extends TestCase
{
    use RefreshDatabase;

    /** Jawaban yang lolos semua validasi, dipakai sebagai dasar lalu ditimpa seperlunya. */
    private function jawaban(array $timpa = []): array
    {
        return array_replace([
            'nama' => 'Erlangga Haryo',
            'email' => 'bendahara-baru@kaskelas.test',
            'sandi' => 'RahasiaKuat123',
            'ulangi' => 'RahasiaKuat123',
            'nama_kelas' => 'XII TRPL 2',
            'sekolah' => 'SMKN 1 Subang',
            'tipe' => 'bulanan',
            'tgl_mulai' => '2026-01-01',
            'tgl_akhir' => '2026-03-31',
            'nominal' => '15000',
        ], $timpa);
    }

    private function jalankan(array $timpa = []): \Illuminate\Testing\PendingCommand
    {
        $j = $this->jawaban($timpa);

        return $this->artisan('kaskelas:buat-kelas')
            ->expectsQuestion('Nama bendahara', $j['nama'])
            ->expectsQuestion('Email bendahara', $j['email'])
            ->expectsQuestion('Kata sandi (minimal 8 karakter)', $j['sandi'])
            ->expectsQuestion('Ulangi kata sandi', $j['ulangi'])
            ->expectsQuestion('Nama kelas (mis. XII TRPL 1)', $j['nama_kelas'])
            ->expectsQuestion('Nama sekolah', $j['sekolah'])
            ->expectsChoice('Tipe periode iuran', $j['tipe'], ['bulanan', 'mingguan'])
            ->expectsQuestion('Tanggal mulai iuran (YYYY-MM-DD)', $j['tgl_mulai'])
            ->expectsQuestion('Tanggal akhir tahun ajaran (YYYY-MM-DD)', $j['tgl_akhir'])
            ->expectsQuestion('Nominal iuran per periode (per bulan)', $j['nominal']);
    }

    public function test_membuat_user_kelas_pivot_dan_periode_dalam_satu_jalan(): void
    {
        $this->jalankan()->assertSuccessful()->run();

        $user = User::where('email', 'bendahara-baru@kaskelas.test')->firstOrFail();
        $kelas = CurrentClassroom::withoutTenancy(
            fn () => Classroom::where('nama_kelas', 'XII TRPL 2')->firstOrFail()
        );

        $this->assertSame('Erlangga Haryo', $user->nama);
        $this->assertSame('bendahara', $user->role);
        $this->assertSame($user->id, $kelas->owner_id);
        $this->assertSame('SMKN 1 Subang', $kelas->sekolah);
        $this->assertSame('bulanan', $kelas->tipe_periode);

        $this->assertDatabaseHas('classroom_user', [
            'classroom_id' => $kelas->id,
            'user_id' => $user->id,
            'peran' => 'bendahara',
        ]);

        // Januari, Februari, Maret 2026 — lewat KasService, bukan logika baru.
        $periode = $this->dalamKelas($kelas, fn () => Period::urutWaktu()->pluck('label')->all());

        $this->assertSame(['Januari 2026', 'Februari 2026', 'Maret 2026'], $periode);
        $this->assertSame('15000.00', $this->dalamKelas($kelas, fn () => Period::first()->nominal));
    }

    public function test_kata_sandi_tersimpan_ter_hash_dan_bisa_dipakai_masuk(): void
    {
        $this->jalankan()->assertSuccessful()->run();

        $user = User::where('email', 'bendahara-baru@kaskelas.test')->firstOrFail();

        $this->assertNotSame('RahasiaKuat123', $user->password);
        // Kalau hash ganda terjadi, baris ini yang akan merah lebih dulu.
        $this->assertTrue(Hash::check('RahasiaKuat123', $user->password));

        $this->post(route('login.store'), [
            'email' => 'bendahara-baru@kaskelas.test',
            'password' => 'RahasiaKuat123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_public_token_empat_puluh_karakter_dan_url_publiknya_terbuka(): void
    {
        $this->jalankan()->assertSuccessful()->run();

        $kelas = CurrentClassroom::withoutTenancy(
            fn () => Classroom::where('nama_kelas', 'XII TRPL 2')->firstOrFail()
        );

        $this->assertSame(40, strlen($kelas->public_token));

        $this->get(route('publik.kelas', $kelas->public_token))
            ->assertOk()
            ->assertSee('XII TRPL 2');
    }

    public function test_email_yang_sudah_terdaftar_ditolak(): void
    {
        [, $userLama] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');

        $jumlahUserSebelum = User::count();
        $jumlahKelasSebelum = CurrentClassroom::withoutTenancy(fn () => Classroom::count());

        // Email dipakai tiga kali (batas percobaan), lalu perintah dibatalkan.
        $this->artisan('kaskelas:buat-kelas')
            ->expectsQuestion('Nama bendahara', 'Penyusup')
            ->expectsQuestion('Email bendahara', $userLama->email)
            ->expectsQuestion('Email bendahara', $userLama->email)
            ->expectsQuestion('Email bendahara', $userLama->email)
            ->assertFailed()
            ->run();

        $this->assertSame($jumlahUserSebelum, User::count());
        $this->assertSame($jumlahKelasSebelum, CurrentClassroom::withoutTenancy(fn () => Classroom::count()));
    }

    public function test_input_tidak_sah_ditanya_ulang_lalu_diterima(): void
    {
        $this->artisan('kaskelas:buat-kelas')
            ->expectsQuestion('Nama bendahara', '')
            ->expectsQuestion('Nama bendahara', 'Erlangga Haryo')
            ->expectsQuestion('Email bendahara', 'bukan-email')
            ->expectsQuestion('Email bendahara', 'bendahara-baru@kaskelas.test')
            ->expectsQuestion('Kata sandi (minimal 8 karakter)', 'pendek')
            ->expectsQuestion('Ulangi kata sandi', 'pendek')
            ->expectsQuestion('Kata sandi (minimal 8 karakter)', 'RahasiaKuat123')
            ->expectsQuestion('Ulangi kata sandi', 'SalahKetik123')
            ->expectsQuestion('Kata sandi (minimal 8 karakter)', 'RahasiaKuat123')
            ->expectsQuestion('Ulangi kata sandi', 'RahasiaKuat123')
            ->expectsQuestion('Nama kelas (mis. XII TRPL 1)', 'XII TRPL 2')
            ->expectsQuestion('Nama sekolah', 'SMKN 1 Subang')
            ->expectsChoice('Tipe periode iuran', 'mingguan', ['bulanan', 'mingguan'])
            ->expectsQuestion('Tanggal mulai iuran (YYYY-MM-DD)', '2026-01-05')
            // Tanggal akhir mendahului tanggal mulai → ditolak, lalu diperbaiki.
            ->expectsQuestion('Tanggal akhir tahun ajaran (YYYY-MM-DD)', '2025-12-01')
            ->expectsQuestion('Tanggal akhir tahun ajaran (YYYY-MM-DD)', '2026-02-01')
            ->expectsQuestion('Nominal iuran per periode (per minggu)', 'lima ribu')
            ->expectsQuestion('Nominal iuran per periode (per minggu)', '5000')
            ->assertSuccessful()
            ->run();

        $kelas = CurrentClassroom::withoutTenancy(
            fn () => Classroom::where('nama_kelas', 'XII TRPL 2')->firstOrFail()
        );

        $this->assertSame('mingguan', $kelas->tipe_periode);
        $this->assertGreaterThan(0, $this->dalamKelas($kelas, fn () => Period::count()));
    }

    public function test_kelas_baru_tidak_mengganggu_kelas_yang_sudah_ada(): void
    {
        [$kelasLama, $userLama] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        $siswaLama = $this->buatSiswa($kelasLama, 'Adinda Kelas Lama');

        $this->actingAs($userLama)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-01-31',
        ]);

        $tagihanLamaSebelum = $this->dalamKelas($kelasLama, fn () => Bill::count());

        $this->jalankan()->assertSuccessful()->run();

        $kelasBaru = CurrentClassroom::withoutTenancy(
            fn () => Classroom::where('nama_kelas', 'XII TRPL 2')->firstOrFail()
        );

        // Kelas baru kosong: tidak ikut menagih siswa milik kelas lain.
        $this->assertSame(0, $this->dalamKelas($kelasBaru, fn () => Student::count()));
        $this->assertSame(0, $this->dalamKelas($kelasBaru, fn () => Bill::count()));

        // Dan kelas lama sama sekali tidak tersentuh.
        $this->assertSame($tagihanLamaSebelum, $this->dalamKelas($kelasLama, fn () => Bill::count()));
        $this->assertSame(1, $this->dalamKelas($kelasLama, fn () => Period::count()));
        $this->assertNotNull($this->dalamKelas($kelasLama, fn () => Student::find($siswaLama->id)));
    }

    public function test_tagihan_ikut_dibuat_kalau_kelasnya_sudah_punya_siswa(): void
    {
        // Periode dibuat lebih dulu oleh perintah, siswanya menyusul — urutan yang
        // sama dengan pemakaian nyata. Siswa ditambahkan LEWAT ROUTE bendahara,
        // karena Student::create() langsung memang bukan yang membuat tagihan;
        // versi lama test ini memanggil model langsung dan karena itu selalu merah.
        $this->jalankan()->assertSuccessful()->run();

        $kelas = CurrentClassroom::withoutTenancy(
            fn () => Classroom::where('nama_kelas', 'XII TRPL 2')->firstOrFail()
        );
        $user = User::where('email', 'bendahara-baru@kaskelas.test')->firstOrFail();

        $this->actingAs($user)->post(route('siswa.store'), [
            'nama' => 'Siswa Menyusul',
            'no_absen' => 1,
            'tgl_mulai_aktif' => '2026-01-01',
        ])->assertRedirect(route('siswa.index'));

        $siswa = $this->dalamKelas($kelas, fn () => Student::where('nama', 'Siswa Menyusul')->firstOrFail());

        $this->assertSame(3, $this->dalamKelas($kelas, fn () => Bill::where('student_id', $siswa->id)->count()));
    }
}
