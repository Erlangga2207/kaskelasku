<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Support\CurrentClassroom;

abstract class Controller
{
    /**
     * Kelas aktif request ini.
     *
     * Semua pencarian record dilakukan lewat relasi kelas ini — bukan
     * Model::find($request->id) — supaya ID milik kelas lain berakhir 404.
     */
    protected function kelas(): Classroom
    {
        return CurrentClassroom::getOrFail();
    }
}
