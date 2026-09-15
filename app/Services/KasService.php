<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Classroom;
use App\Models\Period;
use App\Models\Student;
use App\Support\Uang;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Semua logika uang kas kelas berkumpul di sini: pembuatan periode & tagihan,
 * alokasi pembayaran, denda, dan saldo.
 *
 * Saldo dan status lunas TIDAK PERNAH disimpan sebagai kolom — selalu dihitung
 * ulang dari payments, payment_allocations, dan expenses.
 */
class KasService
{
    /*
    |--------------------------------------------------------------------------
    | Periode
    |--------------------------------------------------------------------------
    */

    /**
     * Tahun ajaran Indonesia berjalan Juli sampai Juni.
     * Jadi kelas yang mulai Januari 2026 berakhir 30 Juni 2026, sedangkan yang
     * mulai September 2026 berakhir 30 Juni 2027.
     */
    public function akhirTahunAjaran(CarbonInterface|string $mulai): CarbonImmutable
    {
        $mulai = CarbonImmutable::parse($mulai);
        $tahun = $mulai->month >= 7 ? $mulai->year + 1 : $mulai->year;

        return CarbonImmutable::create($tahun, 6, 30)->startOfDay();
    }

    /**
     * Membuat periode berurutan sampai akhir tahun ajaran.
     * Periode yang tanggal mulainya sudah ada dilewati, sehingga aman dipanggil ulang.
     *
     * @return int jumlah periode baru yang dibuat
     */
    public function generatePeriode(Classroom $kelas, CarbonInterface|string $mulai, string|int $nominal, CarbonInterface|string|null $sampai = null): int
    {
        $mulai = CarbonImmutable::parse($mulai)->startOfDay();
        $sampai = CarbonImmutable::parse($sampai ?? $this->akhirTahunAjaran($mulai))->startOfDay();

        if ($sampai->lt($mulai)) {
            throw new RuntimeException('Tanggal akhir periode mendahului tanggal mulai.');
        }

        $nominal = Uang::keDesimal(Uang::keSen($nominal));

        $rentang = $kelas->tipe_periode === 'mingguan'
            ? $this->rentangMingguan($mulai, $sampai)
            : $this->rentangBulanan($mulai, $sampai);

        $sudahAda = Period::pluck('tgl_mulai')
            ->map(fn ($t) => CarbonImmutable::parse($t)->toDateString())
            ->all();

        $dibuat = 0;

        DB::transaction(function () use ($rentang, $sudahAda, $nominal, $kelas, &$dibuat) {
            foreach ($rentang as $bagian) {
                if (in_array($bagian['tgl_mulai']->toDateString(), $sudahAda, true)) {
                    continue;
                }

                Period::create([
                    'label' => $bagian['label'],
                    'tipe' => $kelas->tipe_periode,
                    'tgl_mulai' => $bagian['tgl_mulai']->toDateString(),
                    'tgl_selesai' => $bagian['tgl_selesai']->toDateString(),
                    // Jatuh tempo = hari terakhir periode. Denda baru berjalan
                    // setelah masa tenggang, jadi tidak perlu tanggal terpisah.
                    'jatuh_tempo' => $bagian['tgl_selesai']->toDateString(),
                    'nominal' => $nominal,
                    'is_libur' => false,
                ]);

                $dibuat++;
            }
        });

        return $dibuat;
    }

    /** @return array<int, array{label: string, tgl_mulai: CarbonImmutable, tgl_selesai: CarbonImmutable}> */
    protected function rentangBulanan(CarbonImmutable $mulai, CarbonImmutable $sampai): array
    {
        $hasil = [];
        $kursor = $mulai->startOfMonth();

        while ($kursor->lte($sampai)) {
            $akhir = $kursor->endOfMonth()->startOfDay();

            $hasil[] = [
                'label' => $kursor->translatedFormat('F Y'),
                // Periode pertama dimulai pada tanggal kelas dibuat, bukan tanggal 1,
                // supaya tidak menagih bulan yang sudah lewat sebagian.
                'tgl_mulai' => $kursor->lt($mulai) ? $mulai : $kursor,
                'tgl_selesai' => $akhir->gt($sampai) ? $sampai : $akhir,
            ];

            $kursor = $kursor->addMonth()->startOfMonth();
        }

        return $hasil;
    }

