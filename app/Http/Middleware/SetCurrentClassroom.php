<?php

namespace App\Http\Middleware;

use App\Models\Classroom;
use App\Support\CurrentClassroom;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menentukan kelas aktif untuk seluruh route bendahara.
 *
 * Sumbernya session, bukan URL. Kepemilikan tetap diverifikasi ulang tiap request:
 * session bisa saja menyimpan id kelas yang aksesnya sudah dicabut.
 */
class SetCurrentClassroom
{
    public const SESSION_KEY = 'classroom_id';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $classroom = $this->dariSession($user)
            ?? $this->kelasPertama($user);

        if ($classroom === null) {
            abort(403, 'Akun ini belum terhubung ke kelas mana pun.');
        }

        $request->session()->put(self::SESSION_KEY, $classroom->id);
        CurrentClassroom::set($classroom);

        return $next($request);
    }

    protected function dariSession($user): ?Classroom
    {
        $id = session(self::SESSION_KEY);

        return $id ? $this->kelasMilikUser($user, (int) $id) : null;
    }

    protected function kelasMilikUser($user, int $id): ?Classroom
    {
        return $user->classrooms()
            ->where('classrooms.id', $id)
            ->where('classrooms.status', 'aktif')
            ->first();
    }

    protected function kelasPertama($user): ?Classroom
    {
        return $user->classrooms()
            ->where('classrooms.status', 'aktif')
            ->orderBy('classrooms.nama_kelas')
            ->first();
    }
}
