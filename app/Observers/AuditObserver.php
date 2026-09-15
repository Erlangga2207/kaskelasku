<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Mencatat setiap perubahan data tenant ke audit_logs.
 *
 * Dipasang di AppServiceProvider untuk model transaksi. Tidak dipasang pada
 * AuditLog sendiri — kalau iya, setiap pencatatan akan memanggil dirinya lagi.
 */
class AuditObserver
{
    /** Kolom yang tidak ikut dicatat karena tidak menambah informasi. */
    protected array $abaikan = ['created_at', 'updated_at'];

    public function created(Model $model): void
    {
        $this->catat('create', $model, null, $this->bersihkan($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $perubahan = $this->bersihkan($model->getChanges());

        if ($perubahan === []) {
            return;
        }

        $lama = array_intersect_key($this->bersihkan($model->getOriginal()), $perubahan);

        $this->catat('update', $model, $lama, $perubahan);
    }

    public function deleted(Model $model): void
    {
        $this->catat('delete', $model, $this->bersihkan($model->getOriginal()), null);
    }

    public function restored(Model $model): void
    {
        $this->catat('restore', $model, null, $this->bersihkan($model->getAttributes()));
    }

    protected function catat(string $aksi, Model $model, ?array $lama, ?array $baru): void
    {
        AuditLog::catat(
            aksi: $aksi,
            namaTabel: $model->getTable(),
            recordId: $model->getKey(),
            dataLama: $lama,
            dataBaru: $baru,
            classroomId: $model->getAttribute('classroom_id'),
        );
    }

    protected function bersihkan(array $data): array
    {
        return array_diff_key($data, array_flip($this->abaikan));
    }
}
