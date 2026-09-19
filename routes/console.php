<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Perawatan terjadwal (v2.0)
|--------------------------------------------------------------------------
| Dijalankan dari satu cron Hostinger yang memanggil `php artisan schedule:run`
| tiap menit (langkahnya ada di DEPLOY.md). Keduanya ditulis di sini, bukan
| dipicu saat ada yang membuka halaman: pekerjaan yang menghapus data tidak
| boleh bergantung pada ada-tidaknya pengunjung — kelas yang ditinggalkan
| justru yang paling jarang dibuka, dan itu tepat kelas yang perlu dirawat.
|
| withoutOverlapping: shared hosting kadang menjalankan cron terlambat lalu
| menumpuk. Dua proses penghapusan permanen yang berjalan bersamaan bukan
| sesuatu yang layak dicoba.
*/

Schedule::command('kaskelas:rawat-kelas')
    ->dailyAt('03:10')
    ->withoutOverlapping()
    ->onOneServer();

// Demo direset tiap dini hari supaya angkanya tidak menumpuk, dan supaya
// coretan apa pun yang sempat masuk tidak bertahan lebih dari sehari.
Schedule::command('kaskelas:reset-demo')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();
