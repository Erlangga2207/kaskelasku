<?php

namespace App\Http\Controllers;

use App\Models\WaitingListEntry;
use App\Support\Kapasitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Daftar tunggu, muncul menggantikan form pendaftaran saat kuota penuh.
 *
 * Menolak tanpa menawarkan apa pun membuat orang pergi dan tidak kembali;
 * menerima pendaftaran melebihi kapasitas membuat kelas yang sudah memakainya
 * untuk uang sungguhan ikut melambat. Daftar tunggu adalah jalan tengah yang
 * jujur: tempatnya memang belum ada, dan itu dikatakan apa adanya.
 */
class WaitingListController extends Controller
{
    public function create(): View|RedirectResponse
    {
        // Kuota sudah longgar lagi → tidak ada gunanya menahan orang di antrean.
        if (! Kapasitas::kuotaSistemPenuh()) {
            return redirect()->route('daftar');
        }

        return view('publik.daftar-tunggu', [
            'jumlahKelas' => Kapasitas::jumlahKelasSistem(),
            'batas' => Kapasitas::batasKelasSistem(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ], [], ['email' => 'email']);

        // firstOrCreate, bukan unique validation: memberi tahu "email ini sudah
        // ada di antrean" bocor sedikit informasi, dan tidak membantu siapa pun.
        // created_at tidak dioper sendiri — Eloquent yang mengisinya, dan
        // kolomnya sengaja di luar $fillable supaya tidak bisa datang dari form.
        WaitingListEntry::firstOrCreate(['email' => $data['email']]);

        return back()->with(
            'sukses',
            'Emailmu dicatat. Kami kabari begitu ada tempat kosong — tanpa email lain, tanpa iklan.'
        );
    }
}
