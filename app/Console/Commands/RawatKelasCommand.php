<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Support\CurrentClassroom;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Perawatan daur hidup kelas (PRD bagian 5). Dijadwalkan harian.
 *
 * Dua pekerjaan:
 *   1. Menghapus permanen kelas yang tenggangnya sudah lewat.
 *   2. Menandai nonaktif kelas yang lama tidak dipakai.
 *
 * Keduanya sengaja dijalankan perintah terjadwal, bukan dipicu saat ada yang
 * membuka halaman. Pekerjaan yang menghapus data tidak boleh bergantung pada
 * ada-tidaknya pengunjung: kelas yang ditinggalkan justru yang paling jarang
 * dibuka, dan itu tepat kelas yang perlu dirawat.
 */
class RawatKelasCommand extends Command
{
    protected $signature = 'kaskelas:rawat-kelas
                            {--dry-run : Laporkan saja, tidak ada yang diubah atau dihapus.}';

    protected $description = 'Menghapus permanen kelas yang tenggangnya lewat, dan menandai nonaktif kelas yang lama tidak dipakai.';

    public function handle(): int
    {
        $kering = (bool) $this->option('dry-run');

        if ($kering) {
            $this->components->warn('Mode dry-run: tidak ada yang diubah.');
        }

        $dihapus = $this->hapusYangTenggangnyaLewat($kering);
        $dinonaktifkan = $this->tandaiYangTerlantar($kering);

        $this->newLine();
        $this->components->info("Selesai. {$dihapus} kelas dihapus permanen, {$dinonaktifkan} kelas ditandai nonaktif.");

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | Hapus permanen
    |--------------------------------------------------------------------------
    */

    protected function hapusYangTenggangnyaLewat(bool $kering): int
    {
        $tenggang = (int) config('kaskelas.daur.tenggang_hapus_hari');
        $batas = CarbonImmutable::now()->subDays($tenggang);

        $daftar = CurrentClassroom::withoutTenancy(fn () => Classroom::query()
            ->where('status', 'dihapus')
            ->whereNotNull('dihapus_pada')
            ->where('dihapus_pada', '<=', $batas)
            ->orderBy('id')
            ->get());

        foreach ($daftar as $kelas) {
            $this->components->twoColumnDetail(
                "#{$kelas->id} {$kelas->nama_kelas}",
                'dihapus permanen (ditandai '.$kelas->dihapus_pada->toDateString().')'
            );

            if (! $kering) {
                $this->hapusPermanen($kelas);
            }
        }

        return $daftar->count();
    }

    /**
     * Menghapus seluruh jejak satu kelas.
     *
     * Urutannya penting: semua foreign key di skema ini RESTRICT, jadi baris
     * anak harus lenyap lebih dulu. Urutan di bawah mengikuti arah
     * ketergantungan dari daun ke akar. Dibungkus satu transaksi supaya kelas
     * tidak pernah tertinggal separuh terhapus.
     *
     * DELETE mentah, bukan Eloquent: sebagian tabel memakai soft delete, dan di
     * sini yang diminta memang menghilangkan barisnya sungguhan.
     */
    protected function hapusPermanen(Classroom $kelas): void
    {
        DB::transaction(function () use ($kelas) {
            $id = $kelas->id;

            DB::table('payment_allocations')->where('classroom_id', $id)->delete();
            DB::table('payments')->where('classroom_id', $id)->delete();
            DB::table('bills')->where('classroom_id', $id)->delete();
            DB::table('expenses')->where('classroom_id', $id)->delete();
            DB::table('campaigns')->where('classroom_id', $id)->delete();
            DB::table('periods')->where('classroom_id', $id)->delete();
            DB::table('students')->where('classroom_id', $id)->delete();
            DB::table('expense_categories')->where('classroom_id', $id)->delete();
            DB::table('book_closings')->where('classroom_id', $id)->delete();
            DB::table('audit_logs')->where('classroom_id', $id)->delete();
            DB::table('classroom_user')->where('classroom_id', $id)->delete();

            DB::table('classrooms')->where('id', $id)->delete();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Tandai terlantar
    |--------------------------------------------------------------------------
    */

    protected function tandaiYangTerlantar(bool $kering): int
    {
        $bulan = (int) config('kaskelas.daur.nonaktif_setelah_bulan');
        $batas = CarbonImmutable::now()->subMonths($bulan);

        $daftar = CurrentClassroom::withoutTenancy(fn () => Classroom::query()
            ->where('status', 'aktif')
            ->where('is_demo', false)
            ->where('created_at', '<=', $batas)
            ->orderBy('id')
            ->get());

        $jumlah = 0;

        foreach ($daftar as $kelas) {
            $terakhir = $this->aktivitasTerakhir($kelas);

            if ($terakhir !== null && $terakhir->greaterThan($batas)) {
                continue;
            }

            $this->components->twoColumnDetail(
                "#{$kelas->id} {$kelas->nama_kelas}",
                'ditandai nonaktif (aktivitas terakhir '.($terakhir?->toDateString() ?? 'tidak pernah').')'
            );

            $jumlah++;

            if ($kering) {
                continue;
            }

            $kelas->forceFill(['status' => 'nonaktif'])->save();

            AuditLog::catat(
                aksi: 'update',
                namaTabel: 'classrooms',
                recordId: $kelas->id,
                dataLama: ['status' => 'aktif'],
                dataBaru: ['status' => 'nonaktif', 'alasan' => "tanpa transaksi {$bulan} bulan"],
                classroomId: $kelas->id,
            );
        }

        return $jumlah;
    }

    /**
     * Transaksi terakhir sebuah kelas.
     *
     * Dihitung dari tabel transaksi, bukan disimpan sebagai kolom: kolom
     * semacam itu harus diperbarui di setiap jalur tulis, dan satu jalur yang
     * lupa memperbaruinya akan diam-diam menandai kelas yang masih dipakai
     * sebagai terlantar.
     */
    public static function aktivitasTerakhir(Classroom $kelas): ?CarbonImmutable
    {
        $waktu = collect([
            DB::table('payments')->where('classroom_id', $kelas->id)->max('created_at'),
            DB::table('expenses')->where('classroom_id', $kelas->id)->max('created_at'),
        ])->filter()->map(fn ($t) => CarbonImmutable::parse($t));

        return $waktu->max();
    }
}
