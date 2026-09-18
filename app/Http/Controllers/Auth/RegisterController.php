<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Kapasitas;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Pendaftaran bendahara (PRD bagian 5).
 *
 * Akun bisa dibuat, tapi kelas belum bisa — verifikasi email dulu. Urutannya
 * begitu supaya alamat yang dipakai memulihkan akses benar-benar milik orang
 * yang mendaftar: kalau email salah ketik dan baru ketahuan setelah setahun
 * mencatat uang kas, tidak ada jalan pulang.
 */
class RegisterController extends Controller
{
    public function create(): View|RedirectResponse
    {
        // Kuota se-sistem penuh → yang muncul bukan form, tapi daftar tunggu.
        // Menampilkan form lalu menolak di akhir hanya membuang waktu orang.
        if (Kapasitas::kuotaSistemPenuh()) {
            return redirect()->route('daftar-tunggu');
        }

        return view('auth.register', [
            'sisaKuota' => Kapasitas::sisaKuotaSistem(),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        if (Kapasitas::kuotaSistemPenuh()) {
            return redirect()->route('daftar-tunggu');
        }

        $user = User::create($request->safe()->only('nama', 'email', 'password'));

        // Event bawaan Laravel yang memicu pengiriman email verifikasi.
        event(new Registered($user));

        AuditLog::catat('register', 'users', $user->id);

        Auth::login($user);

        return redirect()->route('verifikasi.notice');
    }
}
