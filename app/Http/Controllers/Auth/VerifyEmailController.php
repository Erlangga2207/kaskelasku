<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Verifikasi email bendahara.
 *
 * Tautannya bertanda tangan (signed URL) dan kedaluwarsa, jadi tidak bisa
 * ditebak maupun dipakai ulang bertahun-tahun kemudian.
 */
class VerifyEmailController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('wizard.kelas');
        }

        return view('auth.verifikasi');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('wizard.kelas');
        }

        $request->fulfill();

        event(new Verified($request->user()));

        return redirect()->route('wizard.kelas')->with(
            'sukses',
            'Email berhasil diverifikasi. Sekarang kelasnya bisa dibuat.'
        );
    }

    /** Kirim ulang. Rate limit-nya dipasang di route, bukan di sini. */
    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('wizard.kelas');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('sukses', 'Email verifikasi dikirim ulang. Cek juga folder spam ya.');
    }
}
