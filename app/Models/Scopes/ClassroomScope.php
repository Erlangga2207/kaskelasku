<?php

namespace App\Models\Scopes;

use App\Support\CurrentClassroom;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Menempelkan `where classroom_id = <kelas aktif>` pada SETIAP query model tenant.
 *
 * Kalau kelas aktif tidak ada, scope ini gagal-tertutup: query dipaksa tidak
 * mengembalikan baris apa pun. Lebih baik halaman kosong daripada data kelas lain.
 */
class ClassroomScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (CurrentClassroom::bypassed()) {
            return;
        }

        $column = $model->getTable().'.classroom_id';
        $classroomId = CurrentClassroom::id();

        if ($classroomId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        // Sebagian tabel (expense_categories) punya baris bawaan sistem: classroom_id NULL.
        if (method_exists($model, 'includesSystemRecords') && $model->includesSystemRecords()) {
            $builder->where(function (Builder $query) use ($column, $classroomId) {
                $query->where($column, $classroomId)->orWhereNull($column);
            });

            return;
        }

        $builder->where($column, $classroomId);
    }
}
