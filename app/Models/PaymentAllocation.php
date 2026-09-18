<?php

namespace App\Models;

use App\Exceptions\PeriodeTerkunciException;
use App\Models\Concerns\BelongsToClassroom;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Potongan sebuah pembayaran yang dipakai untuk melunasi sebuah tagihan. */
class PaymentAllocation extends Model
{
    use BelongsToClassroom;

    protected $fillable = ['payment_id', 'bill_id', 'jumlah'];

    protected function casts(): array
    {
        return ['jumlah' => 'decimal:2'];
    }

    /**
     * Alokasi tidak punya tanggal sendiri — tanggalnya adalah tanggal pembayaran
     * yang mendanainya. Jadi alokasi milik pembayaran di periode tertutup ikut
     * terkunci.
     *
     * Yang dijaga hanya ubah & hapus, SENGAJA bukan simpan baru. Pembayaran baru
     * yang menyisakan deposit boleh menutup tagihan lama lewat alokasi baru —
     * itu menambah catatan, bukan mengubah sejarah, dan uang masuknya sendiri
     * bertanggal di luar periode tertutup. Kalau simpan baru ikut dikunci,
     * alokasi deposit otomatis akan meledak di tengah transaksi yang sah.
     */
    protected static function booted(): void
    {
        static::updating(fn (self $model) => $model->pastikanPembayaranBebas('mengubah alokasi pembayaran ini'));
        static::deleting(fn (self $model) => $model->pastikanPembayaranBebas('mengubah alokasi pembayaran ini'));
    }

    protected function pastikanPembayaranBebas(string $aksi): void
    {
        $tanggal = $this->payment?->tanggal;

        if ($tanggal !== null && $closing = BookClosing::penguncian($tanggal)) {
            throw new PeriodeTerkunciException($closing, $aksi);
        }
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
