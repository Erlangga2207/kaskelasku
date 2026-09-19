<?php

namespace Tests\Feature;

use App\Http\Middleware\SetCurrentClassroom;
use App\Models\Bill;
use App\Models\Classroom;
use App\Models\Period;
use App\Models\Student;
use App\Models\User;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wizard penyiapan kelas (Fase 10 / v2.0).
 *
 * Yang diuji di sini bukan tampilan tiga langkahnya, melainkan bahwa urutannya
 * DIPAKSA DI SERVER: buat kelas → input siswa → buat periode.
 *
 * Alasannya bukan kerapian. Tanpa periode tidak ada tagihan, dan tanpa tagihan
 * setiap pembayaran mendarat sebagai deposit menggantung: uangnya tercatat
 * masuk, seluruh laporan tetap nol, dan tidak ada satu pun pesan galat. Bug itu
 * pernah terjadi sungguhan dan baru ketahuan berminggu-minggu kemudian.
 * Menyembunyikan menunya tidak cukup — URL-nya masih bisa diketik langsung.
 */
class WizardPenyiapanTest extends TestCase
{
    use RefreshDatabase;

    /** Akun terverifikasi yang belum punya kelas sama sekali. */
    protected function bendaharaBaru(string $email = 'baru@contoh.test'): User
    {
        $user = User::create([
            'nama' => 'Bendahara Baru',
            'email' => $email,
            'password' => 'RahasiaKuat123',
        ]);

        $user->markEmailAsVerified();

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | Langkah 1 — buat kelas
    |--------------------------------------------------------------------------
    */

    public function test_wizard_membuat_kelas_dan_menjadikannya_kelas_aktif(): void
    {
        $user = $this->bendaharaBaru();

        $this->actingAs($user)->get(route('wizard.kelas'))->assertOk();

        $this->actingAs($user)->post(route('wizard.kelas.store'), [
            'nama_kelas' => 'XII TRPL 1',
            'sekolah' => 'SMKN 1 Subang',
            'tipe_periode' => 'bulanan',
            'persetujuan_data' => '1',
        ])->assertRedirect(route('wizard.siswa'));

        $kelas = CurrentClassroom::withoutTenancy(fn () => Classroom::firstOrFail());

        $this->assertSame('XII TRPL 1', $kelas->nama_kelas);
        $this->assertSame($user->id, $kelas->owner_id);
        $this->assertSame(40, strlen($kelas->public_token));
        $this->assertTrue($kelas->users()->where('users.id', $user->id)->exists());
        $this->assertSame($kelas->id, session(SetCurrentClassroom::SESSION_KEY),
            'Kelas yang baru dibuat harus langsung jadi kelas aktif, kalau tidak langkah '
            .'berikutnya akan mengisi siswa ke kelas yang salah.');
    }

    /**
     * Persetujuan data siswa dicatat WAKTUNYA, bukan sekadar boolean.
     *
     * UU PDP menuntut bisa menunjukkan kapan persetujuan diberikan, bukan hanya
     * bahwa ia pernah diberikan.
     */
    public function test_persetujuan_data_siswa_wajib_dan_waktunya_tercatat(): void
    {
        $user = $this->bendaharaBaru();

        $this->actingAs($user)->post(route('wizard.kelas.store'), [
            'nama_kelas' => 'XII TRPL 1',
            'sekolah' => 'SMKN 1 Subang',
            'tipe_periode' => 'bulanan',
        ])->assertSessionHasErrors('persetujuan_data');

        $this->assertSame(0, CurrentClassroom::withoutTenancy(fn () => Classroom::count()));

        $this->actingAs($user)->post(route('wizard.kelas.store'), [
            'nama_kelas' => 'XII TRPL 1',
            'sekolah' => 'SMKN 1 Subang',
            'tipe_periode' => 'bulanan',
            'persetujuan_data' => '1',
        ])->assertSessionHasNoErrors();

        $kelas = CurrentClassroom::withoutTenancy(fn () => Classroom::firstOrFail());

        $this->assertNotNull($kelas->persetujuan_data_at);
        $this->assertTrue($kelas->persetujuan_data_at->isToday());
    }

    /*
    |--------------------------------------------------------------------------
    | Urutan yang dipaksa
    |--------------------------------------------------------------------------
    */

    /**
     * Inti seluruh fase ini. Setiap halaman yang angkanya baru masuk akal
     * setelah penyiapan selesai harus memantulkan, bukan sekadar menyembunyikan
     * menunya.
     */
    public function test_seluruh_menu_transaksi_terkunci_sampai_ada_siswa(): void
    {
        [, $user] = $this->buatKelas();

        foreach ([
            'pembayaran.index', 'pembayaran.create', 'pengeluaran.index',
            'pengeluaran.create', 'laporan.index', 'audit.index',
            'pengingat.index', 'tutup-buku.index',
        ] as $route) {
            $this->actingAs($user)->get(route($route))
                ->assertRedirect(route('wizard.siswa'), "Route {$route} seharusnya terkunci.");
        }
    }

    public function test_menu_transaksi_masih_terkunci_setelah_siswa_tapi_belum_ada_periode(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $this->buatSiswa($kelas, 'Adinda');

        foreach (['pembayaran.create', 'pengeluaran.index', 'laporan.index', 'tutup-buku.index'] as $route) {
            $this->actingAs($user)->get(route($route))
                ->assertRedirect(route('wizard.periode'), "Route {$route} seharusnya terkunci.");
        }
    }

    public function test_menu_terbuka_begitu_siswa_dan_periode_lengkap(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $this->buatSiswa($kelas, 'Adinda');
        $this->buatPeriode($kelas);

        foreach ([
            'pembayaran.index', 'pembayaran.create', 'pengeluaran.index',
            'laporan.index', 'audit.index', 'pengingat.index', 'tutup-buku.index',
        ] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk("Route {$route} seharusnya terbuka.");
        }
    }

    /**
     * Siswa, Periode, dan Pengaturan sengaja TIDAK ikut digerbang — justru di
     * sanalah penyiapannya diselesaikan. Menggerbang ketiganya akan membuat
     * kelas baru terkunci dari luar tanpa jalan masuk.
     */
    public function test_halaman_penyiapan_sendiri_tidak_ikut_terkunci(): void
    {
        [, $user] = $this->buatKelas();

        foreach (['siswa.index', 'siswa.massal', 'periode.index', 'pengaturan.edit', 'dashboard'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk("Route {$route} tidak boleh ikut dikunci.");
        }
    }

    /**
     * Ketiga langkah wizard benar-benar dirender, bukan cuma jadi tujuan
     * pantulan. `wizard.siswa` khususnya: seluruh test lain hanya memeriksa
     * bahwa sesuatu memantul KE sana, jadi isinya tidak pernah dieksekusi.
     */
    public function test_ketiga_halaman_wizard_benar_benar_dirender(): void
    {
        $user = $this->bendaharaBaru();

        $this->actingAs($user)->get(route('wizard.kelas'))
            ->assertOk()
            ->assertSee('name="nama_kelas"', false)
            ->assertSee('name="persetujuan_data"', false);

        [$kelas] = $this->buatKelas();
        $kelas->users()->attach($user->id, ['peran' => 'bendahara', 'created_at' => now()]);
        session([SetCurrentClassroom::SESSION_KEY => $kelas->id]);

        // Langkah 2: kelas ada, siswa belum.
        $this->actingAs($user)->get(route('wizard.siswa'))
            ->assertOk()
            ->assertSee('name="daftar"', false)
            ->assertSee(route('siswa.massal.store'), false);

        $this->buatSiswa($kelas, 'Adinda');

        // Langkah 3, beserta peringatan yang jadi alasan seluruh wizard ini ada.
        $this->actingAs($user)->get(route('wizard.periode'))
            ->assertOk()
            ->assertSee('name="nominal"', false)
            ->assertSee(route('periode.store'), false)
            ->assertSee('tidak ada tagihan yang terbentuk', false);
    }

    /** Langkah wizard melompat sendiri ke langkah yang memang belum beres. */
    public function test_langkah_wizard_melompat_sesuai_keadaan_kelas(): void
    {
        [$kelas, $user] = $this->buatKelas();

        // Belum ada siswa: langkah periode memantulkan balik ke langkah siswa.
        $this->actingAs($user)->get(route('wizard.periode'))
            ->assertRedirect(route('wizard.siswa'));

        $this->buatSiswa($kelas, 'Adinda');

        // Sudah ada siswa: langkah siswa dilewati, langsung ke periode.
        $this->actingAs($user)->get(route('wizard.siswa'))
            ->assertRedirect(route('wizard.periode'));

        $this->actingAs($user)->get(route('wizard.periode'))->assertOk();

        $this->buatPeriode($kelas);

        // Semua beres: wizard tidak punya alasan menahan siapa pun lagi.
        $this->actingAs($user)->get(route('wizard.periode'))
            ->assertRedirect(route('dashboard'));
    }

    /**
     * Kelas yang punya campaign hidup TIDAK dipantulkan lagi, meski belum punya
     * periode rutin. Yang berbahaya bukan "tidak ada periode", melainkan "tidak
     * ada tagihan sama sekali" — dan campaign pun menerbitkan tagihan.
     */
    public function test_campaign_hidup_cukup_untuk_membuka_gerbang(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->post(route('campaign.store'), [
            'nama' => 'Studi Tour',
            'nominal_per_siswa' => 50000,
            'peserta' => [$siswa->id],
        ])->assertSessionHas('sukses');

        $this->assertSame(0, $this->dalamKelas($kelas, fn () => Period::count()),
            'Persiapan test salah kalau kelasnya ternyata punya periode.');
        $this->assertSame(1, $this->dalamKelas($kelas, fn () => Bill::count()));

        $this->actingAs($user)->get(route('pembayaran.create'))->assertOk();
    }

    /** Periode libur tidak menerbitkan tagihan, jadi tidak boleh membuka gerbang. */
    public function test_periode_libur_saja_tidak_membuka_gerbang(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $this->buatSiswa($kelas, 'Adinda');

        $this->dalamKelas($kelas, fn () => Period::create([
            'label' => 'Juni 2026',
            'tipe' => 'bulanan',
            'tgl_mulai' => '2026-06-01',
            'tgl_selesai' => '2026-06-30',
            'jatuh_tempo' => '2026-06-30',
            'nominal' => '0.00',
            'is_libur' => true,
        ]));

        $this->actingAs($user)->get(route('pembayaran.create'))
            ->assertRedirect(route('wizard.periode'));
    }

    /*
    |--------------------------------------------------------------------------
    | Pemilih kelas aktif
    |--------------------------------------------------------------------------
    */

    public function test_kelas_aktif_berpindah_lewat_session_bukan_url(): void
    {
        [$kelasA, $user] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        // Kelas B dihubungkan ke bendahara yang sama — satu akun, dua kelas.
        $kelasB->users()->attach($user->id, ['peran' => 'bendahara', 'created_at' => now()]);

        $this->actingAs($user)->post(route('kelas.pilih'), ['classroom_id' => $kelasB->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($kelasB->id, session(SetCurrentClassroom::SESSION_KEY));

        // Kedua nama memang muncul — pemilih kelas memang mendaftar semuanya.
        // Yang membuktikan perpindahannya adalah option mana yang tersorot.
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('XI IPA 3')
            ->assertSee('value="'.$kelasB->id.'" selected', false)
            ->assertDontSee('value="'.$kelasA->id.'" selected', false);

        $this->actingAs($user)->post(route('kelas.pilih'), ['classroom_id' => $kelasA->id]);

        $this->assertSame($kelasA->id, session(SetCurrentClassroom::SESSION_KEY));
    }

    /** Isolasi: id kelas milik orang lain tidak boleh bisa dipilih. */
    public function test_memilih_kelas_milik_orang_lain_menghasilkan_404(): void
    {
        [$kelasA, $userA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $this->actingAs($userA)->post(route('kelas.pilih'), ['classroom_id' => $kelasB->id])
            ->assertNotFound();

        $this->assertNotSame($kelasB->id, session(SetCurrentClassroom::SESSION_KEY));

        $this->actingAs($userA)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('XI IPA 3');
    }

    /*
    |--------------------------------------------------------------------------
    | Rem darurat kapasitas
    |--------------------------------------------------------------------------
    */

    public function test_satu_akun_dibatasi_lima_kelas(): void
    {
        config(['kaskelas.batas.kelas_per_akun' => 2]);

        $user = $this->bendaharaBaru();

        foreach (['Kelas Satu', 'Kelas Dua'] as $nama) {
            $this->actingAs($user)->post(route('wizard.kelas.store'), [
                'nama_kelas' => $nama,
                'sekolah' => 'SMKN 1 Subang',
                'tipe_periode' => 'bulanan',
                'persetujuan_data' => '1',
            ])->assertSessionHasNoErrors();
        }

        $this->actingAs($user)->get(route('wizard.kelas'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('peringatan');

        // Termasuk lewat POST langsung.
        $this->actingAs($user)->post(route('wizard.kelas.store'), [
            'nama_kelas' => 'Kelas Tiga',
            'sekolah' => 'SMKN 1 Subang',
            'tipe_periode' => 'bulanan',
            'persetujuan_data' => '1',
        ]);

        $this->assertSame(2, CurrentClassroom::withoutTenancy(fn () => Classroom::count()));
    }

    public function test_siswa_dibatasi_enam_puluh_per_kelas(): void
    {
        config(['kaskelas.batas.siswa_per_kelas' => 3]);

        [$kelas, $user] = $this->buatKelas();

        foreach (range(1, 3) as $i) {
            $this->actingAs($user)->post(route('siswa.store'), [
                'nama' => 'Siswa '.$i,
                'no_absen' => $i,
                'tgl_mulai_aktif' => '2026-01-01',
            ])->assertSessionHasNoErrors();
        }

        $this->actingAs($user)->post(route('siswa.store'), [
            'nama' => 'Siswa Keempat',
            'no_absen' => 4,
            'tgl_mulai_aktif' => '2026-01-01',
        ])->assertSessionHasErrors('nama');

        $this->assertSame(3, $this->dalamKelas($kelas, fn () => Student::count()));
    }

    /**
     * Menempel 80 nama ke kelas berbatas 60 tidak boleh gagal total.
     * Yang muat tetap masuk, sisanya dilaporkan apa adanya — bendahara perlu
     * tahu persis siapa yang belum, bukan cuma bahwa "ada yang gagal".
     */
    public function test_tempel_massal_memasukkan_yang_muat_dan_melaporkan_sisanya(): void
    {
        config(['kaskelas.batas.siswa_per_kelas' => 3]);

        [$kelas, $user] = $this->buatKelas();

        $daftar = collect(['Adinda', 'Bagas', 'Citra', 'Dimas', 'Elsa'])
            ->map(fn ($n, $i) => ($i + 1).'. '.$n)
            ->implode("\n");

        $this->followingRedirects()
            ->actingAs($user)
            ->post(route('siswa.massal.store'), [
                'daftar' => $daftar,
                'tgl_mulai_aktif' => '2026-01-01',
            ])
            ->assertOk()
            ->assertSee('Dimas')
            ->assertSee('Elsa');

        $nama = $this->dalamKelas($kelas, fn () => Student::pluck('nama')->all());

        $this->assertCount(3, $nama);
        $this->assertNotContains('Dimas', $nama);
        $this->assertNotContains('Elsa', $nama);
    }

    /** Menyunting siswa yang sudah ada tetap boleh, walau kelasnya sudah penuh. */
    public function test_kelas_penuh_masih_boleh_menyunting_siswa_lama(): void
    {
        config(['kaskelas.batas.siswa_per_kelas' => 1]);

        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->put(route('siswa.update', $siswa), [
            'nama' => 'Adinda Ayu',
            'no_absen' => 1,
            'tgl_mulai_aktif' => '2026-01-01',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Adinda Ayu', $this->dalamKelas($kelas, fn () => Student::find($siswa->id)->nama));
    }
}
