<?php

namespace App\Models\Concerns;

use App\Models\Classroom;
use App\Models\Scopes\ClassroomScope;
use App\Support\CurrentClassroom;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Dipasang di SEMUA model tenant, tanpa pengecualian.
 *
 * Tiga hal yang dijamin trait ini:
 *   1. Baca  — Global Scope memfilter classroom_id pada setiap query.
 *   2. Tulis — classroom_id diisi dari kelas aktif, nilai dari request ditimpa.
 *   3. Ubah  — classroom_id tidak bisa dipindah ke kelas lain setelah tersimpan.
 */
trait BelongsToClassroom
{
    public static function bootBelongsToClassroom(): void
    {
        static::addGlobalScope(new ClassroomScope());

        static::creating(function ($model) {
            if (CurrentClassroom::bypassed()) {
                return;
            }

            $classroomId = CurrentClassroom::id();

            if ($classroomId === null) {
                if ($model->classroomIsRequired()) {
                    throw new RuntimeException(
                        'Menyimpan '.$model::class.' tanpa kelas aktif ditolak.'
                    );
                }

                return;
            }

            // Ditimpa, bukan diisi kalau kosong: nilai dari request tidak pernah dipercaya.
            $model->setAttribute('classroom_id', $classroomId);
        });

        static::updating(function ($model) {
            if (! CurrentClassroom::bypassed() && $model->isDirty('classroom_id')) {
                throw new RuntimeException(
                    'classroom_id tidak boleh dipindah setelah baris tersimpan.'
                );
            }
        });
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /** Model yang boleh punya classroom_id NULL (mis. audit log aksi tingkat akun) override ini. */
    public function classroomIsRequired(): bool
    {
        return true;
    }

    /** Model yang punya baris bawaan sistem (classroom_id NULL) override ini. */
    public function includesSystemRecords(): bool
    {
        return false;
    }
}
