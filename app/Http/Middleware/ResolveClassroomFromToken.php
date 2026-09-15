<?php

namespace App\Http\Middleware;

use App\Models\Classroom;
use App\Support\CurrentClassroom;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kelas aktif untuk halaman publik, ditentukan oleh public_token pada URL.
 *
 * Token tidak dikenal → 404 polos. Jangan pernah membedakan "token salah" dari
 * "kelas tidak aktif": bedanya saja sudah membocorkan informasi.
 */
class ResolveClassroomFromToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->route('token');

        $classroom = CurrentClassroom::withoutTenancy(
            fn () => Classroom::where('public_token', $token)->where('status', 'aktif')->first()
        );

        abort_if($classroom === null, 404);

        CurrentClassroom::set($classroom);

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
