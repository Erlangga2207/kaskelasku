<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClassroom;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Iuran insidental: satu penggalangan dengan nominal sama untuk tiap peserta
 * (studi tour, perpisahan, bingkisan guru).
 *
 * Campaign TIDAK punya logika uang sendiri. Ia hanya menerbitkan bills seperti
 * periode menerbitkan bills, lalu memakai mesin alokasi pembayaran yang sama
 * persis. Kalau suatu saat ada cabang kode khusus campaign di KasService,
 * berarti desainnya yang salah — bukan alasan menambal.
 */
class Campaign extends Model
{
    use BelongsToClassroom;

    protected $fillable = ['nama', 'deskripsi', 'nominal_per_siswa', 'deadline', 'status'];

    protected $attributes = ['status' => 'aktif'];

    protected function casts(): array
    {
        return [
            'nominal_per_siswa' => 'decimal:2',
            'deadline' => 'date',
        ];
    }

    /** created_by selalu dari user yang login, tidak pernah dari input. */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->created_by ??= auth()->id();
        });
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Campaign yang masih menagih. Yang dibatalkan tidak pernah ikut hitungan dana. */
    public function scopeBerjalan(Builder $query): Builder
    {
        return $query->whereIn('status', ['aktif', 'selesai']);
    }

    public function scopeUrutBaru(Builder $query): Builder
    {
        return $query->orderByDesc('id');
    }

    public function isAktif(): bool
    {
        return $this->status === 'aktif';
    }

    public function isDibatalkan(): bool
    {
        return $this->status === 'dibatalkan';
    }

    public function sudahLewatDeadline(): bool
    {
        return $this->deadline !== null && $this->deadline->isPast();
    }
}
