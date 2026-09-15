<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Period;
use App\Models\Student;
use App\Services\KasService;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_periode_bulanan_dibuat_sampai_akhir_tahun_ajaran(): void
    {
        [$kelas, $user] = $this->buatKelas();

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-05',
            'nominal' => 5000,
        ])->assertRedirect(route('periode.index'));

        $periode = $this->dalamKelas($kelas, fn () => Period::urutWaktu()->get());

        // Januari sampai Juni 2026 = 6 periode.
        $this->assertCount(6, $periode);
        $this->assertSame('2026-01-05', $periode->first()->tgl_mulai->toDateString());
        $this->assertSame('2026-06-30', $periode->last()->tgl_selesai->toDateString());
        $this->assertSame('5000.00', $periode->first()->nominal);
    }

    public function test_tahun_ajaran_yang_mulai_juli_berakhir_juni_tahun_berikutnya(): void
    {
        $kas = app(KasService::class);

        $this->assertSame('2027-06-30', $kas->akhirTahunAjaran(now()->parse('2026-09-01'))->toDateString());
        $this->assertSame('2026-06-30', $kas->akhirTahunAjaran(now()->parse('2026-02-01'))->toDateString());
    }

    public function test_tagihan_dibuat_untuk_setiap_siswa_aktif(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $this->buatSiswa($kelas, 'Adinda', ['no_absen' => 1]);
        $this->buatSiswa($kelas, 'Bagas', ['no_absen' => 2]);

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-05',
            'nominal' => 5000,
            'sampai' => '2026-03-31',
        ]);

        // 3 periode x 2 siswa
        $this->assertSame(6, $this->dalamKelas($kelas, fn () => Bill::count()));
    }

    /** Syarat "Selesai bila" Fase 2. */
    public function test_siswa_berhenti_di_tengah_tahun_tidak_punya_tagihan_setelahnya(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Citra', [
            'tgl_mulai_aktif' => '2026-01-01',
            'tgl_berhenti' => '2026-03-15',
        ]);

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-05',
            'nominal' => 5000,
            'sampai' => '2026-06-30',
        ]);

        $labelTertagih = $this->dalamKelas($kelas, fn () => Bill::where('student_id', $siswa->id)
            ->with('period')
            ->get()
            ->map(fn (Bill $b) => $b->period->label)
            ->sort()
            ->values()
            ->all());

        $this->assertSame(['Januari 2026', 'Februari 2026', 'Maret 2026'], array_values(array_intersect(
            ['Januari 2026', 'Februari 2026', 'Maret 2026'], $labelTertagih
        )));
        $this->assertNotContains('April 2026', $labelTertagih);
        $this->assertNotContains('Mei 2026', $labelTertagih);
        $this->assertCount(3, $labelTertagih);
    }

    public function test_periode_libur_tidak_menghasilkan_tagihan(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-05',
            'nominal' => 5000,
            'sampai' => '2026-02-28',
        ]);

        $periode = $this->dalamKelas($kelas, fn () => Period::urutWaktu()->first());

        $this->actingAs($user)->patch(route('periode.libur', $periode), ['libur' => '1']);

        $this->assertSame(0, $this->dalamKelas($kelas, fn () => Bill::where('period_id', $periode->id)->count()));
        $this->assertTrue($this->dalamKelas($kelas, fn () => Period::find($periode->id)->is_libur));

        // Dibatalkan kembali → tagihannya dibuat lagi.
        $this->actingAs($user)->patch(route('periode.libur', $periode), ['libur' => '0']);

        $this->assertSame(1, $this->dalamKelas($kelas, fn () => Bill::where('period_id', $periode->id)->count()));
    }

    public function test_ubah_nominal_satu_periode_tidak_menyentuh_periode_lain(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-05',
            'nominal' => 5000,
            'sampai' => '2026-03-31',
        ]);

        $periode = $this->dalamKelas($kelas, fn () => Period::urutWaktu()->get());

        $this->actingAs($user)->patch(route('periode.update', $periode[1]), [
            'label' => $periode[1]->label,
            'nominal' => 7500,
        ]);

        $nominalTagihan = $this->dalamKelas($kelas, fn () => Bill::with('period')
            ->get()
            ->mapWithKeys(fn (Bill $b) => [$b->period->label => $b->nominal])
            ->all());

        $this->assertSame('5000.00', $nominalTagihan['Januari 2026']);
        $this->assertSame('7500.00', $nominalTagihan['Februari 2026']);
        $this->assertSame('5000.00', $nominalTagihan['Maret 2026']);
    }

    public function test_input_massal_membaca_nomor_absen_dan_melewati_nama_ganda(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $this->buatSiswa($kelas, 'Adinda Ayu', ['no_absen' => 1]);

        $this->actingAs($user)->post(route('siswa.massal.store'), [
            'daftar' => "1. Adinda Ayu\n2) Bagas Pratama\n3 - Citra Maharani\nDimas Nugroho\n\n   ",
            'tgl_mulai_aktif' => '2026-01-01',
        ])->assertRedirect(route('siswa.index'));

        $siswa = $this->dalamKelas($kelas, fn () => Student::urutAbsen()->pluck('no_absen', 'nama')->all());

        $this->assertCount(4, $siswa);
        $this->assertSame(2, $siswa['Bagas Pratama']);
        $this->assertSame(3, $siswa['Citra Maharani']);
        $this->assertSame(4, $siswa['Dimas Nugroho']);
    }

    public function test_siswa_bertransaksi_dinonaktifkan_bukan_dihapus(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Adinda');

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-05', 'nominal' => 5000, 'sampai' => '2026-01-31',
        ]);

        // Meniru adanya pembayaran: satu alokasi ke tagihan siswa tersebut.
        $this->actingAs($user);

        $this->dalamKelas($kelas, function () use ($siswa) {
            $bill = Bill::where('student_id', $siswa->id)->firstOrFail();

            $payment = Payment::create([
                'student_id' => $siswa->id,
                'tanggal' => '2026-01-10',
                'jumlah' => '5000.00',
                'metode' => 'tunai',
            ]);

            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'bill_id' => $bill->id,
                'jumlah' => '5000.00',
            ]);
        });

        $this->actingAs($user)->delete(route('siswa.destroy', $siswa))->assertRedirect(route('siswa.index'));

        $segar = $this->dalamKelas($kelas, fn () => Student::withTrashed()->find($siswa->id));

        $this->assertNotNull($segar, 'Siswa dengan transaksi tidak boleh terhapus.');
        $this->assertFalse($segar->is_active);
        $this->assertNull($segar->deleted_at);
    }

    public function test_siswa_tanpa_transaksi_boleh_dihapus(): void
    {
        [$kelas, $user] = $this->buatKelas();
        $siswa = $this->buatSiswa($kelas, 'Elvira');

        $this->actingAs($user)->delete(route('siswa.destroy', $siswa))->assertRedirect(route('siswa.index'));

        $this->assertNull($this->dalamKelas($kelas, fn () => Student::find($siswa->id)));
    }

    public function test_tipe_periode_terkunci_setelah_ada_periode(): void
    {
        [$kelas, $user] = $this->buatKelas();

        $this->actingAs($user)->post(route('periode.store'), [
            'tgl_mulai' => '2026-01-05', 'nominal' => 5000, 'sampai' => '2026-01-31',
        ]);

        $this->actingAs($user)->patch(route('pengaturan.update'), [
            'nama_kelas' => 'XII TRPL 1',
            'sekolah' => 'SMKN 1 Subang',
            'tipe_periode' => 'mingguan',
        ])->assertRedirect(route('pengaturan.edit'));

        $this->assertSame('bulanan', CurrentClassroom::withoutTenancy(fn () => $kelas->fresh()->tipe_periode));
    }
}
