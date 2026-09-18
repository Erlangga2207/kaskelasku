<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Campaign;
use App\Models\Classroom;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Student;
use App\Models\User;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Isolasi tenant modul iuran insidental (PRD bagian 3.1 & 14.1).
 *
 * Aturan yang diuji: bendahara kelas A menyentuh ID milik kelas B harus berakhir
 * 404 atau ditolak validasi — tidak pernah menampilkan, mengubah, atau membiayai
 * data kelas lain.
 */
class IsolasiCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_bendahara_kelas_a_membuka_campaign_kelas_b_menghasilkan_404(): void
    {
        [$kelasA, $userA] = $this->buatKelas('XII TRPL 1', 'SMKN 1 Subang');
        [$kelasB, $userB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $campaignB = $this->buatCampaign($userB, $kelasB, 'Studi Tour Kelas B');
        $siswaA = $this->buatSiswa($kelasA, 'Adinda Kelas A');

        $this->actingAs($userA)->get(route('campaign.show', $campaignB))->assertNotFound();
        $this->actingAs($userA)->get(route('campaign.edit', $campaignB))->assertNotFound();

        $this->actingAs($userA)->patch(route('campaign.update', $campaignB), [
            'nama' => 'Dibajak',
            'nominal_per_siswa' => 1000,
            'peserta' => [$siswaA->id],
        ])->assertNotFound();

        $this->actingAs($userA)->patch(route('campaign.status', $campaignB), ['status' => 'dibatalkan'])
            ->assertNotFound();

        $this->actingAs($userA)->delete(route('campaign.destroy', $campaignB))->assertNotFound();

        // Tidak satu pun percobaan di atas boleh meninggalkan bekas di kelas B.
        $this->dalamKelas($kelasB, function () use ($campaignB) {
            $this->assertSame('Studi Tour Kelas B', $campaignB->fresh()->nama);
            $this->assertSame('aktif', $campaignB->fresh()->status);
            $this->assertSame(1, Bill::where('campaign_id', $campaignB->id)->count());
        });
    }

    public function test_daftar_campaign_kelas_a_tidak_memuat_campaign_kelas_b(): void
    {
        [$kelasA, $userA] = $this->buatKelas('XII TRPL 1');
        [$kelasB, $userB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $this->buatCampaign($userA, $kelasA, 'Perpisahan Kelas A');
        $this->buatCampaign($userB, $kelasB, 'Studi Tour Kelas B');

        // Pesan flash milik permintaan sebelumnya ikut menumpang session test ini.
        // Di dunia nyata tiap bendahara punya session sendiri, jadi dibersihkan
        // supaya yang diuji benar-benar isi halaman, bukan sisa notifikasi.
        $this->flushSession();

        $this->actingAs($userA)->get(route('campaign.index'))
            ->assertOk()
            ->assertSee('Perpisahan Kelas A')
            ->assertDontSee('Studi Tour Kelas B');
    }

    public function test_campaign_kelas_lain_tidak_terlihat_di_lapisan_model(): void
    {
        [$kelasA] = $this->buatKelas('XII TRPL 1');
        [$kelasB, $userB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $campaignB = $this->buatCampaign($userB, $kelasB, 'Studi Tour Kelas B');

        CurrentClassroom::set($kelasA);

        $this->assertNull(Campaign::find($campaignB->id));
        $this->assertSame(0, Campaign::count());
        $this->assertSame(0, Bill::whereNotNull('campaign_id')->count());
        $this->assertSame(1, CurrentClassroom::withoutTenancy(fn () => Bill::whereNotNull('campaign_id')->count()));
    }

    /** Peserta dari kelas lain ditolak validasi, jauh sebelum sempat jadi tagihan. */
    public function test_siswa_kelas_lain_tidak_bisa_dijadikan_peserta(): void
    {
        [$kelasA, $userA] = $this->buatKelas('XII TRPL 1');
        [$kelasB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $siswaB = $this->buatSiswa($kelasB, 'Fajar Kelas B');

        $this->actingAs($userA)->post(route('campaign.store'), [
            'nama' => 'Campaign Selundupan',
            'nominal_per_siswa' => 10000,
            'peserta' => [$siswaB->id],
        ])->assertSessionHasErrors('peserta.0');

        $this->assertSame(0, $this->dalamKelas($kelasA, fn () => Campaign::count()));
        $this->assertSame(0, $this->dalamKelas($kelasB, fn () => Bill::whereNotNull('campaign_id')->count()));
    }

    /** Pengeluaran kelas A tidak boleh dibebankan ke campaign kelas B. */
    public function test_pengeluaran_tidak_bisa_dibebankan_ke_campaign_kelas_lain(): void
    {
        [$kelasA, $userA] = $this->buatKelas('XII TRPL 1');
        [$kelasB, $userB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $campaignB = $this->buatCampaign($userB, $kelasB, 'Studi Tour Kelas B');
        $kategori = $this->dalamKelas($kelasA, fn () => ExpenseCategory::orderBy('id')->firstOrFail());

        $this->actingAs($userA)->post(route('pengeluaran.store'), [
            'tanggal' => now()->toDateString(),
            'category_id' => $kategori->id,
            'campaign_id' => $campaignB->id,
            'jumlah' => 1000,
            'keterangan' => 'menumpang dana kelas lain',
        ])->assertSessionHasErrors('campaign_id');

        $this->assertSame(0, $this->dalamKelas($kelasA, fn () => Expense::count()));
        $this->assertSame(0, $this->dalamKelas($kelasB, fn () => Expense::count()));
    }

    /** Uang kelas lain tidak boleh ikut menambah saldo campaign kelas ini. */
    public function test_angka_campaign_kelas_a_tidak_tercampur_kelas_b(): void
    {
        [$kelasA, $userA] = $this->buatKelas('XII TRPL 1');
        [$kelasB, $userB] = $this->buatKelas('XI IPA 3', 'SMAN 2 Bandung');

        $campaignA = $this->buatCampaign($userA, $kelasA, 'Perpisahan Kelas A');
        $this->buatCampaign($userB, $kelasB, 'Studi Tour Kelas B');

        $this->bayar($userB, $this->siswaPertama($kelasB), 50000);

        $kas = app(\App\Services\KasService::class);

        $this->dalamKelas($kelasA, function () use ($kas, $campaignA) {
            $this->assertSame(0, $kas->terkumpulCampaign($campaignA));
            $this->assertSame(0, $kas->danaCampaignTertahan());
            $this->assertSame(0, $kas->saldoKas());
        });

        $this->dalamKelas($kelasB, fn () => $this->assertSame(5000000, $kas->danaCampaignTertahan()));
    }

    /*
    |--------------------------------------------------------------------------
    | Pembantu
    |--------------------------------------------------------------------------
    */

    private function buatCampaign(User $user, Classroom $kelas, string $nama): Campaign
    {
        $siswa = $this->buatSiswa($kelas, 'Siswa '.$kelas->nama_kelas);

        $this->actingAs($user)->post(route('campaign.store'), [
            'nama' => $nama,
            'nominal_per_siswa' => 50000,
            'peserta' => [$siswa->id],
        ])->assertSessionHas('sukses');

        return $this->dalamKelas($kelas, fn () => Campaign::where('nama', $nama)->firstOrFail());
    }

    private function siswaPertama(Classroom $kelas): Student
    {
        return $this->dalamKelas($kelas, fn () => Student::urutAbsen()->firstOrFail());
    }

    private function bayar(User $user, Student $siswa, int $jumlah): void
    {
        $this->actingAs($user)->post(route('pembayaran.store'), [
            'student_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => $jumlah,
            'metode' => 'tunai',
        ])->assertSessionHasNoErrors();
    }
}
