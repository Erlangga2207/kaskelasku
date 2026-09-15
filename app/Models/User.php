<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
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

    public function isAdminPlatform(): bool
    {
        return $this->role === 'admin_platform';
    }
}
