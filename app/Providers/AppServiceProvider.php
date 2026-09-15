<?php

namespace App\Providers;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Period;
use App\Models\Student;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Model tenant yang perubahannya wajib masuk audit_logs. */
    protected array $modelTerpantau = [
        Student::class,
        Period::class,
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

        // Kolom yang tidak ada di $fillable akan melempar error, bukan diam-diam diabaikan.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
