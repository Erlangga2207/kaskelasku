<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Support\CurrentClassroom;
use App\Support\Kapasitas;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Pendaftaran bendahara + verifikasi email (Fase 10 / v2.0).
 *
 * Sebelum fase ini akun hanya lahir lewat `php artisan kaskelas:buat-kelas`,
 * jadi tidak ada satu pun jalur yang bisa disalahgunakan orang asing. Setelah
 * pendaftaran dibuka, tiga hal harus benar sekaligus: alamat email terbukti
 * milik pendaftarnya, kuota kapasitas tidak bisa dijebol, dan kelas tidak bisa
 * lahir dari akun yang belum terbukti.
 */
class PendaftaranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rate limiter menyimpan hitungannya lintas test dalam satu proses;
        // tanpa dibersihkan, test yang urutannya belakangan bisa gagal hanya
        // karena test sebelumnya sudah memakai jatahnya.
        RateLimiter::clear('');
        cache()->clear();
    }

    /*
    |--------------------------------------------------------------------------
    | Pendaftaran
    |--------------------------------------------------------------------------
    */

    public function test_pendaftaran_membuat_akun_belum_terverifikasi_dan_mengirim_email(): void
    {
        Notification::fake();

        $this->post(route('daftar.store'), [
            'nama' => 'Bendahara Baru',
            'email' => 'bendahara@contoh.test',
            'password' => 'RahasiaKuat123',
            'password_confirmation' => 'RahasiaKuat123',
            'setuju_syarat' => '1',
        ])->assertRedirect(route('verifikasi.notice'));

        $user = User::where('email', 'bendahara@contoh.test')->firstOrFail();

        $this->assertNull($user->email_verified_at, 'Akun baru TIDAK boleh langsung terverifikasi.');
        $this->assertNotSame('RahasiaKuat123', $user->password, 'Kata sandi wajib ter-hash.');
        $this->assertAuthenticatedAs($user);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /**
     * Formulir pendaftaran dan halaman verifikasi benar-benar dirender.
     *
     * Terlihat sepele, tapi sebelum test ini ada, kedua Blade itu tidak pernah
     * sekali pun dieksekusi oleh test mana pun — hanya tujuan pantulannya yang
     * diperiksa. Satu salah ketik di dalamnya akan lolos sampai produksi.
     */
    public function test_halaman_daftar_dan_verifikasi_benar_benar_dirender(): void
    {
        $this->get(route('daftar'))
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('name="setuju_syarat"', false)
            ->assertSee(route('syarat'), false)
            ->assertSee(route('privasi'), false);

        $user = User::create([
            'nama' => 'Belum Terverifikasi',
            'email' => 'belum@contoh.test',
            'password' => 'RahasiaKuat123',
        ]);

        $this->actingAs($user)->get(route('verifikasi.notice'))
            ->assertOk()
            ->assertSee($user->email)
            ->assertSee(route('verifikasi.kirim-ulang'), false);
    }

    public function test_pendaftaran_menolak_input_yang_tidak_lengkap(): void
    {
        $this->post(route('daftar.store'), [
            'nama' => '',
            'email' => 'bukan-email',
            'password' => 'pendek',
            'password_confirmation' => 'beda',
        ])->assertSessionHasErrors(['nama', 'email', 'password', 'setuju_syarat']);

        $this->assertSame(0, User::count());
    }

    /** Persetujuan syarat tidak boleh dianggap otomatis hanya karena formnya dikirim. */
    public function test_tanpa_centang_syarat_pendaftaran_ditolak(): void
    {
        $this->post(route('daftar.store'), [
            'nama' => 'Bendahara Baru',
            'email' => 'bendahara@contoh.test',
            'password' => 'RahasiaKuat123',
            'password_confirmation' => 'RahasiaKuat123',
        ])->assertSessionHasErrors('setuju_syarat');

        $this->assertSame(0, User::count());
    }

    public function test_email_yang_sudah_terdaftar_ditolak(): void
    {
        [, $user] = $this->buatKelas();

        $this->post(route('daftar.store'), [
            'nama' => 'Penyusup',
            'email' => $user->email,
            'password' => 'RahasiaKuat123',
            'password_confirmation' => 'RahasiaKuat123',
            'setuju_syarat' => '1',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', $user->email)->count());
    }

    /**
     * Rate limit pendaftaran: 5 percobaan per jam per IP.
     *
     * Tanpa ini satu skrip bisa menghabiskan seluruh kuota 100 kelas dalam
     * hitungan menit, dan tiap percobaan juga memicu satu email keluar lewat
     * SMTP Hostinger yang kuotanya terbatas.
     */
    public function test_pendaftaran_dibatasi_lima_kali_per_jam(): void
    {
        Notification::fake();

        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('daftar.store'), [
                'nama' => 'Bendahara '.$i,
                'email' => "bendahara{$i}@contoh.test",
                'password' => 'RahasiaKuat123',
                'password_confirmation' => 'RahasiaKuat123',
                'setuju_syarat' => '1',
            ])->assertRedirect(route('verifikasi.notice'));

            // Pendaftaran berhasil langsung menyetel sesi masuk, sedangkan route
            // /daftar ada di grup 'guest'. Tanpa keluar dulu, percobaan berikutnya
            // dipantulkan sebelum sempat menyentuh throttle — dan test ini akan
            // hijau tanpa pernah menguji rate limit-nya sama sekali.
            auth()->logout();
            $this->flushSession();
        }

        $this->post(route('daftar.store'), [
            'nama' => 'Bendahara 6',
            'email' => 'bendahara6@contoh.test',
            'password' => 'RahasiaKuat123',
            'password_confirmation' => 'RahasiaKuat123',
            'setuju_syarat' => '1',
        ])->assertStatus(429);

        $this->assertSame(5, User::count(), 'Percobaan ke-6 tidak boleh membuat akun.');
    }

    /*
    |--------------------------------------------------------------------------
    | Verifikasi email
    |--------------------------------------------------------------------------
    */

    public function test_kelas_tidak_bisa_dibuat_sebelum_email_terverifikasi(): void
    {
        $user = User::create([
            'nama' => 'Belum Terverifikasi',
            'email' => 'belum@contoh.test',
            'password' => 'RahasiaKuat123',
        ]);

        $this->actingAs($user)->get(route('wizard.kelas'))
            ->assertRedirect(route('verifikasi.notice'));

        // Termasuk lewat POST langsung — menyembunyikan formnya bukan pengamanan.
        $this->actingAs($user)->post(route('wizard.kelas.store'), [
            'nama_kelas' => 'XII TRPL 1',
            'sekolah' => 'SMKN 1 Subang',
            'tipe_periode' => 'bulanan',
            'persetujuan_data' => '1',
        ])->assertRedirect(route('verifikasi.notice'));

        $this->assertSame(0, CurrentClassroom::withoutTenancy(fn () => Classroom::count()));
    }

    public function test_tautan_verifikasi_bertanda_tangan_menyelesaikan_verifikasi(): void
    {
        $user = User::create([
            'nama' => 'Bendahara Baru',
            'email' => 'baru@contoh.test',
            'password' => 'RahasiaKuat123',
        ]);

        $tautan = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($tautan)->assertRedirect(route('wizard.kelas'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    /** Tautan tanpa tanda tangan yang sah tidak boleh menverifikasi apa pun. */
    public function test_tautan_verifikasi_tanpa_tanda_tangan_ditolak(): void
    {
        $user = User::create([
            'nama' => 'Bendahara Baru',
            'email' => 'baru@contoh.test',
            'password' => 'RahasiaKuat123',
        ]);

        $this->actingAs($user)
            ->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /** Hash milik email lain tidak boleh berlaku, walau id-nya benar. */
    public function test_tautan_verifikasi_dengan_hash_salah_ditolak(): void
    {
        $user = User::create([
            'nama' => 'Bendahara Baru',
            'email' => 'baru@contoh.test',
            'password' => 'RahasiaKuat123',
        ]);

        $tautan = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1('orang-lain@contoh.test'),
        ]);

        $this->actingAs($user)->get($tautan)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /**
     * Kirim ulang dibatasi 3 kali per 10 menit.
     *
     * Bukan sekadar anti-spam: tiap percobaan mengirim email sungguhan lewat
     * SMTP Hostinger, dan kuota SMTP yang habis membuat SEMUA pendaftar lain
     * ikut tertahan — termasuk yang sama sekali tidak bersalah.
     */
    public function test_kirim_ulang_email_verifikasi_dibatasi(): void
    {
        Notification::fake();

        $user = User::create([
            'nama' => 'Bendahara Baru',
            'email' => 'baru@contoh.test',
            'password' => 'RahasiaKuat123',
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($user)->post(route('verifikasi.kirim-ulang'))->assertStatus(302);
        }

        $this->actingAs($user)->post(route('verifikasi.kirim-ulang'))->assertStatus(429);

        Notification::assertSentToTimes($user, VerifyEmail::class, 3);
    }

    public function test_halaman_verifikasi_melompat_ke_wizard_kalau_sudah_terverifikasi(): void
    {
        [, $user] = $this->buatKelas();

        // buatKelas() menghasilkan akun terverifikasi yang sudah punya kelas.
        $this->actingAs($user)->get(route('verifikasi.notice'))
            ->assertRedirect(route('wizard.kelas'));
    }

    /*
    |--------------------------------------------------------------------------
    | Rem darurat kapasitas
    |--------------------------------------------------------------------------
    */

    public function test_kuota_sistem_penuh_mengganti_pendaftaran_dengan_daftar_tunggu(): void
    {
        config(['kaskelas.batas.kelas_terdaftar' => 1]);

        $this->buatKelas();

        $this->get(route('daftar'))->assertRedirect(route('daftar-tunggu'));

        $this->post(route('daftar.store'), [
            'nama' => 'Terlambat',
            'email' => 'terlambat@contoh.test',
            'password' => 'RahasiaKuat123',
            'password_confirmation' => 'RahasiaKuat123',
            'setuju_syarat' => '1',
        ])->assertRedirect(route('daftar-tunggu'));

        $this->assertSame(0, User::where('email', 'terlambat@contoh.test')->count());
    }

    public function test_daftar_tunggu_hanya_mengumpulkan_email(): void
    {
        config(['kaskelas.batas.kelas_terdaftar' => 1]);

        $this->buatKelas();

        $this->get(route('daftar-tunggu'))->assertOk();

        $this->post(route('daftar-tunggu.store'), [
            'email' => 'menunggu@contoh.test',
            // Dikirim iseng — kolomnya memang tidak ada, jadi harus terabaikan.
            'nama' => 'Tidak Boleh Tersimpan',
            'sekolah' => 'SMKN 1 Subang',
        ])->assertSessionHasNoErrors();

        $entri = WaitingListEntry::where('email', 'menunggu@contoh.test')->firstOrFail();

        $kolom = array_keys($entri->getAttributes());
        sort($kolom);

        $this->assertSame(['created_at', 'email', 'id'], $kolom,
            'Daftar tunggu tidak boleh menyimpan apa pun selain email.');
    }

    public function test_email_yang_sama_tidak_menggandakan_antrean(): void
    {
        config(['kaskelas.batas.kelas_terdaftar' => 1]);

        $this->buatKelas();

        foreach (range(1, 3) as $ignored) {
            $this->post(route('daftar-tunggu.store'), ['email' => 'menunggu@contoh.test'])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(1, WaitingListEntry::count());
    }

    /** Kuota longgar lagi → antrean tidak lagi menahan siapa pun. */
    public function test_daftar_tunggu_mengembalikan_ke_pendaftaran_saat_kuota_longgar(): void
    {
        config(['kaskelas.batas.kelas_terdaftar' => 100]);

        $this->get(route('daftar-tunggu'))->assertRedirect(route('daftar'));
    }

    /** Angka batasnya dari config, supaya bisa dinaikkan lewat .env tanpa deploy. */
    public function test_batas_kapasitas_dibaca_dari_config_bukan_hardcode(): void
    {
        config([
            'kaskelas.batas.kelas_terdaftar' => 7,
            'kaskelas.batas.kelas_per_akun' => 3,
            'kaskelas.batas.siswa_per_kelas' => 41,
        ]);

        $this->assertSame(7, Kapasitas::batasKelasSistem());
        $this->assertSame(3, Kapasitas::batasKelasPerAkun());
        $this->assertSame(41, Kapasitas::batasSiswaPerKelas());
    }
}
