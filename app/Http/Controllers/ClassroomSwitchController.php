<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetCurrentClassroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pemilih kelas aktif untuk akun yang memegang lebih dari satu kelas.
 *
 * INI SATU-SATUNYA tempat di seluruh aplikasi yang menerima id kelas dari
 * request, dan itu memang tidak terhindarkan: berpindah kelas berarti menyebut
 * kelas mana yang dituju. Yang membuatnya tetap aman ada tiga:
 *
 *   1. Id-nya dicari lewat relasi $user->classrooms(), bukan Classroom::find().
 *      Id milik kelas yang bukan miliknya tidak ketemu, jadi berakhir 404.
 *   2. Hasilnya disimpan ke SESSION, tidak pernah ke URL. Halaman lain tetap
 *      membaca kelas aktif dari session seperti biasa.
 *   3. Middleware SetCurrentClassroom tetap memverifikasi ulang kepemilikan
 *      pada setiap request berikutnya — akses yang dicabut setelah perpindahan
 *      tidak akan hidup lagi hanya karena id-nya telanjur ada di session.
 */
class ClassroomSwitchController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'integer'],
        ]);

        $kelas = $request->user()->classrooms()
            ->where('classrooms.id', $data['classroom_id'])
            ->where('classrooms.status', 'aktif')
            ->firstOrFail();

        $request->session()->put(SetCurrentClassroom::SESSION_KEY, $kelas->id);

        return redirect()->route('dashboard')->with(
            'sukses',
            "Sekarang kamu melihat kelas {$kelas->nama_kelas}."
        );
    }
}
