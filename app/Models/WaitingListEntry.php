<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Antrean pendaftaran saat kuota kelas se-sistem penuh.
 *
 * Sengaja HANYA menyimpan email. Bukan nama, bukan sekolah, bukan nomor HP:
 * data yang tidak dikumpulkan adalah data yang tidak bisa bocor, dan untuk
 * mengabari "tempatnya sudah ada" satu alamat email sudah cukup.
 */
class WaitingListEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['email'];
}
