<?php

namespace App\Exceptions;

use App\Models\BookClosing;
use RuntimeException;

/**
 * Dilempar saat sebuah transaksi menyentuh rentang tanggal yang sudah ditutup.
 *
 * Membawa objek closing-nya, bukan cuma pesan, supaya pemanggil bisa menyebut
 * rentang MANA yang mengunci. "Ditolak" tanpa menyebut rentangnya membuat
 * bendahara menebak-nebak, lalu mencoba lagi dengan tanggal yang sama.
 */
class PeriodeTerkunciException extends RuntimeException
{
    public function __construct(public readonly BookClosing $closing, string $aksi)
    {
        parent::__construct($closing->pesanPenolakan($aksi));
    }
}
