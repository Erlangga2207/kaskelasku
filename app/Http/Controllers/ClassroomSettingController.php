<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassroomSettingController extends Controller
{
    public function edit(): View
    {
        return view('pengaturan.edit', [
            'kelas' => $this->kelas(),
            'tipeTerkunci' => $this->tipePeriodeTerkunci(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $terkunci = $this->tipePeriodeTerkunci();

        $data = $request->validate([
            'nama_kelas' => ['required', 'string', 'max:100'],
            'sekolah' => ['required', 'string', 'max:150'],
            'tipe_periode' => [
                $terkunci ? 'nullable' : 'required',
                Rule::in(['mingguan', 'bulanan']),
            ],
        ]);

        // Tipe periode tidak bisa diubah setelah ada transaksi: periode lama
        // sudah punya tagihan dan pembayaran yang tidak bisa dihitung ulang.
        if ($terkunci) {
            unset($data['tipe_periode']);
        }

        $this->kelas()->update($data);

        return redirect()->route('pengaturan.edit')->with('sukses', 'Pengaturan kelas disimpan.');
    }

    protected function tipePeriodeTerkunci(): bool
    {
        return Payment::withTrashed()->exists() || Period::exists();
    }
}
