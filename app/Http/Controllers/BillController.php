<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Services\KasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pembebasan iuran: menolkan tagihan tanpa menghapus barisnya, dengan alasan wajib.
 *
 * Barisnya sengaja dipertahankan supaya rekap periode tetap menunjukkan bahwa
 * siswa itu memang ditagih, lalu dibebaskan — bukan seolah-olah terlewat.
 */
class BillController extends Controller
{
    public function __construct(private readonly KasService $kas) {}

    public function bebas(Request $request, string $tagihan): RedirectResponse
    {
        $bill = $this->kelas()->bills()->with(['student', 'allocations'])->findOrFail($tagihan);

        $bebaskan = $request->boolean('bebas');

        if ($bebaskan) {
            $data = $request->validate([
                'alasan_bebas' => ['required', 'string', 'max:255'],
            ], [
                'alasan_bebas.required' => 'Alasan pembebasan wajib diisi.',
            ]);

            if ($this->kas->dibayarTagihan($bill) > 0) {
                return back()->with(
                    'galat',
                    'Tagihan ini sudah menerima pembayaran, jadi tidak bisa dibebaskan. Hapus pembayarannya dulu bila memang keliru.'
                );
            }

            $bill->update(['is_bebas' => true, 'alasan_bebas' => $data['alasan_bebas']]);

            return back()->with('sukses', "Tagihan {$bill->student->nama} dibebaskan.");
        }

        $bill->update(['is_bebas' => false, 'alasan_bebas' => null]);

        // Deposit yang menganggur bisa langsung menutup tagihan yang kembali aktif.
        $this->kas->alokasikanDeposit($bill->student);

        return back()->with('sukses', "Pembebasan tagihan {$bill->student->nama} dibatalkan.");
    }
}
