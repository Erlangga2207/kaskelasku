<?php

namespace App\Providers;

use App\Models\Bill;
use App\Models\Campaign;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Period;
use App\Models\Student;
use App\Observers\AuditObserver;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Model tenant yang perubahannya wajib masuk audit_logs. */
    protected array $modelTerpantau = [
        Student::class,
        Period::class,
        Campaign::class,
        Bill::class,
        Payment::class,
        PaymentAllocation::class,
        ExpenseCategory::class,
        Expense::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ($this->modelTerpantau as $model) {
            $model::observe(AuditObserver::class);
        }

        $this->emailVerifikasiBerbahasaIndonesia();

        // Kolom yang tidak ada di $fillable akan melempar error, bukan diam-diam diabaikan.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Email verifikasi bawaan Laravel berbahasa Inggris dan bernada korporat.
     *
     * Yang menerimanya bendahara kelas, sering siswa SMA, dan email asing
     * berbahasa Inggris dari pengirim yang belum dikenal adalah email yang
     * diabaikan atau dilaporkan sebagai spam. Isinya ditulis ulang di sini,
     * bukan lewat kelas notifikasi baru, karena yang berubah hanya teksnya.
     */
    protected function emailVerifikasiBerbahasaIndonesia(): void
    {
        VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            return (new MailMessage)
                ->subject('Verifikasi email KasKelas')
                ->greeting('Halo '.$notifiable->nama.'!')
                ->line('Terima kasih sudah mendaftar di KasKelas. Satu langkah lagi sebelum kelas pertamamu bisa dibuat: klik tombol di bawah untuk memastikan alamat email ini benar milikmu.')
                ->action('Verifikasi email saya', $url)
                ->line('Tautan ini berlaku terbatas. Kalau sudah kedaluwarsa, minta kirim ulang dari halaman verifikasi.')
                ->line('Kalau kamu merasa tidak pernah mendaftar di KasKelas, abaikan saja email ini. Tidak ada akun yang aktif tanpa verifikasi ini.')
                ->salutation('Salam, KasKelas');
        });
    }
}
