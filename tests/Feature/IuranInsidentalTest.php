<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Campaign;
use App\Models\Classroom;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\User;
use App\Services\KasService;
use App\Support\CurrentClassroom;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Iuran insidental (Fase 7 / v1.1).
 *
 * SETIAP test di sini menempuh ALUR NYATA lewat HTTP: buat kelas → tambah siswa →
 * buat campaign → bayar. Tidak ada satu pun tagihan campaign yang dibuat langsung
 * lewat factory atau Bill::create().
 *
 * Alasannya pahit: bug Fase 2 (periode dibuat, nol tagihan tergenerate) lolos dari
 * seluruh test justru karena test-nya membuat bills sendiri, sehingga jalur
 * generate yang rusak tidak pernah dijalankan.
 */
class IuranInsidentalTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Pembuatan campaign & tagihannya
    |--------------------------------------------------------------------------
    */

    public function test_campaign_menerbitkan_satu_tagihan_untuk_tiap_peserta(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu', 'Bagas Pratama', 'Citra Lestari']);

        $this->assertCount(3, $siswa, 'Persiapan gagal: siswanya tidak terbentuk.');

        $this->actingAs($user)->post(route('campaign.store'), [
            'nama' => 'Studi Tour Bandung',
            'deskripsi' => 'transport + tiket masuk',
            'nominal_per_siswa' => 150000,
            'deadline' => now()->addMonth()->toDateString(),
            'peserta' => $siswa->pluck('id')->all(),
        ])->assertSessionHas('sukses')->assertSessionMissing('peringatan');

        $campaign = $this->dalamKelas($kelas, fn () => Campaign::firstOrFail());
        $tagihan = $this->dalamKelas($kelas, fn () => Bill::where('campaign_id', $campaign->id)->get());

        // Inti dari pelajaran Fase 2: jumlah tagihan == jumlah peserta, dihitung
        // dari database, bukan dari nilai kembalian fungsi yang sedang diuji.
        $this->assertCount(3, $tagihan, 'Jumlah tagihan campaign harus sama dengan jumlah peserta.');
        $this->assertEqualsCanonicalizing($siswa->pluck('id')->all(), $tagihan->pluck('student_id')->all());

        foreach ($tagihan as $bill) {
            $this->assertNull($bill->period_id, 'Tagihan campaign wajib ber-period_id NULL.');
            $this->assertSame($campaign->id, $bill->campaign_id);
            $this->assertSame('150000.00', (string) $bill->nominal);
        }
    }

    public function test_peserta_bisa_dikurangi_dari_bawaan_semua_siswa_aktif(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu', 'Bagas Pratama', 'Citra Lestari']);

        // Bawaan formulir: seluruh siswa aktif tercentang.
        $this->actingAs($user)->get(route('campaign.create'))
            ->assertOk()
            ->assertSee('Bagas Pratama');

        $ikut = $siswa->take(2);

        $this->actingAs($user)->post(route('campaign.store'), [
            'nama' => 'Bingkisan Wali Kelas',
            'nominal_per_siswa' => 20000,
            'peserta' => $ikut->pluck('id')->all(),
        ])->assertSessionHas('sukses');

        $campaign = $this->dalamKelas($kelas, fn () => Campaign::firstOrFail());
        $peserta = $this->dalamKelas($kelas, fn () => Bill::where('campaign_id', $campaign->id)->pluck('student_id')->all());

        $this->assertCount(2, $peserta);
        $this->assertNotContains($siswa->last()->id, $peserta);
    }

    /** Gagal harus berisik. Campaign tanpa peserta = nol tagihan, jadi ditolak di depan. */
    public function test_campaign_tanpa_peserta_ditolak_dengan_pesan_yang_jelas(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $this->tambahSiswa($user, $kelas, ['Adinda Ayu']);

        $this->actingAs($user)->post(route('campaign.store'), [
            'nama' => 'Campaign Kosong',
            'nominal_per_siswa' => 10000,
        ])->assertSessionHasErrors('peserta');

        $this->assertSame(0, $this->dalamKelas($kelas, fn () => Campaign::count()));

        $this->actingAs($user)->get(route('campaign.create'))
            ->assertOk();

        $this->followingRedirects()
            ->actingAs($user)
            ->post(route('campaign.store'), ['nama' => 'Campaign Kosong', 'nominal_per_siswa' => 10000])
            ->assertSee('tidak menghasilkan tagihan apa pun', false);
    }

    /** Kelas kosong: bendahara diarahkan menambah siswa, bukan dibiarkan membuat campaign hampa. */
    public function test_kelas_tanpa_siswa_aktif_diperingatkan_sebelum_membuat_campaign(): void
    {
        [, $user] = $this->buatKelas();

        $this->actingAs($user)->get(route('campaign.create'))
            ->assertRedirect(route('siswa.index'))
            ->assertSessionHas('peringatan');

        $this->actingAs($user)->get(route('siswa.index'))
            ->assertSee('belum punya siswa aktif', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Mesin alokasi: campaign menumpang jalur yang sama dengan iuran rutin
    |--------------------------------------------------------------------------
    */

    public function test_satu_pembayaran_menutup_campuran_tagihan_rutin_dan_campaign(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-02-28',
        ]);

        $campaign = $this->buatCampaign($user, $kelas, 'Perpisahan', 50000, [$siswa->id]);

        $januari = $this->tagihanPeriode($kelas, 'Januari 2026');
        $tagihanCampaign = $this->dalamKelas($kelas, fn () => Bill::where('campaign_id', $campaign->id)->firstOrFail());

        // Satu pembayaran, dua tagihan berbeda sumber, sekali kirim.
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 55000,
            'metode' => 'tunai',
            'mode_alokasi' => 'manual',
            'alokasi' => [$januari->id => 5000, $tagihanCampaign->id => 50000],
        ])->assertRedirect(route('pembayaran.index'));

        $kas = app(KasService::class);

        $this->dalamKelas($kelas, function () use ($kas, $januari, $tagihanCampaign, $siswa) {
            $this->assertSame('lunas', $kas->statusTagihan($januari->fresh()));
            $this->assertSame('lunas', $kas->statusTagihan($tagihanCampaign->fresh()));
            $this->assertSame(0, $kas->depositSiswa($siswa));
            $this->assertSame(2, PaymentAllocation::count(), 'Satu pembayaran seharusnya pecah jadi dua alokasi.');
        });
    }

    /** Alokasi otomatis: mesin lama menangani tagihan campaign tanpa cabang kode baru. */
    public function test_alokasi_otomatis_menutup_tagihan_rutin_lebih_dulu_lalu_campaign(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-02-28',
        ]);

        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id]);

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 30000,
            'metode' => 'tunai',
        ])->assertRedirect(route('pembayaran.index'));

        $kas = app(KasService::class);

        $this->dalamKelas($kelas, function () use ($kas, $kelas, $campaign) {
            $this->assertSame('lunas', $kas->statusTagihan($this->tagihanPeriode($kelas, 'Januari 2026')));
            $this->assertSame('lunas', $kas->statusTagihan($this->tagihanPeriode($kelas, 'Februari 2026')));

            $tagihanCampaign = Bill::where('campaign_id', $campaign->id)->firstOrFail();

            // 30.000 − 5.000 − 5.000 = 20.000 mendarat di tagihan campaign.
            $this->assertSame('kurang', $kas->statusTagihan($tagihanCampaign));
            $this->assertSame(2000000, $kas->dibayarTagihan($tagihanCampaign));
        });
    }

    /** Kelebihan bayar yang mengendap langsung menutup tagihan campaign begitu terbit. */
    public function test_deposit_lama_otomatis_terpakai_saat_campaign_dibuat(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 20000,
            'metode' => 'tunai',
        ]);

        $kas = app(KasService::class);
        $this->dalamKelas($kelas, fn () => $this->assertSame(2000000, $kas->depositSiswa($siswa)));

        $campaign = $this->buatCampaign($user, $kelas, 'Bingkisan Guru', 15000, [$siswa->id]);

        $this->dalamKelas($kelas, function () use ($kas, $campaign, $siswa) {
            $tagihan = Bill::where('campaign_id', $campaign->id)->firstOrFail();

            $this->assertSame('lunas', $kas->statusTagihan($tagihan));
            $this->assertSame(500000, $kas->depositSiswa($siswa), 'Sisa deposit harus tinggal Rp 5.000.');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Pembatalan campaign
    |--------------------------------------------------------------------------
    */

    public function test_campaign_dibatalkan_tidak_menghapus_pembayaran_dan_sisanya_jadi_deposit(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id]);

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 50000,
            'metode' => 'tunai',
        ]);

        $kas = app(KasService::class);
        $saldoSebelum = $this->dalamKelas($kelas, fn () => $kas->saldoKas());

        $this->actingAs($user)->patch(route('campaign.status', $campaign), ['status' => 'dibatalkan'])
            ->assertSessionHas('sukses');

        $this->dalamKelas($kelas, function () use ($kas, $campaign, $siswa, $saldoSebelum) {
            $this->assertSame('dibatalkan', $campaign->fresh()->status);

            // Pembayarannya utuh — yang hilang hanya tagihannya.
            $this->assertSame(1, Payment::count());
            $this->assertSame(0, Bill::where('campaign_id', $campaign->id)->count());
            $this->assertSame(0, PaymentAllocation::count());

            $this->assertSame(5000000, $kas->depositSiswa($siswa), 'Uangnya harus kembali menjadi deposit siswa.');
            $this->assertSame($saldoSebelum, $kas->saldoKas(), 'Saldo kas tidak boleh berubah karena pembatalan.');
            $this->assertSame(0, $kas->danaCampaignTertahan(), 'Campaign batal tidak lagi menahan dana.');
        });
    }

    /** Deposit hasil pembatalan langsung menutup tunggakan rutin, lewat mesin yang sama. */
    public function test_deposit_dari_campaign_batal_menutup_tagihan_rutin_yang_belum_lunas(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-02-28',
        ]);

        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id]);
        $tagihanCampaign = $this->dalamKelas($kelas, fn () => Bill::where('campaign_id', $campaign->id)->firstOrFail());

        // Uangnya sengaja dialokasikan penuh ke campaign, bukan ke iuran rutin.
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 50000,
            'metode' => 'tunai',
            'mode_alokasi' => 'manual',
            'alokasi' => [$tagihanCampaign->id => 50000],
        ]);

        $this->actingAs($user)->patch(route('campaign.status', $campaign), ['status' => 'dibatalkan']);

        $kas = app(KasService::class);

        $this->dalamKelas($kelas, function () use ($kas, $kelas, $siswa) {
            $this->assertSame('lunas', $kas->statusTagihan($this->tagihanPeriode($kelas, 'Januari 2026')));
            $this->assertSame('lunas', $kas->statusTagihan($this->tagihanPeriode($kelas, 'Februari 2026')));
            $this->assertSame(4000000, $kas->depositSiswa($siswa), 'Sisanya tetap mengendap sebagai deposit.');
        });
    }

    public function test_campaign_yang_sudah_punya_pengeluaran_tidak_bisa_dibatalkan(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id]);
        $this->bayar($user, $siswa, 50000);
        $this->catatPengeluaran($user, $kelas, 20000, 'sewa bus', $campaign);

        $this->actingAs($user)->patch(route('campaign.status', $campaign), ['status' => 'dibatalkan'])
            ->assertSessionHas('galat');

        $this->dalamKelas($kelas, function () use ($campaign) {
            $this->assertSame('aktif', $campaign->fresh()->status);
            $this->assertSame(1, Bill::where('campaign_id', $campaign->id)->count());
        });
    }

    public function test_peserta_yang_sudah_membayar_tidak_bisa_dikeluarkan(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu', 'Bagas Pratama']);

        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, $siswa->pluck('id')->all());
        $this->bayar($user, $siswa->first(), 50000);

        $this->actingAs($user)->patch(route('campaign.update', $campaign), [
            'nama' => 'Studi Tour',
            'nominal_per_siswa' => 50000,
            'peserta' => [$siswa->last()->id],
        ])->assertSessionHas('galat');

        $this->assertSame(2, $this->dalamKelas($kelas, fn () => Bill::where('campaign_id', $campaign->id)->count()));
    }

    public function test_nominal_tidak_bisa_diubah_setelah_ada_pembayaran(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id]);
        $this->bayar($user, $siswa, 10000);

        $this->actingAs($user)->patch(route('campaign.update', $campaign), [
            'nama' => 'Studi Tour',
            'nominal_per_siswa' => 75000,
            'peserta' => [$siswa->id],
        ])->assertSessionHas('galat');

        $this->assertSame('50000.00', (string) $campaign->fresh()->nominal_per_siswa);
    }

    /*
    |--------------------------------------------------------------------------
    | Saldo bebas vs dana campaign, dan laporan campaign
    |--------------------------------------------------------------------------
    */

    public function test_dashboard_memisahkan_saldo_bebas_dan_dana_campaign(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-01-31',
        ]);

        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id]);

        // Rp 55.000: 5.000 untuk iuran rutin, 50.000 untuk campaign.
        $this->bayar($user, $siswa, 55000);

        $kas = app(KasService::class);

        $ringkasan = $this->dalamKelas($kelas, fn () => $kas->ringkasan($kelas));

        $this->assertSame(5500000, $ringkasan['saldo']);
        $this->assertSame(5000000, $ringkasan['dana_campaign']);
        $this->assertSame(500000, $ringkasan['saldo_bebas']);
        $this->assertSame(
            $ringkasan['saldo'],
            $ringkasan['saldo_bebas'] + $ringkasan['dana_campaign'],
            'Saldo bebas + dana campaign harus selalu sama dengan saldo kas.'
        );

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Saldo bebas', false)
            ->assertSee('Dana campaign belum terpakai', false);
    }

    public function test_laporan_campaign_menghitung_terkumpul_terpakai_dan_sisa(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu', 'Bagas Pratama']);

        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, $siswa->pluck('id')->all());

        $this->bayar($user, $siswa->first(), 50000);
        $this->bayar($user, $siswa->last(), 20000);
        $this->catatPengeluaran($user, $kelas, 30000, 'DP sewa bus', $campaign);

        $kas = app(KasService::class);
        $laporan = $this->dalamKelas($kelas, fn () => $kas->ringkasanCampaign($campaign));

        $this->assertSame(10000000, $laporan['tertagih']);
        $this->assertSame(7000000, $laporan['terkumpul']);
        $this->assertSame(3000000, $laporan['terpakai']);
        $this->assertSame(4000000, $laporan['sisa']);
        $this->assertSame(1, $laporan['lunas']);
        $this->assertSame(2, $laporan['peserta']);

        $this->actingAs($user)->get(route('campaign.show', $campaign))
            ->assertOk()
            ->assertSee('Studi Tour')
            ->assertSee('DP sewa bus');
    }

    public function test_pengeluaran_campaign_dibatasi_sisa_dana_campaign(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id]);
        $this->bayar($user, $siswa, 30000);

        // Uang campaign yang terkumpul baru Rp 30.000.
        $this->kirimPengeluaran($user, $kelas, 40000, 'sewa bus', $campaign)
            ->assertSessionHasErrors('jumlah');

        $this->kirimPengeluaran($user, $kelas, 30000, 'sewa bus', $campaign)
            ->assertSessionHasNoErrors();

        $kas = app(KasService::class);
        $this->dalamKelas($kelas, fn () => $this->assertSame(0, $kas->sisaCampaign($campaign)));
    }

    public function test_pengeluaran_biasa_tidak_boleh_memakai_uang_campaign(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-01-31',
        ]);

        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id]);
        $this->bayar($user, $siswa, 55000);

        // Saldo kas Rp 55.000, tapi yang bebas hanya Rp 5.000.
        $this->kirimPengeluaran($user, $kelas, 20000, 'beli spidol')
            ->assertSessionHasErrors('jumlah');

        $this->kirimPengeluaran($user, $kelas, 5000, 'beli spidol')
            ->assertSessionHasNoErrors();
    }

    /** Mengubah pengeluaran campaign: nominal lama dikembalikan dulu ke pot campaign. */
    public function test_mengubah_pengeluaran_campaign_tidak_menghitung_nominal_lamanya_dua_kali(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id]);
        $this->bayar($user, $siswa, 50000);
        $this->catatPengeluaran($user, $kelas, 40000, 'DP sewa bus', $campaign);

        $pengeluaran = $this->dalamKelas($kelas, fn () => Expense::where('keterangan', 'DP sewa bus')->firstOrFail());
        $kategori = $this->dalamKelas($kelas, fn () => ExpenseCategory::orderBy('id')->firstOrFail());

        $this->actingAs($user)->get(route('pengeluaran.edit', $pengeluaran))->assertOk()->assertSee('Studi Tour');

        // Sisa dana tinggal Rp 10.000, tapi menaikkan pengeluaran ini jadi Rp 50.000
        // tetap sah — Rp 40.000 yang lama kembali dulu ke pot campaign.
        $this->actingAs($user)->put(route('pengeluaran.update', $pengeluaran), [
            'tanggal' => now()->toDateString(),
            'category_id' => $kategori->id,
            'campaign_id' => $campaign->id,
            'jumlah' => 50000,
            'keterangan' => 'Pelunasan sewa bus',
        ])->assertSessionHasNoErrors();

        $kas = app(KasService::class);
        $this->dalamKelas($kelas, fn () => $this->assertSame(0, $kas->sisaCampaign($campaign->fresh())));

        // Rp 60.000 jelas melebihi yang pernah terkumpul, dan itu wajib ditolak.
        $this->actingAs($user)->put(route('pengeluaran.update', $pengeluaran), [
            'tanggal' => now()->toDateString(),
            'category_id' => $kategori->id,
            'campaign_id' => $campaign->id,
            'jumlah' => 60000,
            'keterangan' => 'Pelunasan sewa bus',
        ])->assertSessionHasErrors('jumlah');
    }

    public function test_campaign_yang_belum_tersentuh_uang_boleh_dihapus(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $campaign = $this->buatCampaign($user, $kelas, 'Salah Buat', 50000, [$siswa->id]);

        $this->actingAs($user)->delete(route('campaign.destroy', $campaign))
            ->assertRedirect(route('campaign.index'))
            ->assertSessionHas('sukses');

        $this->dalamKelas($kelas, function () use ($campaign) {
            $this->assertSame(0, Campaign::count());
            $this->assertSame(0, Bill::where('campaign_id', $campaign->id)->count());
        });
    }

    /** Sudah ada uangnya → jejaknya wajib tinggal. Batalkan, bukan hapus. */
    public function test_campaign_yang_sudah_menerima_uang_tidak_bisa_dihapus(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id]);
        $this->bayar($user, $siswa, 10000);

        $this->actingAs($user)->delete(route('campaign.destroy', $campaign))
            ->assertSessionHas('galat');

        $this->assertSame(1, $this->dalamKelas($kelas, fn () => Campaign::count()));
    }

    /*
    |--------------------------------------------------------------------------
    | Tunggakan, halaman publik, dan integritas skema
    |--------------------------------------------------------------------------
    */

    public function test_tagihan_campaign_baru_jadi_tunggakan_setelah_deadline_lewat(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id], now()->addMonth());

        $kas = app(KasService::class);

        $this->dalamKelas($kelas, fn () => $this->assertSame(
            0,
            $kas->tunggakanSiswa($siswa, $kelas),
            'Campaign yang belum jatuh tempo tidak boleh dihitung sebagai tunggakan.'
        ));

        CurrentClassroom::withoutTenancy(fn () => $campaign->forceFill([
            'deadline' => now()->subDay()->toDateString(),
        ])->save());

        $this->dalamKelas($kelas, fn () => $this->assertSame(
            5000000,
            $kas->tunggakanSiswa($siswa->fresh(), $kelas),
            'Setelah deadline lewat, tagihan campaign wajib terhitung sebagai tunggakan.'
        ));
    }

    public function test_progres_campaign_tampil_di_halaman_kelas_publik(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu', 'Bagas Pratama']);

        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour Bandung', 50000, $siswa->pluck('id')->all());
        $this->bayar($user, $siswa->first(), 50000, 'lewat transfer BCA');

        $this->get(route('publik.kelas', $kelas->public_token))
            ->assertOk()
            ->assertSee('Studi Tour Bandung')
            ->assertSee('Iuran insidental')
            // Isi halaman kelas tetap seperti sebelumnya: tidak ada data pribadi
            // tambahan, tidak ada catatan pembayaran, dan tidak ada satu pun form.
            ->assertDontSee('lewat transfer BCA')
            ->assertDontSee('<form', false);
    }

    /** CHECK constraint v1 harus tetap hidup setelah foreign key campaign dipasang. */
    public function test_tagihan_tidak_boleh_punya_periode_dan_campaign_sekaligus(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-01-31',
        ]);

        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id]);
        $periode = $this->dalamKelas($kelas, fn () => $kelas->periods()->firstOrFail());

        $this->expectException(QueryException::class);
        // Ditegaskan namanya supaya test tidak lulus gara-gara galat lain.
        $this->expectExceptionMessageMatches('/chk_bills_sumber/');

        CurrentClassroom::withoutTenancy(fn () => Bill::insert([
            'classroom_id' => $kelas->id,
            'student_id' => $siswa->id,
            'period_id' => $periode->id,
            'campaign_id' => $campaign->id,
            'nominal' => '1000.00',
        ]));
    }

    public function test_tagihan_tanpa_periode_maupun_campaign_ditolak_database(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/chk_bills_sumber/');

        CurrentClassroom::withoutTenancy(fn () => Bill::insert([
            'classroom_id' => $kelas->id,
            'student_id' => $siswa->id,
            'period_id' => null,
            'campaign_id' => null,
            'nominal' => '1000.00',
        ]));
    }

    /*
    |--------------------------------------------------------------------------
    | Pembantu — semuanya menempuh HTTP, bukan factory
    |--------------------------------------------------------------------------
    */

    /** @return \Illuminate\Support\Collection<int, Student> */
    private function tambahSiswa(User $user, Classroom $kelas, array $nama): \Illuminate\Support\Collection
    {
        $this->actingAs($user)->post(route('siswa.massal.store'), [
            'daftar' => collect($nama)->map(fn ($n, $i) => ($i + 1).'. '.$n)->implode("\n"),
            'tgl_mulai_aktif' => '2026-01-01',
        ])->assertRedirect(route('siswa.index'));

        return $this->dalamKelas($kelas, fn () => Student::whereIn('nama', $nama)->urutAbsen()->get());
    }

    private function buatCampaign(User $user, Classroom $kelas, string $nama, int $nominal, array $peserta, $deadline = null): Campaign
    {
        $this->actingAs($user)->post(route('campaign.store'), [
            'nama' => $nama,
            'nominal_per_siswa' => $nominal,
            'deadline' => $deadline?->toDateString(),
            'peserta' => $peserta,
        ])->assertSessionHas('sukses');

        return $this->dalamKelas($kelas, fn () => Campaign::where('nama', $nama)->firstOrFail());
    }

    private function bayar(User $user, Student $siswa, int $jumlah, ?string $catatan = null): void
    {
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => $jumlah,
            'metode' => 'tunai',
            'catatan' => $catatan,
        ])->assertSessionHasNoErrors();
    }

    private function kirimPengeluaran(User $user, Classroom $kelas, int $jumlah, string $keterangan, ?Campaign $campaign = null)
    {
        $kategori = $this->dalamKelas($kelas, fn () => ExpenseCategory::orderBy('id')->firstOrFail());

        return $this->actingAs($user)->post(route('pengeluaran.store'), [
            'tanggal' => now()->toDateString(),
            'category_id' => $kategori->id,
            'campaign_id' => $campaign?->id,
            'jumlah' => $jumlah,
            'keterangan' => $keterangan,
        ]);
    }

    private function catatPengeluaran(User $user, Classroom $kelas, int $jumlah, string $keterangan, ?Campaign $campaign = null): void
    {
        $this->kirimPengeluaran($user, $kelas, $jumlah, $keterangan, $campaign)->assertSessionHasNoErrors();

        $this->assertSame(
            1,
            $this->dalamKelas($kelas, fn () => Expense::where('keterangan', $keterangan)->count()),
            "Pengeluaran {$keterangan} gagal tersimpan."
        );
    }

    private function tagihanPeriode(Classroom $kelas, string $label): Bill
    {
        return $this->dalamKelas($kelas, fn () => Bill::with(['period', 'allocations'])
            ->get()
            ->firstWhere(fn (Bill $b) => $b->period?->label === $label));
    }
}
