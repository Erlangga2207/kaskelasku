<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Support\CurrentClassroom;
use Illuminate\Http\RedirectResponse;

/**
 * Pintu masuk kelas demo.
 *
 * Yang ditampilkan adalah halaman kelas publik milik kelas demo — bukan salinan
 * dashboard bendahara yang dibuat read-only. Alasannya keamanan, bukan
 * kemalasan: halaman kelas publik sudah berada di grup route yang HANYA GET dan
 * tidak punya satu pun jalur tulis. Membuat tiruan dashboard berarti membuat
 * permukaan baru yang harus dijaga supaya tidak bisa menulis, dan permukaan
 * seperti itu cepat atau lambat bocor.
 */
class DemoController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        abort_unless(config('kaskelas.demo.aktif'), 404);

        $demo = CurrentClassroom::withoutTenancy(
            fn () => Classroom::where('is_demo', true)->where('status', 'aktif')->first()
        );

        if ($demo === null) {
            return redirect()->route('beranda')->with(
                'peringatan',
                'Kelas demo sedang disiapkan ulang. Coba lagi sebentar lagi ya.'
            );
        }

        return redirect()->route('publik.kelas', $demo->public_token);
    }
}
