<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\Student;
use App\Models\User;
use App\Models\WaitingListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Dashboard admin platform (Fase 10 / v2.0).
 *
 * Yang paling penting di sini adalah apa yang TIDAK boleh bisa dilakukan.
 * Halaman ini hanya menampilkan angka gabungan, dan tidak punya satu pun jalan
 * menuju detail transaksi kelas mana pun. Itu batasan yang dipilih, bukan fitur
 * yang belum sempat dibuat — dan batasan yang tidak diuji cepat atau lambat
 * hilang tanpa ada yang sadar.
 */
class AdminPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function adminPlatform(): User
    {
        $admin = User::create([
            'nama' => 'Admin Platform',
            'email' => 'admin@kaskelas.test',
            'password' => 'RahasiaKuat123',
        ]);

        $admin->forceFill(['role' => 'admin_platform', 'email_verified_at' => now()])->save();

        return $admin;
    }

    /** Dua kelas berisi, supaya angkanya bisa dibandingkan dengan yang sebenarnya. */
    protected function duaKelasBerisi(): array
    {
        [$kelasA, $userA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB, $userB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        foreach ([[$kelasA, $userA, 'Adinda Kelas A'], [$kelasB, $userB, 'Fajar Kelas B']] as [$kelas, $user, $nama]) {
            $siswa = $this->buatSiswa($kelas, $nama);
            $this->buatPeriode($kelas);

            $this->actingAs($user)->post(route('pembayaran.store'), [
                'student_id' => $siswa->id,
                'tanggal' => '2026-01-10',
                'jumlah' => 25000,
                'metode' => 'tunai',
                'catatan' => 'rahasia kelas '.$kelas->id,
            ])->assertSessionHasNoErrors();

            $kategori = $this->dalamKelas($kelas, fn () => ExpenseCategory::orderBy('id')->firstOrFail());
            $this->actingAs($user)->post(route('pengeluaran.store'), [
                'tanggal' => '2026-01-11',
                'category_id' => $kategori->id,
                'jumlah' => 3000,
                'keterangan' => 'belanja rahasia '.$kelas->id,
            ])->assertSessionHasNoErrors();
        }

        $this->flushSession();

        return [$kelasA, $kelasB];
    }

    /*
    |--------------------------------------------------------------------------
    | Akses
    |--------------------------------------------------------------------------
    */

    public function test_bendahara_biasa_tidak_bisa_membuka_dashboard_admin(): void
    {
        [, $user] = $this->buatKelas();

        $this->actingAs($user)->get(route('admin.index'))->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('admin.index'))->assertRedirect(route('login'));
    }

    public function test_admin_platform_bisa_membuka_dashboardnya(): void
    {
        $this->duaKelasBerisi();

        $this->actingAs($this->adminPlatform())->get(route('admin.index'))->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Isi halaman: agregat, bukan detail
    |--------------------------------------------------------------------------
    */

    public function test_menampilkan_jumlah_kelas_pengguna_dan_transaksi(): void
    {
        $this->duaKelasBerisi();

        WaitingListEntry::create(['email' => 'menunggu@contoh.test']);

        $admin = $this->adminPlatform();

        $respons = $this->actingAs($admin)->get(route('admin.index'))->assertOk();

        // Dua kelas, dua siswa, dua pembayaran + dua pengeluaran = 4 transaksi.
        $ringkasan = $respons->viewData('ringkasan');

        $this->assertSame(2, $ringkasan['kelas']);
        $this->assertSame(2, $ringkasan['kelas_aktif']);
        $this->assertSame(2, $ringkasan['siswa']);
        $this->assertSame(4, $ringkasan['transaksi']);
        $this->assertSame(1, $ringkasan['antrean']);

        // Tiga bendahara + satu admin.
        $this->assertSame(User::count(), $ringkasan['pengguna']);
    }

    public function test_menampilkan_tanggal_aktif_terakhir_per_kelas(): void
    {
        [$kelasA] = $this->duaKelasBerisi();

        $baris = $this->actingAs($this->adminPlatform())
            ->get(route('admin.index'))
            ->assertOk()
            ->viewData('baris');

        $this->assertCount(2, $baris);

        $barisA = collect($baris)->firstWhere(fn ($b) => $b['kelas']->id === $kelasA->id);

        $this->assertNotNull($barisA['aktivitas_terakhir'],
            'Tanggal aktif terakhir adalah satu-satunya hal yang perlu diketahui '
            .'untuk merawat kapasitas — dan harus terisi kalau kelasnya memang dipakai.');
        $this->assertTrue($barisA['aktivitas_terakhir']->isToday());
    }

    /**
     * Batasan yang paling penting di seluruh fase ini.
     *
     * Admin platform boleh tahu BERAPA, tidak pernah SIAPA membayar berapa.
     * Kalau suatu hari ada permintaan "tolong lihat sebentar kelas X untuk
     * membantu", jawabannya adalah meminta bendaharanya mengekspor CSV sendiri.
     */
    public function test_halaman_admin_tidak_memuat_satu_pun_detail_transaksi(): void
    {
        [$kelasA, $kelasB] = $this->duaKelasBerisi();

        $siswaA = $this->dalamKelas($kelasA, fn () => Student::firstOrFail());
        $siswaB = $this->dalamKelas($kelasB, fn () => Student::firstOrFail());

        $isi = $this->actingAs($this->adminPlatform())
            ->get(route('admin.index'))
            ->assertOk()
            ->getContent();

        // Nama siswa, nominal, catatan, keterangan belanja: tidak satu pun boleh muncul.
        foreach ([
            $siswaA->nama, $siswaB->nama,
            'rahasia kelas '.$kelasA->id, 'rahasia kelas '.$kelasB->id,
            'belanja rahasia '.$kelasA->id, 'belanja rahasia '.$kelasB->id,
            'Rp 25.000', '25000.00',
        ] as $bocor) {
            $this->assertStringNotContainsString($bocor, $isi,
                "Dashboard admin membocorkan detail transaksi: {$bocor}");
        }

        // Token halaman kelas juga bukan urusan admin — memegangnya sama dengan
        // bisa membuka seluruh isi kelas itu tanpa jejak.
        $this->assertStringNotContainsString($kelasA->public_token, $isi);
        $this->assertStringNotContainsString($kelasB->public_token, $isi);
    }

    /** Tidak ada route admin yang menerima id kelas — permukaannya memang tidak ada. */
    public function test_tidak_ada_route_admin_yang_bisa_menunjuk_satu_kelas(): void
    {
        $routeAdmin = collect(Route::getRoutes())
            ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'admin.'))
            ->map(fn ($r) => $r->uri().' ['.implode(',', $r->methods()).']')
            ->values()
            ->all();

        $this->assertSame(['admin [GET,HEAD]'], $routeAdmin,
            'Admin platform hanya boleh punya satu halaman baca, tanpa parameter apa pun.');
    }

    /** Admin platform tetap bendahara biasa di kelasnya sendiri, bukan kunci induk. */
    public function test_admin_platform_tidak_otomatis_bisa_membuka_kelas_orang(): void
    {
        [$kelasA] = $this->duaKelasBerisi();

        $siswaA = $this->dalamKelas($kelasA, fn () => Student::firstOrFail());
        $admin = $this->adminPlatform();

        // Tidak punya kelas sama sekali → diantar ke wizard, bukan menempel ke
        // kelas orang lain hanya karena perannya admin.
        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('wizard.kelas'));
        $this->actingAs($admin)->get(route('siswa.show', $siswaA))->assertRedirect(route('wizard.kelas'));

        // Begitu admin punya kelasnya sendiri pun, kelas orang lain tetap 404 —
        // perannya tidak pernah jadi kunci induk.
        [$kelasAdmin] = $this->buatKelas('X TKJ 2', 'SMKN 2 Subang');
        $kelasAdmin->users()->attach($admin->id, ['peran' => 'bendahara', 'created_at' => now()]);
        $this->flushSession();

        $this->actingAs($admin)->post(route('kelas.pilih'), ['classroom_id' => $kelasA->id])
            ->assertNotFound();
        $this->actingAs($admin)->get(route('siswa.show', $siswaA))->assertNotFound();
    }
}
