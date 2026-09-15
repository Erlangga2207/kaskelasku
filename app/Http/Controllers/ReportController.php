<?php

namespace App\Http\Controllers;

use App\Services\KasService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private readonly KasService $kas) {}

    public function index(Request $request): View
    {
        [$dari, $sampai] = $this->rentang($request);

        return view('laporan.index', [
            'ringkasan' => $this->kas->ringkasan($this->kelas()),
            'tunggakan' => $this->kas->daftarTunggakan($this->kelas()),
            'rekap' => $this->kas->rekapPeriode(),
            'riwayat' => $this->kas->riwayatTransaksi($dari, $sampai),
            'dari' => $dari,
            'sampai' => $sampai,
            'kelas' => $this->kelas(),
        ]);
    }

    /** Laporan PDF untuk dicetak atau dibagikan di rapat kelas. */
    public function pdf(Request $request): Response
    {
        [$dari, $sampai] = $this->rentang($request);

        $pdf = Pdf::loadView('laporan.pdf', [
            'kelas' => $this->kelas(),
            'ringkasan' => $this->kas->ringkasan($this->kelas()),
            'rekap' => $this->kas->rekapPeriode(),
            'tunggakan' => $this->kas->daftarTunggakan($this->kelas()),
            'riwayat' => $this->kas->riwayatTransaksi($dari, $sampai),
            'dari' => $dari,
            'sampai' => $sampai,
            'dicetakPada' => now(),
        ])->setPaper('a4');

        $namaBerkas = Str::slug('laporan kas '.$this->kelas()->nama_kelas.' '.now()->format('Y-m-d')).'.pdf';

        return $pdf->download($namaBerkas);
    }

    /** @return array{0: ?string, 1: ?string} */
    protected function rentang(Request $request): array
    {
        $data = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ], [], ['dari' => 'tanggal awal', 'sampai' => 'tanggal akhir']);

        return [$data['dari'] ?? null, $data['sampai'] ?? null];
    }
}
