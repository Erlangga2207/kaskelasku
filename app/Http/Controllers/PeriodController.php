<?php

namespace App\Http\Controllers;

use App\Models\Period;
use App\Services\KasService;
use App\Support\Uang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PeriodController extends Controller
{
    public function __construct(private readonly KasService $kas) {}

    public function index(): View
    {
        $periode = $this->kelas()->periods()
            ->withCount([
                'bills',
                'bills as bills_dibayar_count' => fn ($q) => $q->has('allocations'),
            ])
            ->urutWaktu()
            ->get();

        return view('periode.index', [
            'daftarPeriode' => $periode,
            'nominalTerakhir' => $periode->last()?->nominal,
            'akhirTahunAjaran' => $this->kas->akhirTahunAjaran(now()),
        ]);
    }

    /** Membuat periode berurutan sampai akhir tahun ajaran, lalu tagihannya. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tgl_mulai' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'sampai' => ['nullable', 'date', 'after_or_equal:tgl_mulai'],
        ], [], ['tgl_mulai' => 'tanggal mulai', 'sampai' => 'tanggal akhir']);

        $sebelum = $this->kelas()->periods()->pluck('id')->all();

        $dibuat = $this->kas->generatePeriode(
            $this->kelas(),
            $data['tgl_mulai'],
            $data['nominal'],
            $data['sampai'] ?? null,
        );

        if ($dibuat === 0) {
            return back()->with('peringatan', 'Tidak ada periode baru — rentang itu sudah punya periode semua.');
        }

        $tagihan = $this->kelas()->periods()
            ->whereNotIn('id', $sebelum)
            ->get()
            ->sum(fn (Period $p) => $this->kas->generateTagihanPeriode($p));

        return redirect()->route('periode.index')->with(
            'sukses',
            "{$dibuat} periode dibuat beserta {$tagihan} tagihan siswa."
        );
    }

    /**
     * Mengubah nominal satu periode. Periode lain tidak ikut berubah —
     * kenaikan iuran tidak boleh mengubah tagihan bulan yang sudah lewat.
     */
    public function update(Request $request, string $periode): RedirectResponse
    {
        $periode = $this->cariPeriode($periode);

        $data = $request->validate([
            'nominal' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'label' => ['required', 'string', 'max:50'],
        ]);

        $periode->update([
            'label' => $data['label'],
            'nominal' => Uang::keDesimal(Uang::keSen($data['nominal'])),
        ]);

        $disesuaikan = $this->kas->selaraskanNominalTagihan($periode);

        return redirect()->route('periode.index')->with(
            'sukses',
            "Periode {$periode->label} diperbarui, {$disesuaikan} tagihan ikut disesuaikan."
        );
    }

    /** Menandai periode libur (tagihan dibuang) atau membatalkannya (tagihan dibuat lagi). */
    public function libur(Request $request, string $periode): RedirectResponse
    {
        $periode = $this->cariPeriode($periode);
        $jadikanLibur = $request->boolean('libur');

        if (! $jadikanLibur) {
            $periode->update(['is_libur' => false]);
            $dibuat = $this->kas->generateTagihanPeriode($periode);

            return redirect()->route('periode.index')->with(
                'sukses',
                "Periode {$periode->label} kembali ditagihkan, {$dibuat} tagihan dibuat."
            );
        }

        try {
            $terhapus = $this->kas->hapusTagihanPeriode($periode);
        } catch (RuntimeException $e) {
            return back()->with('galat', $e->getMessage());
        }

        $periode->update(['is_libur' => true]);

        return redirect()->route('periode.index')->with(
            'sukses',
            "Periode {$periode->label} ditandai libur, {$terhapus} tagihan dibatalkan."
        );
    }

    protected function cariPeriode(string $id): Period
    {
        return $this->kelas()->periods()->findOrFail($id);
    }
}
