<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\Classroom;
use App\Models\Period;
use App\Models\Student;
use App\Services\KasService;
use App\Support\CurrentClassroom;
use App\Support\Uang;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menambal data kelas yang terlanjur kehilangan tagihan karena bug is_active
 * (siswa yang dibuat setelah periode ada tidak pernah kebagian tagihan).
 *
 * Perintah ini TIDAK punya aturan sendiri: pembuatan tagihan memakai
 * KasService::generateTagihanPeriode() dan alokasi uang memakai
 * KasService::alokasikanDeposit() — keduanya sudah idempoten, jadi menjalankan
 * perintah ini dua kali tidak menggandakan apa pun.
 */
class PerbaikiTagihanCommand extends Command
{
    protected $signature = 'kaskelas:perbaiki-tagihan
                            {--kelas=* : Batasi ke ID kelas tertentu (boleh diulang). Kosong = semua kelas aktif.}
                            {--dry-run : Hitung saja, tidak ada yang disimpan.}';

    protected $description = 'Membuat tagihan yang hilang untuk kelas yang sudah punya periode + siswa, lalu mengalokasikan deposit yang menganggur.';

    public function handle(KasService $kas): int
    {
        $kering = (bool) $this->option('dry-run');
        $idKelas = array_filter((array) $this->option('kelas'));

        $daftarKelas = CurrentClassroom::withoutTenancy(fn () => Classroom::query()
            ->when($idKelas !== [], fn ($q) => $q->whereIn('id', $idKelas))
            ->orderBy('id')
            ->get());

        if ($daftarKelas->isEmpty()) {
            $this->components->error('Tidak ada kelas yang cocok dengan pilihan itu.');

            return self::FAILURE;
        }

        if ($kering) {
            $this->components->warn('MODE COBA (--dry-run): semua perubahan dibatalkan di akhir, tidak ada yang tersimpan.');
        }

        $this->newLine();

        $totalTagihan = 0;
        $totalAlokasi = 0;

        foreach ($daftarKelas as $kelas) {
            [$tagihan, $alokasi] = $this->tangani($kas, $kelas, $kering);

            $totalTagihan += $tagihan;
            $totalAlokasi += $alokasi;
        }

        $this->newLine();
        $this->components->twoColumnDetail(
            '<options=bold>Total tagihan '.($kering ? 'yang akan dibuat' : 'dibuat').'</>',
            (string) $totalTagihan
        );
        $this->components->twoColumnDetail(
            '<options=bold>Total deposit '.($kering ? 'yang akan teralokasi' : 'teralokasi').'</>',
            Uang::format(Uang::keDesimal($totalAlokasi))
        );

        if ($kering) {
            $this->newLine();
            $this->components->info('Tidak ada yang disimpan. Jalankan ulang tanpa --dry-run untuk menerapkannya.');
        } elseif ($totalTagihan === 0 && $totalAlokasi === 0) {
            $this->newLine();
            $this->components->info('Tidak ada yang perlu diperbaiki — semua kelas sudah lengkap.');
        }

        return self::SUCCESS;
    }

    /**
     * Seluruh pekerjaan satu kelas dibungkus satu transaksi, supaya --dry-run
     * bisa memakai logika produksi yang asli lalu membatalkannya — bukan menebak
     * hasilnya lewat perhitungan tiruan yang gampang melenceng dari aslinya.
     *
     * @return array{0: int, 1: int} [jumlah tagihan, sen yang teralokasi]
     */
    private function tangani(KasService $kas, Classroom $kelas, bool $kering): array
    {
        $this->components->twoColumnDetail(
            "<fg=cyan;options=bold>#{$kelas->id} {$kelas->nama_kelas}</>",
            "<fg=gray>{$kelas->sekolah}</>"
        );

        DB::beginTransaction();

        try {
            [$tagihan, $alokasi, $catatan] = CurrentClassroom::runFor($kelas, function () use ($kas) {
                $periode = Period::where('is_libur', false)->urutWaktu()->get();
                $siswaAktif = Student::aktif()->count();

                if ($periode->isEmpty()) {
                    return [0, 0, 'dilewati — kelas ini belum punya periode'];
                }

                if ($siswaAktif === 0) {
                    return [0, 0, 'dilewati — kelas ini belum punya siswa aktif'];
                }

                $tagihan = $periode->sum(fn (Period $p) => $kas->generateTagihanPeriode($p));

                // Uang yang sudah masuk tapi menggantung sebagai deposit sekarang
                // punya tagihan untuk ditempati. Urutan "tertua dulu" datang dari
                // alokasikanDeposit(), tidak ditulis ulang di sini.
                //
                // Siswa nonaktif ikut disapu: mereka bisa saja berhenti di tengah
                // tahun dengan tagihan lama yang belum lunas dan uang yang menganggur.
                $alokasi = Student::all()->sum(fn (Student $s) => $kas->alokasikanDeposit($s));

                return [
                    $tagihan,
                    $alokasi,
                    "{$periode->count()} periode x {$siswaAktif} siswa aktif → ".Bill::count().' tagihan total',
                ];
            });

            if ($kering) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            $this->components->error("Kelas #{$kelas->id} gagal diperbaiki: ".$e->getMessage());

            throw $e;
        }

        $this->components->bulletList(array_filter([
            $catatan,
            $tagihan > 0 ? "{$tagihan} tagihan ".($kering ? 'akan dibuat' : 'dibuat') : null,
            $alokasi > 0
                ? Uang::format(Uang::keDesimal($alokasi)).' deposit '.($kering ? 'akan dialokasikan' : 'dialokasikan').' ke tagihan tertua'
                : null,
        ]));

        return [$tagihan, $alokasi];
    }
}
