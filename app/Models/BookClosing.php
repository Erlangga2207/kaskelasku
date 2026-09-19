<?php

namespace App\Models;

use App\Exceptions\PeriodeTerkunciException;
use App\Models\Concerns\BelongsToClassroom;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot tutup buku satu rentang tanggal (PRD bagian 7).
 *
 * Dua peran sekaligus:
 *   1. Catatan historis — angkanya disimpan, tidak dihitung ulang.
 *   2. Kunci — transaksi bertanggal di dalam rentangnya tidak boleh diubah,
 *      dihapus, atau ditambah.
 *
 * Penguncian diperiksa di lapisan model (lihat trait TerkunciTutupBuku), bukan
 * di controller. Kalau pemeriksaannya ada di controller, setiap controller baru
 * yang lupa memanggilnya diam-diam membuka kembali seluruh buku.
 */
class BookClosing extends Model
{
    use BelongsToClassroom;

    /** Tabel ini tidak punya created_at/updated_at; waktunya dicatat di closed_at. */
    public $timestamps = false;

    protected $fillable = ['label', 'tgl_mulai', 'tgl_selesai', 'catatan'];

    protected function casts(): array
    {
        return [
            'tgl_mulai' => 'date',
            'tgl_selesai' => 'date',
            'saldo_awal' => 'decimal:2',
            'total_masuk' => 'decimal:2',
            'total_keluar' => 'decimal:2',
            'saldo_akhir' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Closing yang mengunci sebuah tanggal, atau null bila tanggal itu bebas.
     *
     * Global scope membatasi pencarian ke kelas aktif, jadi closing kelas lain
     * tidak pernah bisa mengunci transaksi kelas ini.
     */
    public static function penguncian(CarbonInterface|string|null $tanggal): ?self
    {
        if ($tanggal === null) {
            return null;
        }

        $hari = CarbonImmutable::parse($tanggal)->toDateString();

        return static::query()
            ->whereDate('tgl_mulai', '<=', $hari)
            ->whereDate('tgl_selesai', '>=', $hari)
            // Rentang seharusnya tidak pernah tumpang tindih (dijaga saat menutup),
            // tapi kalau toh terjadi, yang paling akhir yang disebut di pesan.
            ->orderByDesc('tgl_selesai')
            ->first();
    }

    /** Menolak aksi bila tanggalnya jatuh di rentang yang sudah ditutup. */
    public static function pastikanBebas(CarbonInterface|string|null $tanggal, string $aksi): void
    {
        if ($closing = static::penguncian($tanggal)) {
            throw new PeriodeTerkunciException($closing, $aksi);
        }
    }

    /**
     * Closing paling akhir — satu-satunya yang boleh dibuka kembali.
     *
     * Diurutkan pakai tgl_selesai, bukan closed_at: yang menentukan "terakhir"
     * adalah rentang mana yang paling baru dalam buku, bukan siapa yang kebetulan
     * menekan tombol belakangan.
     */
    public static function terakhir(): ?self
    {
        return static::query()->urutTerbaru()->first();
    }

    public function scopeUrutTerbaru(Builder $query): Builder
    {
        return $query->orderByDesc('tgl_selesai')->orderByDesc('id');
    }

    /** Closing ini yang paling akhir, jadi boleh dibuka kembali. */
    public function bolehDibuka(): bool
    {
        return static::terakhir()?->id === $this->id;
    }

    public function mencakup(CarbonInterface|string $tanggal): bool
    {
        $hari = CarbonImmutable::parse($tanggal)->startOfDay();

        return $hari->betweenIncluded(
            CarbonImmutable::parse($this->tgl_mulai)->startOfDay(),
            CarbonImmutable::parse($this->tgl_selesai)->startOfDay(),
        );
    }

    public function rentangTeks(): string
    {
        return CarbonImmutable::parse($this->tgl_mulai)->translatedFormat('j M Y')
            .' – '.CarbonImmutable::parse($this->tgl_selesai)->translatedFormat('j M Y');
    }

    /**
     * Pesan penolakan yang menyebut rentang pengunci DAN jalan keluarnya.
     *
     * Jalan keluarnya ikut disebut karena bendahara yang tertolak tanpa diberi
     * tahu caranya akan mencari cara lain: mengedit lewat database, atau membuka
     * tutup buku hanya untuk menambal satu angka.
     */
    public function pesanPenolakan(string $aksi): string
    {
        return "Tidak bisa {$aksi}: tanggalnya masuk periode yang sudah ditutup — "
            ."\"{$this->label}\" ({$this->rentangTeks()}). "
            .'Buku yang sudah ditutup tidak diubah lagi. Kalau ada yang perlu dikoreksi, '
            .'catat transaksi penyesuaian bertanggal hari ini, jangan mengubah data lama — '
            .'supaya laporan serah terima yang sudah ditandatangani tetap cocok dengan bukunya.';
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
