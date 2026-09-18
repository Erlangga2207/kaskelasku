<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Akar tenancy — model ini sengaja TIDAK memakai BelongsToClassroom.
 * Semua model lain difilter terhadap baris ini.
 */
class Classroom extends Model
{
    /**
     * qris_path sengaja TIDAK di sini: nilainya berasal dari hasil penyimpanan
     * berkas di server, bukan dari input pengguna — sama seperti bukti_path
     * pada payments. Menaruhnya di fillable membuka jalan bagi request untuk
     * menunjuk berkas mana pun di storage.
     */
    protected $fillable = [
        'nama_kelas', 'sekolah', 'tipe_periode',
        'denda_aktif', 'denda_mode', 'denda_nominal', 'grace_days', 'denda_maks',
        'template_pengingat', 'qris_nama_pemilik',
    ];

    protected function casts(): array
    {
        return [
            'denda_aktif' => 'boolean',
            'denda_nominal' => 'decimal:2',
            'denda_maks' => 'decimal:2',
            'grace_days' => 'integer',
            'token_rotated_at' => 'datetime',
            'persetujuan_data_at' => 'datetime',
            'dihapus_pada' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    /** Token halaman publik: 40 karakter acak, unik lintas seluruh kelas. */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(40);
        } while (static::where('public_token', $token)->exists());

        return $token;
    }

    public function rotateToken(): void
    {
        $this->forceFill([
            'public_token' => static::generateToken(),
            'token_rotated_at' => now(),
        ])->save();
    }

    /** Kelas sudah memasang QRIS, jadi boleh ditampilkan di halaman kelas. */
    public function punyaQris(): bool
    {
        return $this->qris_path !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Kesiapan pakai (wizard v2.0)
    |--------------------------------------------------------------------------
    | Urutannya WAJIB: kelas → siswa → periode. Bukan sekadar saran tampilan.
    |
    | Tanpa periode, tidak ada tagihan; tanpa tagihan, setiap pembayaran yang
    | dicatat mendarat sebagai deposit menggantung dan seluruh laporan tetap
    | menunjukkan nol. Bendahara yang mengalaminya tidak melihat pesan galat apa
    | pun — dia hanya melihat angka yang salah, berminggu-minggu kemudian.
    | Karena itu urutan ini dipaksa di server, bukan cuma diarahkan lewat menu.
    */

    public function punyaSiswa(): bool
    {
        return $this->students()->exists();
    }

    /** Periode libur tidak menerbitkan tagihan, jadi tidak dihitung sebagai siap. */
    public function punyaPeriode(): bool
    {
        return $this->periods()->where('is_libur', false)->exists();
    }

    /**
     * Ada sesuatu di kelas ini yang bisa menerbitkan tagihan.
     *
     * Yang sebenarnya berbahaya bukan "tidak ada periode", melainkan "tidak ada
     * tagihan sama sekali" — itulah yang membuat pembayaran mendarat sebagai
     * deposit menggantung. Iuran insidental juga menerbitkan tagihan, jadi kelas
     * yang sudah punya campaign hidup tidak perlu dipantulkan lagi ke wizard.
     */
    public function punyaSumberTagihan(): bool
    {
        return $this->punyaPeriode()
            || $this->campaigns()->whereIn('status', ['aktif', 'selesai'])->exists();
    }

    public function setupSelesai(): bool
    {
        return $this->punyaSiswa() && $this->punyaSumberTagihan();
    }

    /** Nama route langkah wizard yang masih harus diselesaikan, atau null bila beres. */
    public function langkahWizardBerikutnya(): ?string
    {
        return match (true) {
            ! $this->punyaSiswa() => 'wizard.siswa',
            ! $this->punyaSumberTagihan() => 'wizard.periode',
            default => null,
        };
    }

    public function sedangDihapus(): bool
    {
        return $this->status === 'dihapus';
    }

    /** Kelas demo: boleh dilihat siapa saja, tapi tidak boleh ditulis. */
    public function isDemo(): bool
    {
        return (bool) $this->is_demo;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('peran', 'created_at');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }
}
