<?php

namespace App\Http\Controllers;

use App\Services\KasService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly KasService $kas) {}

    public function __invoke(): View
    {
        $kelas = $this->kelas();

        return view('dashboard', [
            'kelas' => $kelas,
            'ringkasan' => $this->kas->ringkasan($kelas),
            // Lima penunggak terbesar saja — daftar lengkapnya ada di halaman laporan.
            'tunggakanTeratas' => $this->kas->daftarTunggakan($kelas)->take(5),
            'rekapTerbaru' => $this->kas->rekapPeriode()->reverse()->take(3)->values(),
            'riwayatTerbaru' => $this->kas->riwayatTransaksi()->take(6),
            // Hanya campaign yang masih berjalan: yang dibatalkan sudah tidak
            // menahan uang apa pun, jadi tidak perlu ikut memenuhi beranda.
            'campaign' => $this->kas->rekapCampaign(hanyaBerjalan: true)->take(3),
        ]);
    }
}
