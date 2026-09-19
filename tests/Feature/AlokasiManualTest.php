<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Campaign;
use App\Models\Classroom;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\User;
use App\Services\KasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kontrol alokasi manual di form pembayaran (PRD 5.2 & 9).
 *
 * Aturan alokasi otomatis tidak diuji di sini — itu bagian LogikaUangTest.
 * Yang diuji di sini adalah kemampuan bendahara MENGALAHKAN aturan itu:
 * memilih sendiri tagihan mana yang dibayar, mencampur iuran rutin dengan
 * iuran insidental dalam satu pembayaran, dan meninggalkan sisanya sebagai
 * deposit.
 *
 * Semua test menempuh HTTP seperti bendahara sungguhan, bukan memanggil
 * KasService langsung — supaya jalur controller, Form Request, dan form-nya
 * ikut teruji.
 */
class AlokasiManualTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Tampilan form
    |--------------------------------------------------------------------------
    */

    public function test_form_mengelompokkan_tagihan_jadi_iuran_kas_dan_iuran_insidental(): void
    {
        [$kelas, $user, $siswa] = $this->siapkan();
        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour Bandung', 50000, [$siswa->id]);

        $januari = $this->tagihanPeriode($kelas, 'Januari 2026');
        $tagihanCampaign = $this->tagihanCampaign($kelas, $campaign);

        $this->actingAs($user)->get(route('pembayaran.create', ['siswa' => $siswa->id]))
            ->assertOk()
            // Dua bagian terpisah, bukan satu daftar campur aduk.
            ->assertSee('Iuran kas')
            ->assertSee('Iuran insidental')
            ->assertSee('Januari 2026')
            ->assertSee('Studi Tour Bandung')
            // Tiap tagihan punya kolom alokasinya sendiri, rutin maupun insidental.
            ->assertSee('name="alokasi['.$januari->id.']"', false)
            ->assertSee('name="alokasi['.$tagihanCampaign->id.']"', false)
            // Otomatis tetap bawaannya; manual hanya pilihan.
            ->assertSee('Atur sendiri')
            ->assertSee('Belum dialokasikan');
    }

    /** Kolom alokasi tidak boleh bocor ke tagihan siswa lain di kelas yang sama. */
    public function test_form_hanya_menampilkan_tagihan_siswa_yang_dipilih(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu', 'Bagas Pratama']);

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-01-31',
        ])->assertSessionHasNoErrors();

        $milikBagas = $this->dalamKelas($kelas, fn () => Bill::where('student_id', $siswa->last()->id)->firstOrFail());

        $this->actingAs($user)->get(route('pembayaran.create', ['siswa' => $siswa->first()->id]))
            ->assertOk()
            ->assertDontSee('name="alokasi['.$milikBagas->id.']"', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Alokasi campuran: iuran rutin + iuran insidental dalam satu pembayaran
    |--------------------------------------------------------------------------
    */

    public function test_satu_pembayaran_dibagi_manual_ke_tagihan_kas_dan_tagihan_campaign(): void
    {
        [$kelas, $user, $siswa] = $this->siapkan();
        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id]);

        $januari = $this->tagihanPeriode($kelas, 'Januari 2026');
        $tagihanCampaign = $this->tagihanCampaign($kelas, $campaign);

        // Rp 30.000 dibagi sendiri: Rp 5.000 ke Januari, Rp 25.000 ke campaign.
        // Kalau mesin otomatis yang jalan, Februari ikut lunas — jadi status
        // Februari di bawah sekaligus membuktikan kontrol manual benar-benar dipakai.
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 30000,
            'metode' => 'tunai',
            'mode_alokasi' => 'manual',
            'alokasi' => [$januari->id => 5000, $tagihanCampaign->id => 25000],
        ])->assertSessionHasNoErrors()->assertRedirect(route('pembayaran.index'));

        $kas = app(KasService::class);

        $this->dalamKelas($kelas, function () use ($kas, $kelas, $campaign, $siswa, $januari, $tagihanCampaign) {
            $this->assertSame(2, PaymentAllocation::count(), 'Satu pembayaran harus pecah jadi dua alokasi.');

            $this->assertSame('lunas', $kas->statusTagihan($januari->fresh()));
            $this->assertSame(
                'belum',
                $kas->statusTagihan($this->tagihanPeriode($kelas, 'Februari 2026')),
                'Februari tidak diminta dibayar, jadi tidak boleh ikut tersentuh.'
            );

            $this->assertSame('kurang', $kas->statusTagihan($tagihanCampaign->fresh()));
            $this->assertSame(2500000, $kas->dibayarTagihan($tagihanCampaign->fresh()));

            // Saldo campaign naik tepat sebesar porsinya, bukan sebesar pembayarannya.
            $this->assertSame(2500000, $kas->terkumpulCampaign($campaign));
            $this->assertSame(2500000, $kas->sisaCampaign($campaign));

            $this->assertSame(0, $kas->depositSiswa($siswa), 'Seluruh uang terbagi, jadi tidak ada deposit.');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Validasi total alokasi
    |--------------------------------------------------------------------------
    */

    public function test_alokasi_manual_melebihi_jumlah_pembayaran_ditolak(): void
    {
        [$kelas, $user, $siswa] = $this->siapkan();
        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id]);

        $januari = $this->tagihanPeriode($kelas, 'Januari 2026');
        $tagihanCampaign = $this->tagihanCampaign($kelas, $campaign);

        // Uang yang diterima Rp 10.000, tapi dibagi jadi Rp 13.000.
        $respons = $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 10000,
            'metode' => 'tunai',
            'mode_alokasi' => 'manual',
            'alokasi' => [$januari->id => 5000, $tagihanCampaign->id => 8000],
        ])->assertSessionHasErrors('alokasi');

        $pesan = session('errors')->getBag('default')->first('alokasi');

        // Pesannya harus menyebut angkanya, bukan cuma "input tidak valid".
        $this->assertStringContainsString('Rp 13.000', $pesan);
        $this->assertStringContainsString('Rp 10.000', $pesan);
        $this->assertStringContainsString('melebihi jumlah pembayaran', $pesan);

        $respons->assertRedirect();

        // Ditolak sebelum menyentuh database: tidak ada pembayaran setengah jadi.
        $this->dalamKelas($kelas, function () use ($januari) {
            $this->assertSame(0, Payment::withTrashed()->count());
            $this->assertSame(0, PaymentAllocation::count());
            $this->assertSame('belum', app(KasService::class)->statusTagihan($januari->fresh()));
        });
    }

    /** Alokasi ke satu tagihan tidak boleh melebihi sisa tagihan itu sendiri. */
    public function test_alokasi_manual_melebihi_sisa_satu_tagihan_ditolak(): void
    {
        [$kelas, $user, $siswa] = $this->siapkan();
        $januari = $this->tagihanPeriode($kelas, 'Januari 2026');

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 20000,
            'metode' => 'tunai',
            'mode_alokasi' => 'manual',
            // Tagihan Januari hanya Rp 5.000.
            'alokasi' => [$januari->id => 20000],
        ])->assertSessionHas('galat');

        $this->dalamKelas($kelas, function () {
            $this->assertSame(0, PaymentAllocation::count());
            $this->assertSame(0, Payment::count(), 'Transaksi gagal harus batal seluruhnya.');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Sisa yang tidak dialokasikan → deposit
    |--------------------------------------------------------------------------
    */

    public function test_alokasi_manual_kurang_dari_pembayaran_menyisakan_deposit(): void
    {
        [$kelas, $user, $siswa] = $this->siapkan();
        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id]);
        $tagihanCampaign = $this->tagihanCampaign($kelas, $campaign);

        // Rp 30.000 masuk, hanya Rp 20.000 yang dibagikan.
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 30000,
            'metode' => 'tunai',
            'mode_alokasi' => 'manual',
            'alokasi' => [$tagihanCampaign->id => 20000],
        ])->assertSessionHasNoErrors();

        $kas = app(KasService::class);

        $this->dalamKelas($kelas, function () use ($kas, $kelas, $campaign, $siswa) {
            $this->assertSame(1, PaymentAllocation::count());
            $this->assertSame(2000000, $kas->terkumpulCampaign($campaign));

            // Rp 10.000 sisanya mengendap sebagai deposit — TIDAK dilempar balik
            // ke tagihan rutin oleh mesin otomatis.
            $this->assertSame(1000000, $kas->depositSiswa($siswa));
            $this->assertSame('belum', $kas->statusTagihan($this->tagihanPeriode($kelas, 'Januari 2026')));
        });
    }

    /** Mode manual tanpa satu pun centang: seluruh uang jadi deposit. */
    public function test_mode_manual_tanpa_alokasi_menjadikan_seluruh_uang_deposit(): void
    {
        [$kelas, $user, $siswa] = $this->siapkan();

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 30000,
            'metode' => 'tunai',
            'mode_alokasi' => 'manual',
        ])->assertSessionHasNoErrors();

        $kas = app(KasService::class);

        $this->dalamKelas($kelas, function () use ($kas, $kelas, $siswa) {
            $this->assertSame(0, PaymentAllocation::count());
            $this->assertSame(3000000, $kas->depositSiswa($siswa));
            $this->assertSame('belum', $kas->statusTagihan($this->tagihanPeriode($kelas, 'Januari 2026')));
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Penghapusan pembayaran beralokasi manual
    |--------------------------------------------------------------------------
    */

    public function test_menghapus_pembayaran_manual_melepas_semua_alokasinya(): void
    {
        [$kelas, $user, $siswa] = $this->siapkan();
        $campaign = $this->buatCampaign($user, $kelas, 'Studi Tour', 50000, [$siswa->id]);

        $januari = $this->tagihanPeriode($kelas, 'Januari 2026');
        $tagihanCampaign = $this->tagihanCampaign($kelas, $campaign);

        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 30000,
            'metode' => 'tunai',
            'mode_alokasi' => 'manual',
            'alokasi' => [$januari->id => 5000, $tagihanCampaign->id => 25000],
        ])->assertSessionHasNoErrors();

        $pembayaran = $this->dalamKelas($kelas, fn () => Payment::firstOrFail());

        $this->dalamKelas($kelas, fn () => $this->assertSame(2, PaymentAllocation::count()));

        $this->actingAs($user)->delete(route('pembayaran.destroy', $pembayaran))
            ->assertRedirect(route('pembayaran.index'))
            ->assertSessionHas('sukses');

        $kas = app(KasService::class);

        $this->dalamKelas($kelas, function () use ($kas, $kelas, $campaign, $siswa, $januari, $tagihanCampaign) {
            $this->assertSame(0, PaymentAllocation::count(), 'Kedua alokasi harus ikut terhapus.');

            // Status tagihan pulih karena selalu dihitung dari alokasi yang tersisa.
            $this->assertSame('belum', $kas->statusTagihan($januari->fresh()));
            $this->assertSame('belum', $kas->statusTagihan($tagihanCampaign->fresh()));
            $this->assertSame('belum', $kas->statusTagihan($this->tagihanPeriode($kelas, 'Februari 2026')));

            $this->assertSame(0, $kas->terkumpulCampaign($campaign));
            $this->assertSame(0, $kas->depositSiswa($siswa), 'Uangnya sudah tidak ada, jadi deposit pun nol.');
            $this->assertSame(0, $kas->saldoKas());

            // Jejaknya tetap ada: soft delete, bukan hilang dari database.
            $this->assertSame(0, Payment::count());
            $this->assertSame(1, Payment::withTrashed()->count());
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Isolasi tenant
    |--------------------------------------------------------------------------
    */

    public function test_alokasi_manual_ke_tagihan_kelas_lain_ditolak(): void
    {
        [$kelasA, $userA, $siswaA] = $this->siapkan();
        [$kelasB, $userB, $siswaB] = $this->siapkan('XI IPA 3', 'SMAN 2 Bandung');

        $tagihanB = $this->tagihanPeriode($kelasB, 'Januari 2026');

        // Bendahara A mencoba mengalokasikan uang ke tagihan milik kelas B.
        $this->actingAs($userA)->post(route('pembayaran.store'), [
            'student_id' => $siswaA->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 5000,
            'metode' => 'tunai',
            'mode_alokasi' => 'manual',
            'alokasi' => [$tagihanB->id => 5000],
        ])->assertSessionHas('galat');

        $this->dalamKelas($kelasB, function () use ($tagihanB) {
            $this->assertSame(0, PaymentAllocation::count());
            $this->assertSame('belum', app(KasService::class)->statusTagihan($tagihanB->fresh()));
        });

        $this->dalamKelas($kelasA, fn () => $this->assertSame(0, Payment::count()));
    }

    /*
    |--------------------------------------------------------------------------
    | Pembantu
    |--------------------------------------------------------------------------
    */

    /**
     * Kelas + satu siswa + periode Januari & Februari 2026 (Rp 5.000/bulan).
     *
     * @return array{0: Classroom, 1: User, 2: Student}
     */
    private function siapkan(string $namaKelas = 'XII TRPL 1', string $sekolah = 'SMKN 1 Subang'): array
    {
        [$kelas, $user] = $this->buatKelas($namaKelas, $sekolah);
        $siswa = $this->tambahSiswa($user, $kelas, ['Adinda Ayu'])->first();

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-01', 'nominal' => 5000, 'sampai' => '2026-02-28',
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

    private function buatCampaign(User $user, Classroom $kelas, string $nama, int $nominal, array $peserta): Campaign
    {
        $this->actingAs($user)->post(route('campaign.store'), [
            'nama' => $nama,
            'nominal_per_siswa' => $nominal,
            'peserta' => $peserta,
        ])->assertSessionHas('sukses');

        return $this->dalamKelas($kelas, fn () => Campaign::where('nama', $nama)->firstOrFail());
    }

    private function tagihanPeriode(Classroom $kelas, string $label): Bill
    {
        return $this->dalamKelas($kelas, fn () => Bill::with(['period', 'allocations'])
            ->get()
            ->firstWhere(fn (Bill $b) => $b->period?->label === $label));
    }

    private function tagihanCampaign(Classroom $kelas, Campaign $campaign): Bill
    {
        return $this->dalamKelas($kelas, fn () => Bill::with(['campaign', 'allocations'])
            ->where('campaign_id', $campaign->id)
            ->firstOrFail());
    }
}