    /** @return array<int, array{label: string, tgl_mulai: CarbonImmutable, tgl_selesai: CarbonImmutable}> */
    protected function rentangMingguan(CarbonImmutable $mulai, CarbonImmutable $sampai): array
    {
        $hasil = [];
        $kursor = $mulai->startOfWeek(CarbonInterface::MONDAY);

        while ($kursor->lte($sampai)) {
            $akhir = $kursor->endOfWeek(CarbonInterface::SUNDAY)->startOfDay();
            $awalEfektif = $kursor->lt($mulai) ? $mulai : $kursor;
            $akhirEfektif = $akhir->gt($sampai) ? $sampai : $akhir;

            $hasil[] = [
                'label' => $awalEfektif->translatedFormat('j M').' – '.$akhirEfektif->translatedFormat('j M Y'),
                'tgl_mulai' => $awalEfektif,
                'tgl_selesai' => $akhirEfektif,
            ];

            $kursor = $kursor->addWeek();
        }

        return $hasil;
    }

    /*
    |--------------------------------------------------------------------------
    | Tagihan
    |--------------------------------------------------------------------------
    */

    /**
     * Membuat tagihan satu periode untuk semua siswa yang aktif pada rentangnya.
     * Periode libur tidak menghasilkan tagihan sama sekali.
     */
    public function generateTagihanPeriode(Period $period): int
    {
        if ($period->is_libur) {
            return 0;
        }

        $sudahPunya = Bill::where('period_id', $period->id)->pluck('student_id')->all();

        $dibuat = 0;

        DB::transaction(function () use ($period, $sudahPunya, &$dibuat) {
            Student::aktif()->get()->each(function (Student $siswa) use ($period, $sudahPunya, &$dibuat) {
                if (in_array($siswa->id, $sudahPunya) || ! $siswa->aktifPadaPeriode($period)) {
                    return;
                }

                Bill::create([
                    'student_id' => $siswa->id,
                    'period_id' => $period->id,
                    'nominal' => $period->nominal,
                ]);

                $dibuat++;
            });
        });

        return $dibuat;
    }

    /** Dipakai saat siswa baru masuk di tengah tahun. */
    public function generateTagihanSiswa(Student $siswa): int
    {
        if (! $siswa->is_active) {
            return 0;
        }

        $sudahPunya = Bill::where('student_id', $siswa->id)->pluck('period_id')->all();

        $dibuat = 0;

        DB::transaction(function () use ($siswa, $sudahPunya, &$dibuat) {
            Period::where('is_libur', false)->get()->each(function (Period $period) use ($siswa, $sudahPunya, &$dibuat) {
                if (in_array($period->id, $sudahPunya) || ! $siswa->aktifPadaPeriode($period)) {
                    return;
                }

                Bill::create([
                    'student_id' => $siswa->id,
                    'period_id' => $period->id,
                    'nominal' => $period->nominal,
                ]);

                $dibuat++;
            });
        });

        return $dibuat;
    }

    /**
     * Membuang tagihan yang seharusnya tidak ada lagi — misalnya setelah siswa
     * diberi tanggal berhenti, atau periode ditandai libur.
     *
     * Tagihan yang sudah menerima pembayaran TIDAK pernah dibuang: uangnya nyata,
     * dan menghapus tagihannya akan membuat saldo tidak bisa dipertanggungjawabkan.
     */
    public function bersihkanTagihanTakBerlaku(Student $siswa): int
    {
        $terhapus = 0;

        DB::transaction(function () use ($siswa, &$terhapus) {
            Bill::where('student_id', $siswa->id)
                ->whereNotNull('period_id')
                ->with('period')
                ->doesntHave('allocations')
                ->get()
                ->each(function (Bill $bill) use ($siswa, &$terhapus) {
                    if ($bill->period && $siswa->aktifPadaPeriode($bill->period) && ! $bill->period->is_libur) {
                        return;
                    }

                    $bill->delete();
                    $terhapus++;
                });
        });

        return $terhapus;
    }

    /** Menghapus seluruh tagihan sebuah periode. Gagal bila ada yang sudah dibayar. */
    public function hapusTagihanPeriode(Period $period): int
    {
        if ($this->periodePunyaPembayaran($period)) {
            throw new RuntimeException('Periode ini sudah menerima pembayaran, tagihannya tidak boleh dihapus.');
        }

        return Bill::where('period_id', $period->id)->get()->each->delete()->count();
    }

    public function periodePunyaPembayaran(Period $period): bool
    {
        return Bill::where('period_id', $period->id)->has('allocations')->exists();
    }

    /**
     * Menyelaraskan nominal tagihan setelah nominal periode diubah.
     * Hanya periode ini yang terpengaruh — periode lain tidak ikut berubah.
     */
    public function selaraskanNominalTagihan(Period $period): int
    {
        return Bill::where('period_id', $period->id)
            ->where('is_bebas', false)
            ->get()
            ->filter(fn (Bill $bill) => Uang::keSen($bill->nominal) !== Uang::keSen($period->nominal))
            ->each(fn (Bill $bill) => $bill->update(['nominal' => $period->nominal]))
            ->count();
    }
}
