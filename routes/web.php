<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClassroomSettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\StudentBulkController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route bendahara (butuh login + kelas aktif)
|--------------------------------------------------------------------------
| Landing page publik baru dibuat di v2.0, jadi akar situs untuk sementara
| mengarahkan ke dashboard (atau ke halaman masuk bila belum login).
*/

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/keluar', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'kelas'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // --- Data master siswa ---
    Route::get('/siswa/massal', [StudentBulkController::class, 'create'])->name('siswa.massal');
    Route::post('/siswa/massal', [StudentBulkController::class, 'store'])->name('siswa.massal.store');
    Route::patch('/siswa/{siswa}/aktifkan', [StudentController::class, 'restore'])->name('siswa.restore');
    Route::resource('siswa', StudentController::class)->except('show')->parameters(['siswa' => 'siswa']);

    // --- Periode iuran ---
    Route::get('/periode', [PeriodController::class, 'index'])->name('periode.index');
    Route::post('/periode', [PeriodController::class, 'store'])->name('periode.store');
    Route::patch('/periode/{periode}', [PeriodController::class, 'update'])->name('periode.update');
    Route::patch('/periode/{periode}/libur', [PeriodController::class, 'libur'])->name('periode.libur');

    // --- Pengaturan kelas ---
    Route::get('/pengaturan', [ClassroomSettingController::class, 'edit'])->name('pengaturan.edit');
    Route::patch('/pengaturan', [ClassroomSettingController::class, 'update'])->name('pengaturan.update');
});
