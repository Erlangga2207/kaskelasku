<?php

namespace App\Http\Controllers;

use App\Console\Commands\RawatKelasCommand;
use App\Models\Classroom;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Support\CurrentClassroom;
use App\Support\Kapasitas;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboard admin platform — AGREGAT SAJA (PRD bagian 5).
 *
 * Halaman ini sengaja tidak punya jalan menuju detail transaksi kelas mana pun.
 * Itu batasan yang dipilih, bukan fitur yang belum sempat dibuat: yang
 * dibutuhkan untuk mengelola kapasitas server hanyalah JUMLAH — berapa kelas,
 * berapa pengguna, berapa transaksi, kapan terakhir dipakai. Tidak satu pun
 * dari pertanyaan itu butuh melihat siapa membayar berapa.
 *
 * Kalau suatu hari ada permintaan "tolong lihat sebentar kelas X untuk
 * membantu", jawabannya adalah meminta bendaharanya mengekspor CSV sendiri —
 * bukan menambahkan tombol di sini.
 */
class AdminPlatformController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->isAdminPlatform(), 403);

        return CurrentClassroom::withoutTenancy(function () {
            $kelas = Classroom::query()
                ->select(['id', 'nama_kelas', 'sekolah', 'status', 'is_demo', 'created_at', 'dihapus_pada'])
                ->withCount('students')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Classroom $k) => [
                    'kelas' => $k,
                    // Hanya tanggal aktivitas terakhir. Bukan transaksinya.
                    'aktivitas_terakhir' => RawatKelasCommand::aktivitasTerakhir($k),
                ]);

            return view('admin.index', [
                'baris' => $kelas,
                'ringkasan' => [
                    'kelas' => Classroom::where('is_demo', false)->count(),
                    'kelas_aktif' => Classroom::where('is_demo', false)->where('status', 'aktif')->count(),
                    'kelas_nonaktif' => Classroom::where('status', 'nonaktif')->count(),
                    'kelas_dihapus' => Classroom::where('status', 'dihapus')->count(),
                    'pengguna' => User::count(),
                    'siswa' => DB::table('students')->whereNull('deleted_at')->count(),
                    // Jumlah baris, bukan nilai rupiahnya. Total uang se-platform
                    // bukan angka yang perlu diketahui siapa pun untuk mengelola server.
                    'transaksi' => DB::table('payments')->whereNull('deleted_at')->count()
                        + DB::table('expenses')->whereNull('deleted_at')->count(),
                    'antrean' => WaitingListEntry::count(),
                ],
                'kuota' => [
                    'terpakai' => Kapasitas::jumlahKelasSistem(),
                    'batas' => Kapasitas::batasKelasSistem(),
                    'sisa' => Kapasitas::sisaKuotaSistem(),
                ],
            ]);
        });
    }
}
