<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HalamanKelasPublikTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_kelas_terbuka_tanpa_login(): void
    {
        [$kelas, $user] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        $siswa = $this->buatSiswa($kelas, 'Adinda Ayu');

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-02-28',
        ]);
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id, 'tanggal' => '2026-01-10', 'jumlah' => 5000, 'metode' => 'tunai',
        ]);

        $this->flushSession();

        $this->get(route('publik.kelas', $kelas->public_token))
            ->assertOk()
            ->assertSee('XII TRPL 1')
            ->assertSee('Adinda Ayu')
            ->assertSee('Saldo kas saat ini')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        // Yang diuji bukan sekadar "kebetulan terbuka": route-nya memang tidak
        // dilewatkan middleware auth sama sekali.
        $middleware = collect(Route::getRoutes())
            ->first(fn ($r) => $r->getName() === 'publik.kelas')
            ->gatherMiddleware();

        $this->assertNotContains('auth', $middleware);
        $this->assertContains('kelas.token', $middleware);
    }

    public function test_token_tidak_dikenal_menghasilkan_404(): void
    {
        $this->buatKelas();

        $this->get('/kelas/'.str_repeat('z', 40))->assertNotFound();
        $this->get('/kelas/pendek')->assertNotFound();
    }

    public function test_kelas_nonaktif_juga_404(): void
    {
        [$kelas] = $this->buatKelas();

        CurrentClassroom::withoutTenancy(fn () => $kelas->forceFill(['status' => 'nonaktif'])->save());

        $this->get(route('publik.kelas', $kelas->public_token))->assertNotFound();
    }

    public function test_token_kelas_a_hanya_menampilkan_data_kelas_a(): void
    {
        [$kelasA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB, $userB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $this->buatSiswa($kelasA, 'Adinda Kelas A');
        $siswaB = $this->buatSiswa($kelasB, 'Fajar Kelas B');

        $this->actingAs($userB)->post(route('pembayaran.store'), [
            'student_id' => $siswaB->id, 'tanggal' => '2026-01-10', 'jumlah' => 90000, 'metode' => 'tunai',
        ]);

        $this->flushSession();

        $this->get(route('publik.kelas', $kelasA->public_token))
            ->assertOk()
            ->assertSee('Adinda Kelas A')
            ->assertDontSee('Fajar Kelas B')
            ->assertDontSee('SMAN 2 Bandung')
            ->assertDontSee('Rp 90.000');
    }

    public function test_bukti_transfer_dan_data_akun_tidak_bocor_ke_halaman_kelas(): void
    {
        Storage::fake('local');

        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => '2026-01-10',
            'jumlah' => 5000,
            'metode' => 'transfer',
            'catatan' => 'transfer dari rekening ibunya',
            'bukti' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $bukti = $this->dalamKelas($kelas, fn () => Payment::firstOrFail()->bukti_path);

        $this->flushSession();

        $this->get(route('publik.kelas', $kelas->public_token))
            ->assertOk()
            ->assertDontSee($bukti)
            ->assertDontSee('bukti')
            ->assertDontSee('transfer dari rekening ibunya')
            ->assertDontSee($user->email)
            ->assertDontSee($user->nama);
    }

    public function test_grup_route_publik_hanya_berisi_method_get(): void
    {
        $pelanggaran = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'kelas/'))
            ->reject(fn ($route) => array_diff($route->methods(), ['GET', 'HEAD']) === [])
            ->map(fn ($route) => $route->uri().' ['.implode(',', $route->methods()).']')
            ->values()
            ->all();

        $this->assertSame([], $pelanggaran, 'Route publik wajib hanya GET.');
    }

    public function test_halaman_kelas_tidak_memuat_satu_pun_form_tulis(): void
    {
        [$kelas] = $this->buatKelas();
        $this->buatSiswa($kelas, 'Adinda');

        $isi = $this->get(route('publik.kelas', $kelas->public_token))->assertOk()->getContent();

        $this->assertStringNotContainsString('<form', $isi);
        $this->assertStringNotContainsString('method="POST"', $isi);
        $this->assertStringNotContainsString('csrf', strtolower($isi));
    }

    public function test_rotasi_token_mematikan_tautan_lama(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $tokenLama = $kelas->public_token;

        $this->actingAs($user)->patch(route('pengaturan.token'))
            ->assertRedirect(route('pengaturan.edit'));

        $tokenBaru = CurrentClassroom::withoutTenancy(fn () => Classroom::find($kelas->id)->public_token);

        $this->assertNotSame($tokenLama, $tokenBaru);
        $this->assertSame(40, strlen($tokenBaru));

        $this->flushSession();

        $this->get('/kelas/'.$tokenLama)->assertNotFound();
        $this->get('/kelas/'.$tokenBaru)->assertOk();
    }

    public function test_bendahara_a_tidak_bisa_merotasi_token_kelas_b(): void
    {
        [$kelasA, $userA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $tokenB = $kelasB->public_token;

        // Tidak ada parameter kelas di route ini — rotasi selalu mengenai kelas
        // aktif milik penggunanya sendiri, jadi kelas B tidak mungkin tersentuh.
        $this->actingAs($userA)->patch(route('pengaturan.token'));

        $this->assertSame($tokenB, CurrentClassroom::withoutTenancy(
            fn () => Classroom::find($kelasB->id)->public_token
        ));
        $this->assertNotSame($kelasA->public_token, CurrentClassroom::withoutTenancy(
            fn () => Classroom::find($kelasA->id)->public_token
        ));
    }

    public function test_manifest_per_kelas_mengarah_ke_halaman_kelas_itu(): void
    {
        [$kelas] = $this->buatKelas('XII TRPL 1');

        $this->get(route('publik.manifest', $kelas->public_token))
            ->assertOk()
            ->assertJsonPath('short_name', 'XII TRPL 1')
            ->assertJsonPath('start_url', route('publik.kelas', $kelas->public_token))
            ->assertJsonPath('display', 'standalone');
    }

    public function test_berkas_pwa_tersedia(): void
    {
        // public_path() sengaja diarahkan ke root project (lihat bootstrap/app.php
        // dan DEPLOY.md), jadi berkas publik duduk sejajar dengan index.php.
        foreach ([
            'manifest.webmanifest',
            'sw.js',
            'robots.txt',
            'ikon/ikon-192.png',
            'ikon/ikon-512.png',
            'ikon/ikon-maskable-512.png',
        ] as $berkas) {
            $this->assertFileExists(public_path($berkas));
        }

        $this->get(route('offline'))->assertOk()->assertSee('tanpa koneksi');
    }

    public function test_pengeluaran_ikut_mengurangi_saldo_di_halaman_kelas(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id, 'tanggal' => '2026-01-10', 'jumlah' => 10000, 'metode' => 'tunai',
        ]);

        $kategori = $this->dalamKelas($kelas, fn () => ExpenseCategory::whereNull('classroom_id')->firstOrFail());
        $this->actingAs($user)->post(route('pengeluaran.store'), [
            'tanggal' => '2026-01-11', 'category_id' => $kategori->id,
            'jumlah' => 4000, 'keterangan' => 'beli spidol',
        ]);

        $this->flushSession();

        $this->get(route('publik.kelas', $kelas->public_token))
            ->assertOk()
            ->assertSee('Rp 6.000');
    }
}
