<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClassroom;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** classroom_id NULL = kategori bawaan sistem, ikut tampil di semua kelas. */
class ExpenseCategory extends Model
{
    use BelongsToClassroom;

    public $timestamps = false;

    protected $fillable = ['nama'];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    public function includesSystemRecords(): bool
    {
        return true;
    }

    public function isBawaan(): bool
    {
        return $this->classroom_id === null;
    }
}
