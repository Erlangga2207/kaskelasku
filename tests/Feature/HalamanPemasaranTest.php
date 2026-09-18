<?php

namespace Tests\Feature;

use App\Http\Controllers\PublicPageController;
use App\Models\Bill;
use App\Models\Campaign;
use App\Models\Classroom;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Landing page, halaman wajib, SEO, dan kelas demo (Fase 10 / v2.0).
 *
 * Yang diuji di sini bukan "apakah halamannya bagus" — itu tidak bisa diuji
 * otomatis. Yang diuji adalah hal-hal yang kalau salah akibatnya nyata:
 * halaman kelas ikut terindeks, data terstruktur berbeda dari yang terlihat,
 * atau kelas demo ternyata bisa ditulisi.
 */
class HalamanPemasaranTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Halaman publik
    |--------------------------------------------------------------------------
    */

    public function test_halaman_publik_terbuka_tanpa_login(): void
    {
        foreach (['beranda', 'panduan', 'privasi', 'syarat'] as $nama) {
            $this->get(route($nama))->assertOk("Halaman {$nama} harus terbuka untuk tamu.");
        }
    }

    public function test_beranda_punya_tepat_satu_h1(): void
    {
        $isi = $this->get(route('beranda'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($isi, '<h1'),
            'Satu halaman hanya boleh punya satu H1.');
    }

    public function test_beranda_memuat_judul_deskripsi_kanonik_dan_open_graph(): void
    {
        $respons = $this->get(route('beranda'))->assertOk();

        // Kata yang benar-benar dicari orang, bukan jargon internal.
        $this->assertStringContainsString('aplikasi kas kelas',
            mb_strtolower($respons->getContent()));
        $respons->assertSee('<meta name="description"', false);
        $respons->assertSee('<link rel="canonical" href="'.route('beranda').'"', false);

        // Tautan ini akan disebar lewat WhatsApp, jadi preview-nya harus benar.
        $respons->assertSee('og:title', false);
        $respons->assertSee('og:description', false);
        $respons->assertSee('og:image', false);
        $respons->assertSee('og:image:width', false);
        $respons->assertSee('twitter:card', false);
    }

    /** Halaman pemasaran boleh diindeks — dan HARUS, kalau tidak sia-sia dibuat. */
    public function test_halaman_pemasaran_boleh_diindeks(): void
    {
        foreach (['beranda', 'panduan', 'privasi', 'syarat'] as $nama) {
            $this->get(route($nama))
                ->assertOk()
                ->assertSee('<meta name="robots" content="index, follow', false);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Data terstruktur
    |--------------------------------------------------------------------------
    */

    public function test_json_ld_memuat_software_application_dan_faq(): void
    {
        $isi = $this->get(route('beranda'))->assertOk()->getContent();

        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $isi, $cocok);

        $this->assertNotEmpty($cocok, 'Beranda harus memuat satu blok JSON-LD.');

        $data = json_decode($cocok[1], true, flags: JSON_THROW_ON_ERROR);

        $tipe = array_column($data['@graph'], '@type');

        $this->assertContains('SoftwareApplication', $tipe);
        $this->assertContains('FAQPage', $tipe);

        // Tidak ada aggregateRating: bintang tanpa ulasan sungguhan itu data
        // palsu, dan bisa membuat SELURUH data terstruktur situs diabaikan.
        $this->assertStringNotContainsString('aggregateRating', $cocok[1]);
    }

    /**
     * Jawaban terstruktur yang berbeda dari yang terlihat di halaman adalah
     * pelanggaran pedoman Google, bukan sekadar tidak rapi. Karena itu keduanya
     * lahir dari satu sumber yang sama.
     */
    public function test_faq_di_json_ld_sama_persis_dengan_yang_terlihat(): void
    {
        $respons = $this->get(route('beranda'))->assertOk();

        foreach (PublicPageController::faq() as $item) {
            $respons->assertSee($item['tanya'], false);
        }

        preg_match('#<script type="application/ld\+json">(.+?)</script>#s',
            $respons->getContent(), $cocok);

        $data = json_decode($cocok[1], true, flags: JSON_THROW_ON_ERROR);
        $faqPage = collect($data['@graph'])->firstWhere('@type', 'FAQPage');

        $this->assertSame(
            array_column(PublicPageController::faq(), 'tanya'),
            array_column($faqPage['mainEntity'], 'name'),
        );
    }

    /**
     * Klaim keamanan palsu ("terenkripsi end-to-end", "100% aman") bukan sekadar
     * berlebihan — itu masalah hukum kalau suatu hari ada kebocoran.
     */
    public function test_tidak_ada_klaim_keamanan_yang_tidak_benar(): void
    {
        foreach (['beranda', 'panduan', 'privasi', 'syarat'] as $nama) {
            $isi = mb_strtolower($this->get(route($nama))->assertOk()->getContent());

            foreach (['end-to-end', 'end to end', '100% aman', 'sepenuhnya aman',
                'tidak mungkin dibobol', 'keamanan tingkat bank'] as $klaim) {
                $this->assertStringNotContainsString($klaim, $isi,
                    "Halaman {$nama} memuat klaim keamanan yang tidak bisa dipertanggungjawabkan: {$klaim}");
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | sitemap.xml & robots.txt
    |--------------------------------------------------------------------------
    */

    public function test_sitemap_hanya_memuat_halaman_yang_boleh_diindeks(): void
    {
        $respons = $this->get(route('sitemap'))->assertOk();

        $respons->assertHeader('content-type', 'application/xml; charset=UTF-8');

        $isi = $respons->getContent();

        foreach ([route('beranda'), route('panduan'), route('privasi'), route('syarat')] as $url) {
            $this->assertStringContainsString($url, $isi);
        }

        foreach (['/dashboard', '/kelas/', '/siswa', '/pembayaran', '/laporan',
            '/admin', '/ekspor', '/wizard', '/masuk', '/daftar'] as $terlarang) {
            $this->assertStringNotContainsString($terlarang, $isi,
                "Sitemap mengundang crawler ke {$terlarang} — halaman itu tidak boleh diindeks.");
        }
    }

    public function test_robots_menutup_area_bendahara_dan_halaman_kelas(): void
    {
        $respons = $this->get(route('robots'))->assertOk();

        $isi = $respons->getContent();

        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $isi);

        foreach (['/dashboard', '/kelas/', '/siswa', '/pembayaran', '/pengeluaran',
            '/laporan', '/audit', '/ekspor', '/admin', '/wizard'] as $jalur) {
            $this->assertStringContainsString('Disallow: '.$jalur, $isi,
                "robots.txt harus menutup {$jalur}.");
        }
    }

    /**
     * Dua lapis, dan keduanya diuji: robots.txt saja tidak cukup karena crawler
     * yang nakal mengabaikannya, dan halaman kelas berisi nama siswa.
     */
    public function test_halaman_kelas_tetap_noindex_di_header_dan_meta(): void
    {
        [$kelas] = $this->buatKelas();
        $this->buatSiswa($kelas, 'Adinda');

        $this->get(route('publik.kelas', $kelas->public_token))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('<meta name="robots" content="noindex, nofollow', false);
    }

    public function test_dashboard_bendahara_juga_noindex(): void
    {
        [, $user] = $this->buatKelas();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Kelas demo
    |--------------------------------------------------------------------------
    */

    public function test_perintah_reset_demo_membangun_kelas_contoh_yang_masuk_akal(): void
    {
        $this->artisan('kaskelas:reset-demo')->assertSuccessful();

        $demo = CurrentClassroom::withoutTenancy(
            fn () => Classroom::where('is_demo', true)->firstOrFail()
        );

        $this->dalamKelas($demo, function () {
            $this->assertGreaterThan(5, Student::count(), 'Demo harus terasa seperti kelas sungguhan.');
            $this->assertGreaterThan(0, Payment::count());
            $this->assertGreaterThan(0, Expense::count());
            $this->assertSame(1, Campaign::count(), 'PRD meminta satu iuran insidental berjalan.');

            // Sebagian lunas, sebagian nunggak — demo yang semuanya lunas tidak
            // menunjukkan bagian aplikasi yang justru paling dipakai.
            $terbayar = Bill::whereHas('allocations')->count();
            $this->assertGreaterThan(0, $terbayar);
            $this->assertGreaterThan($terbayar, Bill::count());
        });
    }

    public function test_demo_mengantar_ke_halaman_kelas_publik_yang_hanya_bisa_dibaca(): void
    {
        $this->artisan('kaskelas:reset-demo')->assertSuccessful();

        $demo = CurrentClassroom::withoutTenancy(
            fn () => Classroom::where('is_demo', true)->firstOrFail()
        );

        $this->get(route('demo'))->assertRedirect(route('publik.kelas', $demo->public_token));

        $isi = $this->get(route('publik.kelas', $demo->public_token))->assertOk()->getContent();

        // Halaman kelas publik memang tidak punya satu pun jalur tulis —
        // itulah alasan demo memakainya, bukan tiruan dashboard read-only.
        $this->assertStringNotContainsString('<form', $isi);
        $this->assertStringNotContainsString('csrf', strtolower($isi));
    }

    /** Demo tidak boleh ikut memakan kuota kelas, dan tidak boleh bisa dihapus. */
    public function test_demo_tidak_menghitung_kuota_dan_tidak_bisa_dihapus(): void
    {
        $this->artisan('kaskelas:reset-demo')->assertSuccessful();

        $this->assertSame(0, \App\Support\Kapasitas::jumlahKelasSistem(),
            'Kelas demo bukan milik pengguna, jadi tidak boleh memakan kuota.');

        $demo = CurrentClassroom::withoutTenancy(
            fn () => Classroom::where('is_demo', true)->firstOrFail()
        );

        // Lapis pertama: akun pemilik demo mati dan tidak pernah terverifikasi,
        // jadi ia tidak bisa dipakai masuk sama sekali.
        $pemilik = User::findOrFail($demo->owner_id);
        $this->assertFalse((bool) $pemilik->is_active);
        $this->assertFalse($pemilik->hasVerifiedEmail());

        // Lapis kedua, yang sebenarnya diuji di sini: seandainya pun ada akun
        // sehat yang memegang kelas demo, penghapusannya tetap ditolak server.
        [, $bendahara] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');

        CurrentClassroom::withoutTenancy(function () use ($demo, $bendahara) {
            Classroom::findOrFail($demo->id)->forceFill(['owner_id' => $bendahara->id])->save();
        });
        $demo->users()->attach($bendahara->id, ['peran' => 'bendahara', 'created_at' => now()]);

        session(['classroom_id' => $demo->id]);

        $this->actingAs($bendahara)->delete(route('kelas.destroy'), [
            'konfirmasi_nama' => $demo->nama_kelas,
        ])->assertForbidden();

        $this->assertSame('aktif', CurrentClassroom::withoutTenancy(
            fn () => Classroom::findOrFail($demo->id)->status
        ));
    }

    public function test_reset_demo_membangun_ulang_bukan_menumpuk(): void
    {
        $this->artisan('kaskelas:reset-demo')->assertSuccessful();

        $demo = CurrentClassroom::withoutTenancy(
            fn () => Classroom::where('is_demo', true)->firstOrFail()
        );
        $sebelum = $this->dalamKelas($demo, fn () => Payment::count());

        $this->artisan('kaskelas:reset-demo')->assertSuccessful();

        $demoBaru = CurrentClassroom::withoutTenancy(
            fn () => Classroom::where('is_demo', true)->firstOrFail()
        );

        $this->assertSame(1, CurrentClassroom::withoutTenancy(
            fn () => Classroom::where('is_demo', true)->count()
        ), 'Reset tidak boleh meninggalkan kelas demo lama.');

        $this->assertSame($sebelum, $this->dalamKelas($demoBaru, fn () => Payment::count()),
            'Angka demo tidak boleh menumpuk tiap kali direset.');
    }

    /*
    |--------------------------------------------------------------------------
    | Grup route publik
    |--------------------------------------------------------------------------
    */

    /**
     * Halaman pemasaran hanya GET, kecuali daftar tunggu yang memang perlu
     * menerima satu alamat email.
     */
    public function test_route_publik_tidak_menambah_permukaan_tulis(): void
    {
        $tulis = collect(Route::getRoutes())
            ->filter(fn ($r) => in_array($r->getName(), [
                'beranda', 'panduan', 'privasi', 'syarat', 'sitemap', 'robots', 'demo',
            ], true))
            ->reject(fn ($r) => array_diff($r->methods(), ['GET', 'HEAD']) === [])
            ->map(fn ($r) => $r->uri())
            ->values()
            ->all();

        $this->assertSame([], $tulis, 'Halaman pemasaran wajib hanya GET.');
    }
}
