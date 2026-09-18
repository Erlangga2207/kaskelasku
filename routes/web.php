<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ClassroomQrisController;
use App\Http\Controllers\ClassroomSettingController;
use App\Http\Controllers\ClassroomTokenController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\PublicClassController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
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
    Route::resource('siswa', StudentController::class)->parameters(['siswa' => 'siswa']);

    // --- Periode iuran ---
    Route::get('/periode', [PeriodController::class, 'index'])->name('periode.index');
    Route::post('/periode', [PeriodController::class, 'store'])->name('periode.store');
    Route::patch('/periode/{periode}', [PeriodController::class, 'update'])->name('periode.update');
    Route::patch('/periode/{periode}/libur', [PeriodController::class, 'libur'])->name('periode.libur');

    // --- Iuran insidental (campaign) ---
    Route::get('/campaign', [CampaignController::class, 'index'])->name('campaign.index');
    Route::get('/campaign/baru', [CampaignController::class, 'create'])->name('campaign.create');
    Route::post('/campaign', [CampaignController::class, 'store'])->name('campaign.store');
    Route::get('/campaign/{campaign}', [CampaignController::class, 'show'])->name('campaign.show');
    Route::get('/campaign/{campaign}/ubah', [CampaignController::class, 'edit'])->name('campaign.edit');
    Route::patch('/campaign/{campaign}', [CampaignController::class, 'update'])->name('campaign.update');
    Route::patch('/campaign/{campaign}/status', [CampaignController::class, 'status'])->name('campaign.status');
    Route::delete('/campaign/{campaign}', [CampaignController::class, 'destroy'])->name('campaign.destroy');

    // --- Pembayaran ---
    Route::get('/pembayaran', [PaymentController::class, 'index'])->name('pembayaran.index');
    Route::get('/pembayaran/baru', [PaymentController::class, 'create'])->name('pembayaran.create');
    Route::post('/pembayaran', [PaymentController::class, 'store'])->name('pembayaran.store');
    Route::get('/pembayaran/{pembayaran}/bukti', [PaymentController::class, 'bukti'])->name('pembayaran.bukti');
    Route::delete('/pembayaran/{pembayaran}', [PaymentController::class, 'destroy'])->name('pembayaran.destroy');

    // --- Tagihan (pembebasan iuran) ---
    Route::patch('/tagihan/{tagihan}/bebas', [BillController::class, 'bebas'])->name('tagihan.bebas');

    // --- Pengeluaran ---
    Route::get('/pengeluaran/{pengeluaran}/bukti', [ExpenseController::class, 'bukti'])->name('pengeluaran.bukti');
    Route::resource('pengeluaran', ExpenseController::class)
        ->except('show')
        ->parameters(['pengeluaran' => 'pengeluaran']);
    Route::post('/kategori-pengeluaran', [ExpenseCategoryController::class, 'store'])->name('kategori.store');
    Route::delete('/kategori-pengeluaran/{kategori}', [ExpenseCategoryController::class, 'destroy'])->name('kategori.destroy');

    // --- Pelaporan ---
    Route::get('/laporan', [ReportController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/pdf', [ReportController::class, 'pdf'])->name('laporan.pdf');
    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');

    // --- Pengingat tunggakan (teks siap salin, tanpa pengiriman otomatis) ---
    Route::get('/pengingat', [ReminderController::class, 'index'])->name('pengingat.index');

    // --- Pengaturan kelas ---
    Route::get('/pengaturan', [ClassroomSettingController::class, 'edit'])->name('pengaturan.edit');
    Route::patch('/pengaturan', [ClassroomSettingController::class, 'update'])->name('pengaturan.update');
    Route::patch('/pengaturan/token', [ClassroomTokenController::class, 'rotate'])->name('pengaturan.token');
    Route::patch('/pengaturan/pengingat', [ClassroomSettingController::class, 'pengingat'])->name('pengaturan.pengingat');
    Route::post('/pengaturan/qris', [ClassroomQrisController::class, 'store'])->name('pengaturan.qris');
    Route::delete('/pengaturan/qris', [ClassroomQrisController::class, 'destroy'])->name('pengaturan.qris.hapus');
});

/*
|--------------------------------------------------------------------------
| Halaman kelas (publik, bertoken)
|--------------------------------------------------------------------------
| Grup terpisah, HANYA GET, tanpa middleware auth. Tidak boleh ada satu pun
| route tulis yang bisa dicapai lewat token publik — kalau suatu saat butuh
| aksi tulis dari sisi anggota kelas, berarti desainnya perlu ditinjau ulang,
| bukan grup ini yang ditambahi.
*/
Route::middleware('kelas.token')->group(function () {
    Route::get('/kelas/{token}', [PublicClassController::class, 'show'])->name('publik.kelas');
    Route::get('/kelas/{token}/qris', [PublicClassController::class, 'qris'])->name('publik.qris');
    Route::get('/kelas/{token}/manifest.webmanifest', [PublicClassController::class, 'manifest'])
        ->name('publik.manifest');
});

Route::view('/offline', 'offline')->name('offline');
