<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthBendaharaTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_bendahara_menolak_tamu(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_bendahara_bisa_masuk_dan_melihat_kelasnya(): void
    {
        [$kelas, $user] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'RahasiaKuat123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('XII TRPL 1')
            ->assertSee('SMKN 1 Subang');

        $this->assertSame(
            1,
            CurrentClassroom::withoutTenancy(
                fn () => AuditLog::where('aksi', 'login')->where('user_id', $user->id)->count()
            )
        );
    }

    public function test_kata_sandi_salah_ditolak(): void
    {
        [, $user] = $this->buatKelas();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'salah-sekali'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_percobaan_masuk_dibatasi_lima_kali_per_menit(): void
    {
        [, $user] = $this->buatKelas();

        RateLimiter::clear(str()->lower($user->email).'|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'salah']);
        }

        $respons = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'RahasiaKuat123', // sandi benar pun harus ditolak saat terkunci
        ]);

        $respons->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_akun_nonaktif_tidak_bisa_masuk(): void
    {
        [, $user] = $this->buatKelas();
        $user->forceFill(['is_active' => false])->save();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'RahasiaKuat123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_bendahara_tanpa_kelas_tidak_bisa_masuk_dashboard(): void
    {
        $user = User::create([
            'nama' => 'Bendahara Tanpa Kelas',
            'email' => 'sendirian@kaskelas.test',
            'password' => 'RahasiaKuat123',
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_session_kelas_milik_orang_lain_diabaikan(): void
    {
        [$kelasA, $userA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $this->actingAs($userA)
            ->withSession(['classroom_id' => $kelasB->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('XII TRPL 1')
            ->assertDontSee('XI IPA 3');

        $this->assertSame($kelasA->id, session('classroom_id'));
    }

    public function test_keluar_menghapus_sesi(): void
    {
        [, $user] = $this->buatKelas();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
