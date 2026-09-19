<?php

use App\Http\Controllers\AdminPlatformController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\BookClosingController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ClassroomExportController;
use App\Http\Controllers\ClassroomLifecycleController;
use App\Http\Controllers\ClassroomQrisController;
use App\Http\Controllers\ClassroomSwitchController;
use App\Http\Controllers\ClassroomSettingController;
use App\Http\Controllers\ClassroomTransferController;
use App\Http\Controllers\ClassroomTokenController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\PublicClassController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StudentBulkController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\WaitingListController;
use App\Http\Controllers\WizardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman publik (boleh diindeks mesin pencari)
|--------------------------------------------------------------------------
| HANYA halaman di grup ini yang boleh masuk hasil pencarian. Dashboard dan
| halaman kelas bertoken punya noindex di layout-nya masing-masing, dan juga
| ditolak lewat robots.txt -- dua lapis, karena halaman kelas berisi nama siswa.
*/

Route::get('/', [PublicPageController::class, 'beranda'])->name('beranda');
Route::get('/panduan', [PublicPageController::class, 'panduan'])->name('panduan');
Route::get('/privasi', [PublicPageController::class, 'privasi'])->name('privasi');
Route::get('/syarat', [PublicPageController::class, 'syarat'])->name('syarat');
Route::get('/sitemap.xml', [PublicPageController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [PublicPageController::class, 'robots'])->name('robots');
Route::get('/demo', DemoController::class)->name('demo');

// Daftar tunggu: menggantikan pendaftaran saat kuota se-sistem penuh.
Route::get('/daftar-tunggu', [WaitingListController::class, 'create'])->name('daftar-tunggu');
Route::post('/daftar-tunggu', [WaitingListController::class, 'store'])
    ->middleware('throttle:10,60')
    ->name('daftar-tunggu.store');

/*
|--------------------------------------------------------------------------
| Masuk & daftar
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->name('login.store');

    Route::get('/daftar', [RegisterController::class, 'create'])->name('daftar');
    // Rate limit pendaftaran: 5 percobaan per jam per IP. Tanpa ini satu skrip
    // bisa menghabiskan seluruh kuota kelas dalam hitungan menit.
    Route::post('/daftar', [RegisterController::class, 'store'])
        ->middleware('throttle:5,60')
        ->name('daftar.store');
});

/*
|--------------------------------------------------------------------------
| Verifikasi email & wizard penyiapan (butuh login, belum butuh kelas)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/verifikasi', [VerifyEmailController::class, 'notice'])->name('verifikasi.notice');
    Route::get('/verifikasi/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    // Kirim ulang dibatasi ketat: tiap percobaan mengirim email sungguhan lewat
    // SMTP Hostinger, dan kuota SMTP yang habis membuat SEMUA pendaftar tertahan.
    Route::post('/verifikasi/kirim-ulang', [VerifyEmailController::class, 'resend'])
        ->middleware('throttle:3,10')
        ->name('verifikasi.kirim-ulang');

    // Wizard wajib lewat email terverifikasi -- kelas tidak boleh lahir dari
    // alamat email yang belum terbukti bisa dihubungi.
    Route::middleware('verified')->group(function () {
        Route::get('/wizard/kelas', [WizardController::class, 'buatKelas'])->name('wizard.kelas');
        Route::post('/wizard/kelas', [WizardController::class, 'simpanKelas'])->name('wizard.kelas.store');
    });

    Route::get('/kelas-terhapus', [ClassroomLifecycleController::class, 'terhapus'])->name('kelas.terhapus');
    Route::patch('/kelas-terhapus/{kelas}/pulihkan', [ClassroomLifecycleController::class, 'restore'])
        ->name('kelas.restore');

    // Dashboard admin platform: agregat saja, tidak pernah detail transaksi.
    Route::get('/admin', [AdminPlatformController::class, 'index'])->name('admin.index');
});

Route::post('/keluar', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'verified', 'kelas'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Pemilih kelas aktif. Hasilnya disimpan ke session, tidak pernah ke URL.
    Route::post('/kelas-aktif', [ClassroomSwitchController::class, 'store'])->name('kelas.pilih');

    // Langkah wizard yang butuh kelas aktif.
    Route::get('/wizard/siswa', [WizardController::class, 'siswa'])->name('wizard.siswa');
    Route::get('/wizard/periode', [WizardController::class, 'periode'])->name('wizard.periode');

    // Ekspor data kelas -- jalan keluar yang dijanjikan Syarat Layanan.
    Route::get('/ekspor', [ClassroomExportController::class, 'index'])->name('ekspor.index');
    Route::get('/ekspor/{jenis}.csv', [ClassroomExportController::class, 'unduh'])->name('ekspor.unduh');

    // Hapus kelas: bertenggang, tidak pernah langsung permanen.
    Route::delete('/kelas', [ClassroomLifecycleController::class, 'destroy'])->name('kelas.destroy');

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

    /*
    |----------------------------------------------------------------------
    | Halaman yang menuntut penyiapan sudah selesai
    |----------------------------------------------------------------------
    | Middleware 'siap' memantulkan bendahara kembali ke langkah wizard yang
    | belum beres. Siswa, Periode, dan Pengaturan sengaja TIDAK ikut digerbang
    | -- justru di sanalah penyiapannya diselesaikan.
    |
    | Ini penegakan di server, bukan sekadar menyembunyikan menu: URL-nya masih
    | bisa diketik langsung, dan mencatat pembayaran sebelum ada periode
    | menghasilkan deposit menggantung yang membuat seluruh laporan nol tanpa
    | satu pun pesan galat.
    */
    Route::middleware('siap')->group(function () {

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

    // --- Tutup buku & serah terima (v1.2) ---
    Route::get('/tutup-buku', [BookClosingController::class, 'index'])->name('tutup-buku.index');
    Route::post('/tutup-buku', [BookClosingController::class, 'store'])->name('tutup-buku.store');
    Route::get('/tutup-buku/{closing}/serah-terima', [BookClosingController::class, 'serahTerima'])
        ->name('tutup-buku.serah-terima');
    // Membuka kembali tutup buku terakhir. Service yang memastikan hanya yang
    // terakhir bisa dibuka — bukan route ini, dan bukan tombol di Blade.
    Route::delete('/tutup-buku/{closing}', [BookClosingController::class, 'destroy'])->name('tutup-buku.destroy');
    Route::post('/tutup-buku/alih-kepemilikan', [ClassroomTransferController::class, 'store'])
        ->name('tutup-buku.transfer');

    }); // akhir grup 'siap'

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
