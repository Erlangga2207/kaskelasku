<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BookClosing;
use App\Models\Campaign;
use App\Models\Classroom;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Period;
use App\Models\Student;
use App\Models\User;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * AUDIT ISOLASI MENYELURUH — syarat selesainya Fase 10.
 *
 * Sampai v1.2 aplikasi ini hanya dipakai satu orang, jadi kebocoran antar kelas
 * tidak berdampak pada siapa pun. Setelah pendaftaran dibuka untuk umum,
 * kebocoran berarti catatan uang satu kelas terlihat oleh orang asing.
 *
 * Berkas ini menyapu SELURUH route bendahara yang menerima id, satu per satu,
 * dan menuntut jawaban 404 untuk id milik kelas lain. Berbeda dari test isolasi
 * per modul yang sudah ada, sapuan ini digerakkan dari daftar route — jadi
 * modul baru yang lupa diuji akan langsung kelihatan di sini, bukan menunggu
 * ada yang ingat menuliskan test-nya.
 */
class IsolasiMenyeluruhTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Membangun satu kelas lengkap berisi SETIAP jenis baris yang ada di
     * aplikasi ini, semuanya lewat HTTP seperti bendahara sungguhan.
     *
     * @return array{kelas: Classroom, user: User, id: array<string, mixed>}
     */
    protected function kelasLengkap(string $nama, string $sekolah): array
    {
        [$kelas, $user] = $this->buatKelas($nama, $sekolah);

        $siswa = $this->buatSiswa($kelas, 'Siswa '.$nama);
        $this->buatPeriode($kelas, '2026-01-01', 5000, '2026-02-28');

        // Campaign + tagihannya.
        $this->actingAs($user)->post(route('campaign.store'), [
            'nama' => 'Studi Tour '.$nama,
            'nominal_per_siswa' => 50000,
            'deadline' => '2026-06-30',
            'peserta' => [$siswa->id],
        ])->assertSessionHasNoErrors();

        // Pembayaran + bukti + alokasi.
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => '2026-01-10',
            'jumlah' => 80000,
            'metode' => 'transfer',
            'bukti' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertSessionHasNoErrors();

        // Kategori pengeluaran khusus kelas ini + pengeluaran.
        $this->actingAs($user)->post(route('kategori.store'), ['nama' => 'Kategori '.$nama])
            ->assertSessionHasNoErrors();

        $kategori = $this->dalamKelas($kelas, fn () => ExpenseCategory::whereNotNull('classroom_id')->firstOrFail());

        $this->actingAs($user)->post(route('pengeluaran.store'), [
            'tanggal' => '2026-01-11',
            'category_id' => $kategori->id,
            'jumlah' => 3000,
            'keterangan' => 'belanja '.$nama,
            'bukti' => UploadedFile::fake()->image('nota.jpg'),
        ])->assertSessionHasNoErrors();

        // Tutup buku.
        $this->actingAs($user)->post(route('tutup-buku.store'), [
            'label' => 'Semester Ganjil '.$nama,
            'tgl_mulai' => '2026-01-01',
            'tgl_selesai' => '2026-01-31',
        ])->assertSessionHasNoErrors();

        $this->flushSession();

        $id = $this->dalamKelas($kelas, fn () => [
            'siswa' => Student::firstOrFail()->id,
            'periode' => Period::firstOrFail()->id,
            'tagihan' => Bill::firstOrFail()->id,
            'pembayaran' => Payment::firstOrFail()->id,
            'pengeluaran' => Expense::firstOrFail()->id,
            'kategori' => $kategori->id,
            'campaign' => Campaign::firstOrFail()->id,
            'closing' => BookClosing::firstOrFail()->id,
        ]);

        return ['kelas' => $kelas, 'user' => $user, 'id' => $id];
    }

    /*
    |--------------------------------------------------------------------------
    | Sapuan route
    |--------------------------------------------------------------------------
    */

    /**
     * Setiap route bendahara ber-id, ditembak dengan id milik kelas lain.
     *
     * Polanya sama untuk semuanya: bendahara kelas A → id kelas B → 404.
     */
    public static function routeBerId(): array
    {
        return [
            // modul, nama route, method, kunci id
            'siswa — lihat' => ['siswa', 'siswa.show', 'get', 'siswa'],
            'siswa — form ubah' => ['siswa', 'siswa.edit', 'get', 'siswa'],
            'siswa — simpan ubahan' => ['siswa', 'siswa.update', 'put', 'siswa'],
            'siswa — nonaktifkan' => ['siswa', 'siswa.destroy', 'delete', 'siswa'],
            'siswa — aktifkan lagi' => ['siswa', 'siswa.restore', 'patch', 'siswa'],

            'periode — ubah nominal' => ['periode', 'periode.update', 'patch', 'periode'],
            'periode — tandai libur' => ['periode', 'periode.libur', 'patch', 'periode'],

            'tagihan — bebaskan' => ['tagihan', 'tagihan.bebas', 'patch', 'tagihan'],

            'pembayaran — hapus' => ['pembayaran', 'pembayaran.destroy', 'delete', 'pembayaran'],
            'pembayaran — unduh bukti' => ['pembayaran', 'pembayaran.bukti', 'get', 'pembayaran'],

            'pengeluaran — form ubah' => ['pengeluaran', 'pengeluaran.edit', 'get', 'pengeluaran'],
            'pengeluaran — simpan ubahan' => ['pengeluaran', 'pengeluaran.update', 'put', 'pengeluaran'],
            'pengeluaran — hapus' => ['pengeluaran', 'pengeluaran.destroy', 'delete', 'pengeluaran'],
            'pengeluaran — unduh bukti' => ['pengeluaran', 'pengeluaran.bukti', 'get', 'pengeluaran'],
            'pengeluaran — hapus kategori' => ['pengeluaran', 'kategori.destroy', 'delete', 'kategori'],

            'campaign — lihat' => ['campaign', 'campaign.show', 'get', 'campaign'],
            'campaign — form ubah' => ['campaign', 'campaign.edit', 'get', 'campaign'],
            'campaign — simpan ubahan' => ['campaign', 'campaign.update', 'patch', 'campaign'],
            'campaign — ubah status' => ['campaign', 'campaign.status', 'patch', 'campaign'],
            'campaign — hapus' => ['campaign', 'campaign.destroy', 'delete', 'campaign'],

            'tutup buku — serah terima' => ['tutup-buku', 'tutup-buku.serah-terima', 'get', 'closing'],
            'tutup buku — buka kembali' => ['tutup-buku', 'tutup-buku.destroy', 'delete', 'closing'],
        ];
    }

    /**
     * @dataProvider routeBerId
     */
    public function test_route_ber_id_menolak_id_kelas_lain(string $modul, string $route, string $method, string $kunci): void
    {
        Storage::fake('local');

        $a = $this->kelasLengkap('XII TRPL 1', 'SMKN 1 Subang');
        $b = $this->kelasLengkap('XI IPA 3', 'SMAN 2 Bandung');

        $idB = $b['id'][$kunci];

        // Payload sengaja dibuat SAH sepenuhnya — nilai-nilai miliknya sendiri,
        // milik kelas A. Kalau isolasinya bocor, request ini akan benar-benar
        // berhasil mengubah baris kelas B. Payload yang gagal validasi juga
        // menghasilkan 302, dan 302 semacam itu tidak membuktikan apa-apa.
        $payload = [
            'nama' => 'Dibajak', 'no_absen' => 41, 'tgl_mulai_aktif' => '2026-01-01',
            'nominal' => 9999, 'is_libur' => '1',
            'bebas' => '1', 'alasan_bebas' => 'dibajak',
            'tanggal' => '2026-02-01', 'jumlah' => 1000, 'keterangan' => 'dibajak',
            'category_id' => $a['id']['kategori'],
            'nominal_per_siswa' => 12345, 'status' => 'dibatalkan',
            'peserta' => [$a['id']['siswa']],
        ];

        $this->actingAs($a['user'])
            ->{$method}(route($route, $idB), $payload)
            ->assertNotFound("Route {$route} (modul {$modul}) membocorkan id kelas lain.");
    }

    /**
     * Penjaga daftar di atas.
     *
     * Kalau suatu hari ada route bendahara ber-id yang baru dan lupa
     * didaftarkan, test ini yang gagal — bukan diam-diam lolos sampai ada yang
     * menemukannya di produksi.
     */
    public function test_tidak_ada_route_bendahara_ber_id_yang_luput_dari_sapuan(): void
    {
        $diuji = collect(static::routeBerId())->map(fn ($baris) => $baris[1])->unique();

        // Route ber-id yang memang bukan bagian dari isolasi tenant:
        // halaman publik bertoken (diuji di HalamanKelasPublikTest), verifikasi
        // email (milik akun, bukan kelas), pemulihan kelas (diuji di
        // DaurHidupKelasTest), ekspor (parameternya jenis berkas, bukan id
        // baris), dan route bawaan Laravel untuk berkas lokal.
        $dikecualikan = [
            'publik.kelas', 'publik.manifest', 'publik.qris',
            'verification.verify', 'kelas.restore', 'ekspor.unduh',
            'storage.local', 'storage.local.upload',
        ];

        $luput = collect(Route::getRoutes())
            ->filter(fn ($r) => str_contains($r->uri(), '{'))
            ->map(fn ($r) => (string) $r->getName())
            ->filter()
            ->unique()
            ->reject(fn ($nama) => in_array($nama, $dikecualikan, true))
            ->reject(fn ($nama) => $diuji->contains($nama))
            ->values()
            ->all();

        $this->assertSame([], $luput,
            'Ada route ber-id yang belum masuk sapuan isolasi. Tambahkan ke routeBerId() '
            .'(atau ke daftar pengecualian, kalau memang bukan route tenant).');
    }

    /*
    |--------------------------------------------------------------------------
    | Kebocoran lewat daftar, bukan lewat id
    |--------------------------------------------------------------------------
    */

    /** Tidak satu pun halaman daftar boleh menampilkan baris milik kelas lain. */
    public function test_tidak_ada_halaman_daftar_yang_menampilkan_isi_kelas_lain(): void
    {
        Storage::fake('local');

        $a = $this->kelasLengkap('XII TRPL 1', 'SMKN 1 Subang');
        $b = $this->kelasLengkap('XI IPA 3', 'SMAN 2 Bandung');

        $jejakB = [
            'Siswa XI IPA 3', 'SMAN 2 Bandung', 'Studi Tour XI IPA 3',
            'belanja XI IPA 3', 'Kategori XI IPA 3', 'Semester Ganjil XI IPA 3',
            $b['kelas']->public_token,
        ];

        foreach ([
            'dashboard', 'siswa.index', 'periode.index', 'pembayaran.index',
            'pembayaran.create', 'pengeluaran.index', 'pengeluaran.create',
            'campaign.index', 'campaign.create', 'laporan.index', 'audit.index',
            'pengingat.index', 'tutup-buku.index', 'pengaturan.edit', 'ekspor.index',
        ] as $route) {
            $this->flushSession();

            $isi = $this->actingAs($a['user'])->get(route($route))->assertOk()->getContent();

            foreach ($jejakB as $jejak) {
                $this->assertStringNotContainsString($jejak, $isi,
                    "Halaman {$route} menampilkan jejak kelas B: {$jejak}");
            }
        }
    }

    /**
     * Lapisan model, bukan lapisan HTTP.
     *
     * Global scope harus menutup setiap model tenant, termasuk yang tidak punya
     * halaman daftar sendiri. Dihitung langsung ke SQL sebagai pembanding —
     * kalau global scope-nya mati, angka keduanya akan berbeda.
     */
    public function test_setiap_model_tenant_tertutup_global_scope(): void
    {
        Storage::fake('local');

        $a = $this->kelasLengkap('XII TRPL 1', 'SMKN 1 Subang');
        $this->kelasLengkap('XI IPA 3', 'SMAN 2 Bandung');

        $model = [
            Student::class => 'students',
            Period::class => 'periods',
            Bill::class => 'bills',
            Payment::class => 'payments',
            PaymentAllocation::class => 'payment_allocations',
            Expense::class => 'expenses',
            Campaign::class => 'campaigns',
            BookClosing::class => 'book_closings',
            AuditLog::class => 'audit_logs',
        ];

        foreach ($model as $kelasModel => $tabel) {
            $lewatScope = $this->dalamKelas($a['kelas'], fn () => $kelasModel::count());

            $lewatSql = DB::table($tabel)
                ->where('classroom_id', $a['kelas']->id)
                // Hanya tiga model ini yang memakai soft delete.
                ->when(
                    in_array($tabel, ['students', 'payments', 'expenses'], true),
                    fn ($q) => $q->whereNull('deleted_at')
                )
                ->count();

            $this->assertSame($lewatSql, $lewatScope,
                "Global scope {$kelasModel} tidak cocok dengan hitungan SQL langsung — "
                .'berarti ada baris kelas lain yang ikut terlihat, atau baris sendiri yang hilang.');

            $this->assertGreaterThan(0, $lewatScope,
                "Persiapan test salah: tidak ada satu pun baris {$kelasModel} untuk diuji.");
        }
    }

    /**
     * Gagal-tertutup. Tanpa kelas aktif, query mengembalikan NOL baris —
     * bukan seluruh baris. Halaman kosong bisa diperbaiki; data kelas lain yang
     * telanjur terlihat tidak bisa ditarik kembali.
     */
    public function test_tanpa_kelas_aktif_seluruh_model_tenant_mengembalikan_nol_baris(): void
    {
        Storage::fake('local');

        $this->kelasLengkap('XII TRPL 1', 'SMKN 1 Subang');

        CurrentClassroom::forget();

        foreach ([
            Student::class, Period::class, Bill::class, Payment::class,
            PaymentAllocation::class, Expense::class,
            Campaign::class, BookClosing::class,
        ] as $model) {
            $this->assertSame(0, $model::count(),
                "{$model} membocorkan baris saat tidak ada kelas aktif.");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | classroom_id tidak pernah datang dari request
    |--------------------------------------------------------------------------
    */

    /** Hidden input classroom_id yang diutak-atik tidak boleh berefek apa pun. */
    public function test_classroom_id_di_request_tidak_pernah_dipakai(): void
    {
        Storage::fake('local');

        $a = $this->kelasLengkap('XII TRPL 1', 'SMKN 1 Subang');
        $b = $this->kelasLengkap('XI IPA 3', 'SMAN 2 Bandung');

        $siswaB = $b['id']['siswa'];

        // Menambah siswa sambil menyelipkan classroom_id kelas B.
        $this->actingAs($a['user'])->post(route('siswa.store'), [
            'nama' => 'Siswa Selundupan',
            'no_absen' => 39,
            'tgl_mulai_aktif' => '2026-01-01',
            'classroom_id' => $b['kelas']->id,
        ])->assertSessionHasNoErrors();

        $mendarat = DB::table('students')->where('nama', 'Siswa Selundupan')->first();

        $this->assertSame($a['kelas']->id, (int) $mendarat->classroom_id,
            'classroom_id dari request berhasil memindahkan baris ke kelas lain.');

        // Mencatat pembayaran untuk siswa kelas B sambil menyelipkan id kelasnya.
        $this->actingAs($a['user'])->post(route('pembayaran.store'), [
            'student_id' => $siswaB,
            'tanggal' => '2026-02-01',
            'jumlah' => 5000,
            'metode' => 'tunai',
            'classroom_id' => $b['kelas']->id,
        ])->assertSessionHasErrors('student_id');

        // Pengeluaran ke kategori milik kelas B.
        $this->actingAs($a['user'])->post(route('pengeluaran.store'), [
            'tanggal' => '2026-02-01',
            'category_id' => $b['id']['kategori'],
            'jumlah' => 1000,
            'keterangan' => 'menumpang kategori kelas lain',
            'classroom_id' => $b['kelas']->id,
        ])->assertSessionHasErrors('category_id');
    }

    /** Alokasi manual ke tagihan kelas lain ditolak — uang tidak boleh menyeberang. */
    public function test_alokasi_manual_ke_tagihan_kelas_lain_ditolak(): void
    {
        Storage::fake('local');

        $a = $this->kelasLengkap('XII TRPL 1', 'SMKN 1 Subang');
        $b = $this->kelasLengkap('XI IPA 3', 'SMAN 2 Bandung');

        $siswaA = $a['id']['siswa'];
        $tagihanB = $b['id']['tagihan'];

        $this->actingAs($a['user'])->post(route('pembayaran.store'), [
            'student_id' => $siswaA,
            'tanggal' => '2026-02-01',
            'jumlah' => 5000,
            'metode' => 'tunai',
            'mode_alokasi' => 'manual',
            'alokasi' => [$tagihanB => 5000],
        ])->assertSessionHas('galat');

        // Tagihan B memang sudah punya alokasi dari pembayaran kelas B sendiri.
        // Yang harus nol adalah alokasi milik KELAS A yang menempel padanya.
        $this->assertSame(0, DB::table('payment_allocations')
            ->where('bill_id', $tagihanB)
            ->where('classroom_id', $a['kelas']->id)
            ->count());

        $this->assertSame(1, DB::table('payments')
            ->where('classroom_id', $a['kelas']->id)
            ->whereNull('deleted_at')
            ->count(), 'Hanya pembayaran dari persiapan yang boleh ada.');
    }

    /*
    |--------------------------------------------------------------------------
    | Permukaan baru v2.0
    |--------------------------------------------------------------------------
    */

    /**
     * Pengaturan, QRIS, dan token selalu mengenai kelas AKTIF — tidak satu pun
     * dari route-nya menerima id kelas, jadi kelas lain memang tidak bisa
     * disebut. Yang diuji di sini: keadaan itu benar-benar bertahan.
     */
    public function test_route_tanpa_id_selalu_mengenai_kelas_aktif_sendiri(): void
    {
        Storage::fake('local');

        $a = $this->kelasLengkap('XII TRPL 1', 'SMKN 1 Subang');
        $b = $this->kelasLengkap('XI IPA 3', 'SMAN 2 Bandung');

        $tokenBSebelum = CurrentClassroom::withoutTenancy(
            fn () => Classroom::findOrFail($b['kelas']->id)->public_token
        );

        // Bendahara A memutar token dan mengubah pengaturan sambil menyelipkan
        // id kelas B di mana-mana.
        $this->actingAs($a['user'])->patch(route('pengaturan.token'), ['classroom_id' => $b['kelas']->id]);
        $this->actingAs($a['user'])->patch(route('pengaturan.update'), [
            'nama_kelas' => 'Dibajak',
            'sekolah' => 'Sekolah Bajakan',
            'classroom_id' => $b['kelas']->id,
        ]);

        $setelah = CurrentClassroom::withoutTenancy(fn () => Classroom::findOrFail($b['kelas']->id));

        $this->assertSame($tokenBSebelum, $setelah->public_token, 'Token kelas B ikut terputar.');
        $this->assertSame('XI IPA 3', $setelah->nama_kelas, 'Nama kelas B ikut berubah.');
    }

    /** Ekspor CSV selalu berisi kelas aktif, tidak pernah bisa diarahkan ke kelas lain. */
    public function test_ekspor_tidak_bisa_diarahkan_ke_kelas_lain(): void
    {
        Storage::fake('local');

        $a = $this->kelasLengkap('XII TRPL 1', 'SMKN 1 Subang');
        $b = $this->kelasLengkap('XI IPA 3', 'SMAN 2 Bandung');

        foreach (['siswa', 'pembayaran', 'pengeluaran', 'campaign', 'tutup-buku'] as $jenis) {
            $csv = $this->actingAs($a['user'])
                ->get(route('ekspor.unduh', $jenis).'?classroom_id='.$b['kelas']->id)
                ->assertOk()
                ->streamedContent();

            $this->assertStringNotContainsString('XI IPA 3', $csv,
                "Ekspor {$jenis} bocor ke kelas lain.");
            $this->assertStringNotContainsString('SMAN 2 Bandung', $csv);
        }
    }

    /** Halaman kelas publik: token kelas B tidak pernah menampilkan isi kelas A. */
    public function test_token_publik_hanya_membuka_kelasnya_sendiri(): void
    {
        Storage::fake('local');

        $a = $this->kelasLengkap('XII TRPL 1', 'SMKN 1 Subang');
        $b = $this->kelasLengkap('XI IPA 3', 'SMAN 2 Bandung');

        $this->flushSession();

        $this->get(route('publik.kelas', $b['kelas']->public_token))
            ->assertOk()
            ->assertSee('Siswa XI IPA 3')
            ->assertDontSee('Siswa XII TRPL 1')
            ->assertDontSee('SMKN 1 Subang')
            ->assertDontSee($a['kelas']->public_token);

        $this->get(route('publik.qris', $a['kelas']->public_token))->assertNotFound();
    }
}
