<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;

/**
 * Rotasi token halaman kelas.
 *
 * Dipakai kalau tautan telanjur tersebar keluar kelas. Token lama langsung mati,
 * jadi tautan yang sudah beredar tidak bisa dipakai lagi.
 */
class ClassroomTokenController extends Controller
{
    public function rotate(): RedirectResponse
    {
        $kelas = $this->kelas();
        $tokenLama = $kelas->public_token;

        $kelas->rotateToken();

        AuditLog::catat(
            aksi: 'rotate_token',
            namaTabel: 'classrooms',
            recordId: $kelas->id,
            // Token lama sengaja tidak ikut disimpan utuh — cukup penanda bahwa
            // rotasi terjadi. Menyimpannya sama saja membiarkan kunci lama beredar.
            dataLama: ['public_token' => substr($tokenLama, 0, 6).'…'],
            dataBaru: null,
            classroomId: $kelas->id,
        );

        return redirect()->route('pengaturan.edit')->with(
            'sukses',
            'Tautan kelas diganti. Tautan lama tidak berlaku lagi — bagikan yang baru ke grup kelas.'
        );
    }
}
