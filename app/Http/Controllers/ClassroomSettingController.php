<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\Uang;
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

            // Denda DEFAULT NONAKTIF (PRD bagian 8) — hanya dihidupkan bila
            // kelas memang menyepakatinya.
            'denda_aktif' => ['nullable', 'boolean'],
            'denda_mode' => ['required_if:denda_aktif,1', Rule::in(['tetap', 'harian'])],
            'denda_nominal' => ['required_if:denda_aktif,1', 'numeric', 'min:0', 'max:9999999999'],
            'grace_days' => ['required_if:denda_aktif,1', 'integer', 'min:0', 'max:365'],
            'denda_maks' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        $data['denda_aktif'] = $request->boolean('denda_aktif');
        $data['denda_mode'] = $data['denda_mode'] ?? 'tetap';
        $data['denda_nominal'] = Uang::keDesimal(Uang::keSen($data['denda_nominal'] ?? 0));
        $data['grace_days'] = (int) ($data['grace_days'] ?? 7);
        $data['denda_maks'] = ($data['denda_maks'] ?? null) === null
            ? null
            : Uang::keDesimal(Uang::keSen($data['denda_maks']));

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
