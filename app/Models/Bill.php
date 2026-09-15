<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClassroom;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tagihan seorang siswa. Sumbernya persis satu: periode rutin atau campaign (v1.1).
 * Status lunas TIDAK disimpan — selalu dihitung dari payment_allocations.
 */
class Bill extends Model
{
    use BelongsToClassroom;

    protected $fillable = ['student_id', 'period_id', 'nominal', 'is_bebas', 'alasan_bebas'];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'is_bebas' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** Tagihan yang dibebaskan tidak ditagih dan tidak dihitung sebagai tunggakan. */
    public function nominalTertagih(): string
    {
        return $this->is_bebas ? '0.00' : (string) $this->nominal;
    }

    public function scopeBelumBebas(Builder $query): Builder
    {
        return $query->where('is_bebas', false);
    }
}
