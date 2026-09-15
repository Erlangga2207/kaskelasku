<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClassroom;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Uang yang diterima bendahara. Pembagiannya ke tagihan ada di payment_allocations. */
class Payment extends Model
{
    use BelongsToClassroom, SoftDeletes;

    protected $fillable = ['student_id', 'tanggal', 'jumlah', 'metode', 'catatan'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jumlah' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
