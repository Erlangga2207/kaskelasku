<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClassroom;
use App\Models\Concerns\TerkunciTutupBuku;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Uang yang diterima bendahara. Pembagiannya ke tagihan ada di payment_allocations. */
class Payment extends Model
{
    use BelongsToClassroom, SoftDeletes, TerkunciTutupBuku;

    protected $fillable = ['student_id', 'tanggal', 'jumlah', 'metode', 'catatan'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jumlah' => 'decimal:2',
        ];
    }

    /** created_by selalu dari user yang login, tidak pernah dari input. */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->created_by ??= auth()->id();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Penguncian tutup buku
    |--------------------------------------------------------------------------
    | Kalimatnya ditulis dari sudut pandang bendahara ("mencatat pembayaran"),
    | bukan dari sudut pandang tabel, karena kalimat inilah yang muncul di layar
    | saat aksinya ditolak.
    */

    public function aksiSimpan(): string
    {
        return 'mencatat pembayaran ini';
    }

    public function aksiUbah(): string
    {
        return 'mengubah pembayaran ini';
    }

    public function aksiHapus(): string
    {
        return 'menghapus pembayaran ini';
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
