<?php

namespace App\Models\Concerns;

use App\Models\BookClosing;

/**
 * Mengunci transaksi yang tanggalnya masuk rentang tutup buku.
 *
 * Dipasang di model, BUKAN dipanggil dari controller. Alasannya sama dengan
 * alasan global scope tenancy ada di model: pemeriksaan yang harus diingat
 * setiap kali menulis controller baru adalah pemeriksaan yang cepat atau lambat
 * akan terlupakan, dan kunci yang bisa terlupakan bukan kunci.
 *
 * Yang dijaga:
 *   - simpan baru  → tanggalnya tidak boleh jatuh di rentang tertutup
 *   - ubah         → tanggal LAMA maupun BARU harus bebas; memindahkan transaksi
 *                    keluar dari rentang tertutup juga termasuk mengubah sejarah
 *   - hapus        → tanggal lamanya harus bebas
 *   - pulihkan     → tanggalnya harus bebas
 */
trait TerkunciTutupBuku
{
    public static function bootTerkunciTutupBuku(): void
    {
        static::creating(function ($model) {
            BookClosing::pastikanBebas($model->getAttribute('tanggal'), $model->aksiSimpan());
        });

        static::updating(function ($model) {
            // Tanggal lama diperiksa lebih dulu: kalau barisnya memang duduk di
            // dalam periode tertutup, seluruh perubahan atasnya ditolak — bukan
            // hanya perubahan pada kolom tanggalnya.
            BookClosing::pastikanBebas($model->getOriginal('tanggal'), $model->aksiUbah());
            BookClosing::pastikanBebas($model->getAttribute('tanggal'), $model->aksiUbah());
        });

        static::deleting(function ($model) {
            BookClosing::pastikanBebas($model->getOriginal('tanggal'), $model->aksiHapus());
        });

        static::restoring(function ($model) {
            BookClosing::pastikanBebas($model->getOriginal('tanggal'), $model->aksiSimpan());
        });
    }

    abstract public function aksiSimpan(): string;

    abstract public function aksiUbah(): string;

    abstract public function aksiHapus(): string;
}
