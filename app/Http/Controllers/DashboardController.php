<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\CurrentClassroom;
use Illuminate\View\View;

/**
 * Fase 1 masih menampilkan ringkasan seadanya. Angka saldo dan tunggakan
 * baru diisi di Fase 4 setelah KasService ada — jangan menebak rumusnya di sini.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'kelas' => CurrentClassroom::getOrFail(),
            'jumlahSiswaAktif' => Student::aktif()->count(),
        ]);
    }
}
