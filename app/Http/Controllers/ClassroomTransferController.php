<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransferOwnerRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Serah terima kepemilikan kelas (PRD bagian 10).
 *
 * Perpindahannya lewat tabel classroom_user + kolom owner_id, BUKAN dengan
 * menyerahkan kata sandi akun lama. Satu akun tetap satu orang: begitu akun
 * dipakai bergantian, seluruh audit log kehilangan artinya karena tidak ada
 * lagi cara membedakan siapa yang mencatat apa.
 *
 * Bendahara lama dicabut aksesnya di transaksi yang sama. Membiarkannya tetap
 * bisa masuk "sebentar dulu" adalah cara paling umum sebuah serah terima tidak
 * pernah benar-benar selesai.
 */
class ClassroomTransferController extends Controller
{
    public function store(TransferOwnerRequest $request): RedirectResponse
    {
        $kelas = $this->kelas();
        $lama = $request->user();
        $data = $request->validated();

        $baru = DB::transaction(function () use ($request, $kelas, $lama, $data) {
            $baru = $request->calon();

            if ($baru === null) {
                $baru = User::create([
                    'nama' => $data['nama'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                ]);

                // Akun serah terima langsung dianggap terverifikasi. Pilihan ini
                // sadar, dan biayanya diakui: alamat emailnya memang belum
                // dibuktikan. Alternatifnya lebih buruk -- bendahara lama sudah
                // dicabut aksesnya di transaksi yang sama, jadi kalau bendahara
                // baru tertahan di halaman verifikasi dan emailnya ternyata
                // salah ketik, kelas itu tidak punya satu pun orang yang bisa
                // membukanya lagi. Yang menanggung alamat ini adalah bendahara
                // lama, yang mengetiknya sambil berhadapan dengan penggantinya.
                $baru->markEmailAsVerified();
            }

            // syncWithoutDetaching: kalau bendahara baru sudah terhubung ke kelas
            // ini sebelumnya, barisnya tidak digandakan.
            $kelas->users()->syncWithoutDetaching([
                $baru->id => ['peran' => 'bendahara', 'created_at' => now()],
            ]);

            $kelas->forceFill(['owner_id' => $baru->id])->save();

            $kelas->users()->detach($lama->id);

            AuditLog::catat(
                aksi: 'transfer_owner',
                namaTabel: 'classrooms',
                recordId: $kelas->id,
                dataLama: ['owner_id' => $lama->id, 'owner_email' => $lama->email],
                dataBaru: ['owner_id' => $baru->id, 'owner_email' => $baru->email],
                classroomId: $kelas->id,
            );

            return $baru;
        });

        // Session menyimpan kelas aktif; tanpa dibersihkan, bendahara lama akan
        // mendarat di kelas yang aksesnya baru saja dicabut.
        $request->session()->forget('classroom_id');

        $pesan = "Kelas {$kelas->nama_kelas} sudah dialihkan ke {$baru->nama} ({$baru->email}). "
            .'Aksesmu ke kelas ini dicabut, dan perpindahannya tercatat di audit log. '
            .'Jangan berbagi akun — minta bendahara baru masuk dengan akunnya sendiri.';

        // Bendahara lama bisa saja masih memegang kelas lain. Yang dicabut hanya
        // akses ke kelas ini, bukan akunnya — jadi dia hanya dikeluarkan kalau
        // memang tidak punya kelas lain untuk dituju.
        if ($lama->classrooms()->where('classrooms.status', 'aktif')->exists()) {
            return redirect()->route('dashboard')->with('sukses', $pesan);
        }

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('sukses', $pesan);
    }
}
