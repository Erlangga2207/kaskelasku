<?php

namespace App\Http\Controllers;

use App\Http\Requests\QrisRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

/**
 * QRIS statis per kelas (PRD bagian 6.2).
 *
 * Tidak ada integrasi payment gateway apa pun. Gambarnya hanya dipajang di
 * halaman kelas; konfirmasi bahwa uangnya masuk tetap dilakukan bendahara
 * secara manual lewat menu Bayar.
 */
class ClassroomQrisController extends Controller
{
    public function store(QrisRequest $request): RedirectResponse
    {
        $kelas = $this->kelas();
        $lama = $kelas->qris_path;

        $baru = $request->hasFile('qris')
            // Disimpan di storage privat, dipisah per kelas, dengan nama acak.
            // Nama berkas dari pengguna tidak pernah dipakai.
            ? $request->file('qris')->store('kelas-'.$kelas->id.'/qris', 'local')
            : $lama;

        $kelas->forceFill([
            'qris_path' => $baru,
            'qris_nama_pemilik' => $request->validated('qris_nama_pemilik'),
        ])->save();

        // Gambar lama dibuang hanya setelah yang baru tersimpan, supaya
        // kegagalan di tengah jalan tidak meninggalkan kelas tanpa QRIS.
        if ($lama !== null && $lama !== $baru) {
            Storage::disk('local')->delete($lama);
        }

        return redirect()->route('pengaturan.edit')->with(
            'sukses',
            'QRIS kelas disimpan dan sekarang tampil di halaman kelas.'
        );
    }

    public function destroy(): RedirectResponse
    {
        $kelas = $this->kelas();
        $lama = $kelas->qris_path;

        $kelas->forceFill(['qris_path' => null, 'qris_nama_pemilik' => null])->save();

        if ($lama !== null) {
            Storage::disk('local')->delete($lama);
        }

        return redirect()->route('pengaturan.edit')->with(
            'sukses',
            'QRIS dihapus. Halaman kelas tidak lagi menampilkannya.'
        );
    }
}
