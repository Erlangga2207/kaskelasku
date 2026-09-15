<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClassroom;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Potongan sebuah pembayaran yang dipakai untuk melunasi sebuah tagihan. */
class PaymentAllocation extends Model
{
    use BelongsToClassroom;

    protected $fillable = ['payment_id', 'bill_id', 'jumlah'];

    protected function casts(): array
    {
        return ['jumlah' => 'decimal:2'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
