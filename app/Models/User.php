<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Bendahara (atau admin platform).
 *
 * MustVerifyEmail dipasang sejak v2.0: sebelum ini aplikasi hanya dipakai
 * sendiri, jadi email tidak perlu dibuktikan. Setelah pendaftaran dibuka untuk
 * umum, alamat email adalah satu-satunya jalan pulang kalau kata sandi hilang —
 * dan alamat yang belum pernah dibuktikan bukan jalan pulang.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['nama', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** Kelas yang dimiliki user ini (owner). */
    public function ownedClassrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'owner_id');
    }

    /** Kelas yang boleh diakses user ini — dasar pemilih kelas aktif. */
    public function classrooms(): BelongsToMany
    {
        // Pivot classroom_user hanya punya created_at, jadi tidak memakai withTimestamps().
        return $this->belongsToMany(Classroom::class)->withPivot('peran', 'created_at');
    }

    /** Kelas yang masih hidup — yang sedang dalam tenggang hapus tidak ikut. */
    public function kelasAktif(): BelongsToMany
    {
        return $this->classrooms()->where('classrooms.status', 'aktif');
    }

    public function isAdminPlatform(): bool
    {
        return $this->role === 'admin_platform';
    }
}
