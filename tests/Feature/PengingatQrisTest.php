<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Services\KasService;
use App\Services\PengingatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pengingat tunggakan & QRIS statis (Fase 8 / v1.1).
 *
 * Dua fitur ini digabung dalam satu berkas karena keduanya data per kelas yang
 * tampil di luar akun bendahara — dan karena itu keduanya wajib lulus test
 * isolasi yang sama.
 *
 * Semua persiapan data menempuh HTTP seperti bendahara sungguhan.
 */
class PengingatQrisTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Generator teks pengingat
    |--------------------------------------------------------------------------
    */

    public function test_teks_pengingat_cocok_dengan_perhitungan_tunggakan_yang_sudah_ada(): void
    {
        [$kelas, $user, $siswa] = $this->siapkan();

        $pengingat = app(PengingatService::class);
        $kas = app(KasService::class);

        [$teks, $rincian, $total, $tunggakan] = $this->dalamKelas($kelas, fn () => [
            $pengingat->untukSiswa($siswa, '2026-10-01', $kelas),
            $pengingat->rincianTunggakan($siswa, $kelas),
            $pengingat->rincianTunggakan($siswa, $kelas)->sum(fn ($b) => $b['sisa'] + $b['denda']),
            $kas->tunggakanSiswa($siswa, $kelas),
        ]);

        // Tiga periode Januari–Maret 2026 @ Rp 5.000, belum dibayar sepeser pun.
        $this->assertCount(3, $rincian, 'Rincian harus memuat tiga periode yang belum lunas.');
        $this->assertSame(
            ['Januari 2026', 'Februari 2026', 'Maret 2026'],
            $rincian->pluck('label')->all(),
            'Rincian wajib urut terlama dulu.'
        );

        // INI inti test-nya: angka di teks pengingat tidak boleh punya hitungan
        // sendiri — harus sama dengan tunggakan yang dipakai laporan & dashboard.
        $this->assertSame(1500000, $tunggakan);
        $this->assertSame($tunggakan, $total, 'Total pengingat harus sama dengan tunggakan dari KasService.');

        $this->assertStringContainsString($siswa->nama, $teks);
        $this->assertStringContainsString('- Januari 2026: Rp 5.000', $teks);
        $this->assertStringContainsString('- Februari 2026: Rp 5.000', $teks);
        $this->assertStringContainsString('- Maret 2026: Rp 5.000', $teks);
        $this->assertStringContainsString('Total: Rp 15.000', $teks);
        $this->assertStringContainsString('1 Oktober 2026', $teks, 'Batas pembayaran harus ikut tertulis.');

        // Tidak boleh ada placeholder yang lolos belum tergantikan.
        foreach (PengingatService::PLACEHOLDER as $placeholder) {
            $this->assertStringNotContainsString($placeholder, $teks);
        }

        $this->actingAs($user)->get(route('pengingat.index'))
            ->assertOk()
            ->assertSee($siswa->nama)
            ->assertSee('Rp 15.000');
    }

    public function test_pembayaran_sebagian_mengecilkan_rincian_dan_total_pengingat(): void
    {
        [$kelas, $user, $siswa] = $this->siapkan();

        // Rp 12.000 menutup Januari & Februari penuh, Maret tinggal kurang Rp 3.000.
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 12000,
            'metode' => 'tunai',
        ])->assertSessionHasNoErrors();

        $pengingat = app(PengingatService::class);
        $kas = app(KasService::class);

        $this->dalamKelas($kelas, function () use ($pengingat, $kas, $kelas, $siswa) {
            $rincian = $pengingat->rincianTunggakan($siswa, $kelas);

            $this->assertCount(1, $rincian, 'Yang sudah lunas tidak boleh ikut ditagih lagi.');
            $this->assertSame('Maret 2026', $rincian->first()['label']);
            $this->assertSame(300000, $rincian->first()['sisa']);

            $teks = $pengingat->untukSiswa($siswa, null, $kelas);
            $this->assertStringContainsString('- Maret 2026: Rp 3.000', $teks);
            $this->assertStringNotContainsString('Januari 2026', $teks);
            $this->assertStringContainsString('Total: Rp 3.000', $teks);

            $this->assertSame($kas->tunggakanSiswa($siswa, $kelas), 300000);
        });
    }

    /** Siswa lunas tidak boleh menghasilkan teks apa pun — bukan teks kosong, tapi null. */
    public function test_siswa_tanpa_tunggakan_tidak_menghasilkan_teks_pengingat(): void
    {
        [$kelas, $user, $siswa] = $this->siapkan();

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 15000,
            'metode' => 'tunai',
        ])->assertSessionHasNoErrors();

        $pengingat = app(PengingatService::class);

        $this->dalamKelas($kelas, function () use ($pengingat, $kelas, $siswa) {
            $this->assertSame(0, app(KasService::class)->tunggakanSiswa($siswa, $kelas));
            $this->assertNull($pengingat->untukSiswa($siswa, null, $kelas));
            $this->assertTrue($pengingat->rincianTunggakan($siswa, $kelas)->isEmpty());

            // Juga tidak boleh muncul di hasil pengingat massal.
            $this->assertTrue(
                $pengingat->untukBanyakSiswa(collect([$siswa]), null, $kelas)->isEmpty(),
                'Siswa lunas tidak boleh ikut di daftar pengingat massal.'
            );
        });

        $this->actingAs($user)->get(route('pengingat.index'))
            ->assertOk()
            ->assertSee('Tidak ada yang perlu ditagih')
            ->assertDontSee('Lihat teksnya');
    }

    /** Periode yang belum jatuh tempo bukan tunggakan, jadi belum pantas ditagih. */
    public function test_periode_yang_belum_jatuh_tempo_tidak_masuk_pengingat(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        // Periode jauh di depan: jatuh temponya belum lewat.
        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => now()->addMonths(2)->startOfMonth()->toDateString(),
            'nominal' => 5000,
            'sampai' => now()->addMonths(3)->endOfMonth()->toDateString(),
        ])->assertSessionHasNoErrors();

        $pengingat = app(PengingatService::class);

        $this->dalamKelas($kelas, function () use ($pengingat, $kelas, $siswa) {
            $this->assertTrue($pengingat->rincianTunggakan($siswa, $kelas)->isEmpty());
            $this->assertNull($pengingat->untukSiswa($siswa, null, $kelas));
        });
    }

    public function test_denda_ikut_tertulis_di_rincian_pengingat(): void
    {
        [$kelas, $user, $siswa] = $this->siapkan();

        $this->actingAs($user)->patch(route('pengaturan.update'), [
            'nama_kelas' => $kelas->nama_kelas,
            'sekolah' => $kelas->sekolah,
            'denda_aktif' => 1,
            'denda_mode' => 'tetap',
            'denda_nominal' => 2000,
            'grace_days' => 0,
        ])->assertSessionHasNoErrors();

        $pengingat = app(PengingatService::class);
        $kelasSegar = $kelas->fresh();

        $this->dalamKelas($kelasSegar, function () use ($pengingat, $kelasSegar, $siswa) {
            $teks = $pengingat->untukSiswa($siswa, null, $kelasSegar);

            $this->assertStringContainsString('- Januari 2026: Rp 5.000 + denda Rp 2.000', $teks);
            // 3 × (5.000 + 2.000)
            $this->assertStringContainsString('Total: Rp 21.000', $teks);
            $this->assertSame(
                app(KasService::class)->tunggakanSiswa($siswa, $kelasSegar),
                $pengingat->rincianTunggakan($siswa, $kelasSegar)->sum(fn ($b) => $b['sisa'] + $b['denda']),
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Template per kelas
    |--------------------------------------------------------------------------
    */

    public function test_template_kelas_dipakai_menggantikan_template_bawaan(): void
    {
        [$kelas, $user, $siswa] = $this->siapkan();

        $this->actingAs($user)->patch(route('pengaturan.pengingat'), [
            'template_pengingat' => "Woi {nama}! Utangmu:\n{rincian}\nJumlahnya {total}, bayar sebelum {batas}.",
        ])->assertRedirect(route('pengaturan.edit'))->assertSessionHas('sukses');

        $pengingat = app(PengingatService::class);
        $kelasSegar = $kelas->fresh();

        $this->dalamKelas($kelasSegar, function () use ($pengingat, $kelasSegar, $siswa) {
            $teks = $pengingat->untukSiswa($siswa, '2026-10-01', $kelasSegar);

            $this->assertStringStartsWith('Woi '.$siswa->nama.'! Utangmu:', $teks);
            $this->assertStringContainsString('Jumlahnya Rp 15.000, bayar sebelum 1 Oktober 2026.', $teks);
            $this->assertStringNotContainsString('mohon izin mengingatkan', $teks);
        });
    }

    public function test_template_tanpa_placeholder_nominal_ditolak(): void
    {
        [$kelas, $user] = $this->siapkan();

        $this->actingAs($user)->patch(route('pengaturan.pengingat'), [
            'template_pengingat' => 'Halo {nama}, tolong bayar ya.',
        ])->assertSessionHasErrors('template_pengingat');

        $this->actingAs($user)->patch(route('pengaturan.pengingat'), [
            'template_pengingat' => '',
        ])->assertSessionHasErrors('template_pengingat');

        $this->assertNull($kelas->fresh()->template_pengingat, 'Template tidak boleh tersimpan saat ditolak.');
    }

    public function test_template_bawaan_dipakai_selama_kelas_belum_mengubahnya(): void
    {
        [$kelas, , $siswa] = $this->siapkan();

        $pengingat = app(PengingatService::class);

        $this->dalamKelas($kelas, function () use ($pengingat, $kelas, $siswa) {
            $this->assertNull($kelas->template_pengingat);
            $this->assertSame(PengingatService::TEMPLATE_BAWAAN, $pengingat->template($kelas));
            $this->assertStringContainsString(
                'mohon izin mengingatkan',
                $pengingat->untukSiswa($siswa, null, $kelas),
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Halaman pengingat: salin satu & salin banyak
    |--------------------------------------------------------------------------
    */

    public function test_halaman_pengingat_menyediakan_teks_tiap_penunggak_dan_salin_massal(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu', 'Bagas Pratama', 'Citra Lestari']);

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-03-31',
        ])->assertSessionHasNoErrors();

        // Citra melunasi semuanya; dua lainnya menunggak.
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->last()->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 15000,
            'metode' => 'tunai',
        ])->assertSessionHasNoErrors();

        $respons = $this->actingAs($user)->get(route('pengingat.index'))->assertOk();

        $respons->assertSee('2 siswa menunggak')
            ->assertSee('Adinda Ayu')
            ->assertSee('Bagas Pratama')
            // Tiap penunggak punya textarea teksnya sendiri, itulah yang dibaca
            // tombol salin. Yang sudah lunas tidak boleh punya satu pun.
            ->assertSee('id="teks-'.$siswa->first()->id.'"', false)
            ->assertSee('id="teks-'.$siswa->get(1)->id.'"', false)
            ->assertDontSee('id="teks-'.$siswa->last()->id.'"', false)
            // Salin massal: ada pilih-semua dan tombol salin banyak sekaligus.
            ->assertSee('Pilih semua')
            ->assertSee('Salin pengingat terpilih');
    }

    public function test_batas_pembayaran_bisa_diganti_dari_halaman_pengingat(): void
    {
        [, $user] = $this->siapkan();

        $this->actingAs($user)->get(route('pengingat.index', ['batas' => '2026-12-31']))
            ->assertOk()
            ->assertSee('31 Desember 2026');

        $this->actingAs($user)->get(route('pengingat.index', ['batas' => 'bukan-tanggal']))
            ->assertSessionHasErrors('batas');
    }

    /*
    |--------------------------------------------------------------------------
    | QRIS statis
    |--------------------------------------------------------------------------
    */

    public function test_qris_diunggah_lalu_tampil_di_halaman_kelas(): void
    {
        Storage::fake('local');

        [$kelas, $user] = $this->buatKelas();

        // Form-nya wajib memperingatkan bahwa gambar ini terlihat siapa pun
        // yang punya tautan kelas — halaman kelas tidak butuh login.
        $this->actingAs($user)->get(route('pengaturan.edit'))
            ->assertOk()
            ->assertSee('terlihat siapa pun yang punya tautan kelas');

        $this->actingAs($user)->post(route('pengaturan.qris'), [
            'qris_nama_pemilik' => 'Erlangga H. (bendahara)',
            'qris' => UploadedFile::fake()->image('qris kelas asli.png', 300, 300),
        ])->assertRedirect(route('pengaturan.edit'))->assertSessionHas('sukses');

        $kelas->refresh();

        $this->assertNotNull($kelas->qris_path);
        $this->assertSame('Erlangga H. (bendahara)', $kelas->qris_nama_pemilik);

        // Disimpan privat, dipisah per kelas, namanya diacak.
        $this->assertStringStartsWith('kelas-'.$kelas->id.'/qris/', $kelas->qris_path);
        $this->assertStringNotContainsString('qris kelas asli', $kelas->qris_path);
        Storage::disk('local')->assertExists($kelas->qris_path);

        // Tampil di halaman kelas, tanpa login.
        $this->get(route('publik.kelas', $kelas->public_token))
            ->assertOk()
            ->assertSee('Bayar lewat QRIS')
            ->assertSee('Erlangga H. (bendahara)')
            ->assertSee(route('publik.qris', $kelas->public_token), false);

        $this->get(route('publik.qris', $kelas->public_token))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_halaman_kelas_tanpa_qris_tidak_menampilkan_bagiannya(): void
    {
        [$kelas] = $this->buatKelas();

        $this->get(route('publik.kelas', $kelas->public_token))
            ->assertOk()
            ->assertDontSee('Bayar lewat QRIS');

        $this->get(route('publik.qris', $kelas->public_token))->assertNotFound();
    }

    public function test_unggahan_qris_bukan_gambar_ditolak(): void
    {
        Storage::fake('local');

        [$kelas, $user] = $this->buatKelas();

        // PDF: sah untuk bukti transfer, tapi tidak untuk QRIS yang dipasang <img>.
        $this->actingAs($user)->post(route('pengaturan.qris'), [
            'qris_nama_pemilik' => 'Bendahara',
            'qris' => UploadedFile::fake()->create('qris.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('qris');

        // Berkas yang dinamai .png tapi isinya bukan gambar: ekstensinya lolos
        // aturan mimes, dan justru aturan mimetypes yang menangkapnya.
        $this->actingAs($user)->post(route('pengaturan.qris'), [
            'qris_nama_pemilik' => 'Bendahara',
            'qris' => UploadedFile::fake()->create('qris.png', 10, 'text/plain'),
        ])->assertSessionHasErrors('qris');

        // Skrip yang menyamar: ditolak aturan ekstensi maupun aturan jenis isi.
        $this->actingAs($user)->post(route('pengaturan.qris'), [
            'qris_nama_pemilik' => 'Bendahara',
            'qris' => UploadedFile::fake()->create('qris.php', 10, 'application/x-httpd-php'),
        ])->assertSessionHasErrors('qris');

        $this->assertNull($kelas->fresh()->qris_path);
    }

    public function test_unggahan_qris_lebih_dari_2mb_ditolak(): void
    {
        Storage::fake('local');

        [$kelas, $user] = $this->buatKelas();

        $this->actingAs($user)->post(route('pengaturan.qris'), [
            'qris_nama_pemilik' => 'Bendahara',
            'qris' => UploadedFile::fake()->image('qris.png')->size(2049),
        ])->assertSessionHasErrors('qris');

        $this->assertNull($kelas->fresh()->qris_path);

        // Tepat di bawah batas harus lolos, supaya batasnya benar-benar 2 MB.
        $this->actingAs($user)->post(route('pengaturan.qris'), [
            'qris_nama_pemilik' => 'Bendahara',
            'qris' => UploadedFile::fake()->image('qris.png')->size(2048),
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($kelas->fresh()->qris_path);
    }

    public function test_qris_wajib_disertai_nama_pemilik(): void
    {
        Storage::fake('local');

        [$kelas, $user] = $this->buatKelas();

        $this->actingAs($user)->post(route('pengaturan.qris'), [
            'qris' => UploadedFile::fake()->image('qris.png'),
        ])->assertSessionHasErrors('qris_nama_pemilik');

        $this->assertNull($kelas->fresh()->qris_path);
    }

    public function test_qris_diganti_membuang_berkas_lama(): void
    {
        Storage::fake('local');

        [$kelas, $user] = $this->buatKelas();

        $this->actingAs($user)->post(route('pengaturan.qris'), [
            'qris_nama_pemilik' => 'Bendahara Lama',
            'qris' => UploadedFile::fake()->image('satu.png'),
        ])->assertSessionHasNoErrors();

        $lama = $kelas->fresh()->qris_path;

        $this->actingAs($user)->post(route('pengaturan.qris'), [
            'qris_nama_pemilik' => 'Bendahara Baru',
            'qris' => UploadedFile::fake()->image('dua.png'),
        ])->assertSessionHasNoErrors();

        $baru = $kelas->fresh()->qris_path;

        $this->assertNotSame($lama, $baru);
        Storage::disk('local')->assertMissing($lama);
        Storage::disk('local')->assertExists($baru);
    }

    /** Ganti nama pemilik saja tanpa mengunggah ulang gambarnya harus boleh. */
    public function test_nama_pemilik_bisa_diubah_tanpa_mengunggah_ulang(): void
    {
        Storage::fake('local');

        [$kelas, $user] = $this->buatKelas();

        $this->actingAs($user)->post(route('pengaturan.qris'), [
            'qris_nama_pemilik' => 'Nama Lama',
            'qris' => UploadedFile::fake()->image('qris.png'),
        ])->assertSessionHasNoErrors();

        $path = $kelas->fresh()->qris_path;

        $this->actingAs($user)->post(route('pengaturan.qris'), [
            'qris_nama_pemilik' => 'Nama Baru',
        ])->assertSessionHasNoErrors();

        $kelas->refresh();

        $this->assertSame('Nama Baru', $kelas->qris_nama_pemilik);
        $this->assertSame($path, $kelas->qris_path, 'Gambarnya tidak boleh hilang.');
        Storage::disk('local')->assertExists($path);
    }

    public function test_qris_dihapus_beserta_berkasnya(): void
    {
        Storage::fake('local');

        [$kelas, $user] = $this->buatKelas();

        $this->actingAs($user)->post(route('pengaturan.qris'), [
            'qris_nama_pemilik' => 'Bendahara',
            'qris' => UploadedFile::fake()->image('qris.png'),
        ])->assertSessionHasNoErrors();

        $path = $kelas->fresh()->qris_path;

        $this->actingAs($user)->delete(route('pengaturan.qris.hapus'))
            ->assertRedirect(route('pengaturan.edit'))
            ->assertSessionHas('sukses');

        $kelas->refresh();

        $this->assertNull($kelas->qris_path);
        $this->assertNull($kelas->qris_nama_pemilik);
        Storage::disk('local')->assertMissing($path);

        $this->get(route('publik.kelas', $kelas->public_token))->assertDontSee('Bayar lewat QRIS');
        $this->get(route('publik.qris', $kelas->public_token))->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Isolasi tenant
    |--------------------------------------------------------------------------
    */

    public function test_template_dan_qris_kelas_a_tidak_terlihat_dari_kelas_b(): void
    {
        Storage::fake('local');

        [$kelasA, $userA, $siswaA] = $this->siapkan();
        // Nama siswanya sengaja dibedakan: kalau namanya sama, kebocoran nama
        // tidak bisa dibedakan dari kebetulan.
        [$kelasB, $userB, $siswaB] = $this->siapkan('XI IPA 3', 'SMAN 2 Bandung', 'Fajar Nugraha');

        $this->actingAs($userA)->patch(route('pengaturan.pengingat'), [
            'template_pengingat' => 'RAHASIA KELAS A {rincian} {total}',
        ])->assertSessionHasNoErrors();

        $this->actingAs($userA)->post(route('pengaturan.qris'), [
            'qris_nama_pemilik' => 'Bendahara Kelas A',
            'qris' => UploadedFile::fake()->image('qris-a.png'),
        ])->assertSessionHasNoErrors();

        $pathA = $kelasA->fresh()->qris_path;

        // Pengaturan kelas B tidak memuat sepotong pun data kelas A.
        $this->actingAs($userB)->get(route('pengaturan.edit'))
            ->assertOk()
            ->assertDontSee('RAHASIA KELAS A')
            ->assertDontSee('Bendahara Kelas A')
            ->assertDontSee($pathA);

        $this->assertNull($kelasB->fresh()->qris_path, 'Kelas B tidak boleh kecipratan QRIS kelas A.');
        $this->assertNull($kelasB->fresh()->template_pengingat);

        // Token kelas B tidak bisa dipakai membuka gambar QRIS kelas A.
        $this->get(route('publik.qris', $kelasB->public_token))->assertNotFound();
        $this->get(route('publik.kelas', $kelasB->public_token))
            ->assertOk()
            ->assertDontSee('Bendahara Kelas A');

        // Template kelas A tidak ikut mengubah teks pengingat kelas B.
        $pengingat = app(PengingatService::class);
        $kelasBSegar = $kelasB->fresh();

        $this->dalamKelas($kelasBSegar, function () use ($pengingat, $kelasBSegar) {
            $this->assertSame(PengingatService::TEMPLATE_BAWAAN, $pengingat->template($kelasBSegar));
        });

        // Dan pengingat kelas B hanya menyebut siswanya sendiri.
        $this->actingAs($userB)->get(route('pengingat.index'))
            ->assertOk()
            ->assertSee($siswaB->nama)
            ->assertSee('id="teks-'.$siswaB->id.'"', false)
            ->assertDontSee($siswaA->nama)
            ->assertDontSee('id="teks-'.$siswaA->id.'"', false)
            ->assertDontSee('RAHASIA KELAS A');
    }

    public function test_bendahara_kelas_b_tidak_bisa_menghapus_qris_kelas_a(): void
    {
        Storage::fake('local');

        [$kelasA, $userA] = $this->buatKelas();
        [, $userB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $this->actingAs($userA)->post(route('pengaturan.qris'), [
            'qris_nama_pemilik' => 'Bendahara Kelas A',
            'qris' => UploadedFile::fake()->image('qris-a.png'),
        ])->assertSessionHasNoErrors();

        $pathA = $kelasA->fresh()->qris_path;

        // Route-nya tidak menerima ID kelas dari mana pun — kelas aktif selalu
        // dari session. Jadi aksi hapus milik B hanya menyentuh kelas B sendiri.
        $this->actingAs($userB)->delete(route('pengaturan.qris.hapus'))->assertRedirect();

        $this->assertSame($pathA, $kelasA->fresh()->qris_path, 'QRIS kelas A harus utuh.');
        Storage::disk('local')->assertExists($pathA);
    }

    public function test_pengingat_dan_qris_butuh_login(): void
    {
        [$kelas, $user] = $this->buatKelas();

        // Pengingat ada di balik middleware 'siap'; yang diuji di sini gerbang
        // login, jadi penyiapan kelasnya dibereskan dulu.
        $this->buatSiswa($kelas, 'Adinda');
        $this->buatPeriode($kelas);

        $this->get(route('pengingat.index'))->assertRedirect(route('login'));
        $this->patch(route('pengaturan.pengingat'), ['template_pengingat' => '{total}'])
            ->assertRedirect(route('login'));
        $this->post(route('pengaturan.qris'), ['qris_nama_pemilik' => 'X'])->assertRedirect(route('login'));
        $this->delete(route('pengaturan.qris.hapus'))->assertRedirect(route('login'));

        $this->actingAs($user)->get(route('pengingat.index'))->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Pembantu
    |--------------------------------------------------------------------------
    */

    /**
     * Kelas + satu siswa + tiga periode Januari–Maret 2026 @ Rp 5.000,
     * semuanya sudah lewat jatuh tempo dan belum dibayar.
     *
     * @return array{0: Classroom, 1: User, 2: Student}
     */
    private function siapkan(
        string $namaKelas = 'XII TRPL 1',
        string $sekolah = 'SMKN 1 Subang',
        string $namaSiswa = 'Adinda Ayu',
    ): array {
        [$kelas, $user] = $this->buatKelas($namaKelas, $sekolah);
        $siswa = $this->tambahSiswa($user, $kelas, [$namaSiswa])->first();

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-03-31',
        ])->assertSessionHasNoErrors();

        return [$kelas, $user, $siswa];
    }

    /** @return \Illuminate\Support\Collection<int, Student> */
    private function tambahSiswa(User $user, Classroom $kelas, array $nama): \Illuminate\Support\Collection
    {
        $this->actingAs($user)->post(route('siswa.massal.store'), [
            'daftar' => collect($nama)->map(fn ($n, $i) => ($i + 1).'. '.$n)->implode("\n"),
            'tgl_mulai_aktif' => '2026-01-01',
        ])->assertRedirect(route('siswa.index'));

        return $this->dalamKelas($kelas, fn () => Student::whereIn('nama', $nama)->urutAbsen()->get());
    }
}
