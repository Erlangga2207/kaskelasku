<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClassroom;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Data pribadi seminimal mungkin: nama, no absen, status aktif.
 * Kolom NIS/NISN, nomor HP, alamat, atau foto DILARANG ditambahkan (UU PDP).
 */
class Student extends Model
{
    use BelongsToClassroom, SoftDeletes;

    protected $fillable = ['nama', 'no_absen', 'tgl_mulai_aktif', 'tgl_berhenti', 'is_active'];

    protected function casts(): array
    {
        return [
            'tgl_mulai_aktif' => 'date',
            'tgl_berhenti' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeUrutAbsen(Builder $query): Builder
    {
        return $query->orderByRaw('no_absen IS NULL, no_absen')->orderBy('nama');
    }

    /** Siswa ditagih untuk sebuah periode hanya selama rentang keaktifannya. */
    public function aktifPadaPeriode(Period $period): bool
    {
        if ($this->tgl_mulai_aktif->gt($period->tgl_selesai)) {
            return false;
        }

        return $this->tgl_berhenti === null || $this->tgl_berhenti->gte($period->tgl_mulai);
    }
}
