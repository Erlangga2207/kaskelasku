<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetCurrentClassroom;
use App\Models\AuditLog;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Menghapus dan membatalkan penghapusan kelas (PRD bagian 5).
 *
 * Penghapusan tidak pernah langsung. Kelas ditandai 'dihapus' beserta waktunya,
 * lalu benar-benar lenyap setelah tenggang (bawaan 30 hari) lewat perintah
 * terjadwal. Alasannya: yang hilang di sini bukan satu baris, tapi seluruh
 * catatan uang satu kelas selama setahun — dan tidak ada tombol undo untuk itu
 * kalau ternyata yang menekan sedang panik atau salah pilih kelas.
 */
class ClassroomLifecycleController extends Controller
{
    public function destroy(Request $request): RedirectResponse
    {
        $kelas = $this->kelas();

        // Hanya pemilik kelas. Bendahara kedua yang diundang tidak boleh
        // menghapus kelas milik orang lain.
        abort_unless($kelas->owner_id === $request->user()->id, 403,
            'Hanya pemilik kelas yang bisa menghapus kelas ini.');

        abort_if($kelas->isDemo(), 403, 'Kelas demo tidak bisa dihapus.');

        $request->validate([
            // Mengetik nama kelasnya memaksa berhenti sejenak dan membaca ulang
            // kelas MANA yang sedang dihapus — akun bisa memegang lima kelas.
            'konfirmasi_nama' => ['required', 'string'],
        ], [
            'konfirmasi_nama.required' => 'Ketik nama kelasnya untuk mengonfirmasi.',
        ]);

        if (trim($request->input('konfirmasi_nama')) !== $kelas->nama_kelas) {
            return back()->with('galat', 'Nama kelas yang diketik tidak cocok. Kelas tidak jadi dihapus.');
        }

        $tenggang = (int) config('kaskelas.daur.tenggang_hapus_hari');

        $kelas->forceFill([
            'status' => 'dihapus',
            'dihapus_pada' => now(),
        ])->save();

        AuditLog::catat(
            aksi: 'delete',
            namaTabel: 'classrooms',
            recordId: $kelas->id,
            dataLama: ['status' => 'aktif'],
            dataBaru: ['status' => 'dihapus', 'dihapus_pada' => now()->toDateTimeString()],
            classroomId: $kelas->id,
        );

        $request->session()->forget(SetCurrentClassroom::SESSION_KEY);

        return redirect()->route('kelas.terhapus')->with(
            'sukses',
            "Kelas {$kelas->nama_kelas} dijadwalkan dihapus. Datanya masih bisa dipulihkan selama "
                ."{$tenggang} hari, setelah itu hilang permanen."
        );
    }

    /** Daftar kelas milik user yang sedang dalam tenggang hapus. */
    public function terhapus(Request $request)
    {
        return view('kelas.terhapus', [
            'daftar' => $request->user()->classrooms()
                ->where('classrooms.status', 'dihapus')
                ->get(),
            'tenggang' => (int) config('kaskelas.daur.tenggang_hapus_hari'),
        ]);
    }

    public function restore(Request $request, string $kelas): RedirectResponse
    {
        // Lewat relasi user, bukan Classroom::find(): id kelas orang lain 404.
        $record = $request->user()->classrooms()
            ->where('classrooms.id', $kelas)
            ->where('classrooms.status', 'dihapus')
            ->firstOrFail();

        abort_unless($record->owner_id === $request->user()->id, 403);

        $record->forceFill(['status' => 'aktif', 'dihapus_pada' => null])->save();

        AuditLog::catat(
            aksi: 'restore',
            namaTabel: 'classrooms',
            recordId: $record->id,
            dataLama: ['status' => 'dihapus'],
            dataBaru: ['status' => 'aktif'],
            classroomId: $record->id,
        );

        $request->session()->put(SetCurrentClassroom::SESSION_KEY, $record->id);

        return redirect()->route('dashboard')->with(
            'sukses',
            "Kelas {$record->nama_kelas} dipulihkan. Semua datanya utuh."
        );
    }
}
