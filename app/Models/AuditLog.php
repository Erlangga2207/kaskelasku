<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClassroom;
use App\Support\CurrentClassroom;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabel hanya-tulis. Aksi tingkat akun (login, register) punya classroom_id NULL,
 * karena saat itu belum ada kelas aktif.
 */
class AuditLog extends Model
{
    use BelongsToClassroom;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'aksi', 'nama_tabel', 'record_id', 'data_lama', 'data_baru', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'data_lama' => 'array',
            'data_baru' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Satu-satunya pintu menulis audit log. Dipanggil Observer untuk perubahan data,
     * dan langsung oleh controller untuk aksi non-CRUD (login, rotate_token).
     */
    public static function catat(
        string $aksi,
        string $namaTabel,
        ?int $recordId = null,
        ?array $dataLama = null,
        ?array $dataBaru = null,
        ?int $classroomId = null,
    ): void {
        $log = new static([
            'user_id' => auth()->id(),
            'aksi' => $aksi,
            'nama_tabel' => $namaTabel,
            'record_id' => $recordId,
            'data_lama' => $dataLama,
            'data_baru' => $dataBaru,
            'ip' => request()->ip(),
        ]);

        $log->classroom_id = $classroomId ?? CurrentClassroom::id();
        $log->save();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classroomIsRequired(): bool
    {
        return false;
    }
}
