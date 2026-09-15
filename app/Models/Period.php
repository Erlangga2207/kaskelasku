<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClassroom;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Nominal melekat pada periode, supaya kenaikan iuran tidak mengubah tagihan lampau. */
class Period extends Model
{
    use BelongsToClassroom;

    protected $fillable = ['label', 'tipe', 'tgl_mulai', 'tgl_selesai', 'jatuh_tempo', 'nominal', 'is_libur'];

    protected function casts(): array
    {
        return [
            'tgl_mulai' => 'date',
            'tgl_selesai' => 'date',
            'jatuh_tempo' => 'date',
            'nominal' => 'decimal:2',
            'is_libur' => 'boolean',
        ];
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function scopeUrutWaktu(Builder $query): Builder
    {
        return $query->orderBy('tgl_mulai');
    }

    public function sudahJatuhTempo(): bool
    {
        return $this->jatuh_tempo->isPast();
    }
}
