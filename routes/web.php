<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
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
});
