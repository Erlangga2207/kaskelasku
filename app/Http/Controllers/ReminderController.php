<?php

namespace App\Http\Controllers;

use App\Services\KasService;
use App\Services\PengingatService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman pengingat tunggakan: menyiapkan teks, bukan mengirimnya.
 *
 * Tidak ada satu pun pemanggilan API WhatsApp di sini, dan tidak akan ada.
 * Bendahara menyalin teksnya lalu mengirim sendiri — itu keputusan desain,
 * bukan keterbatasan sementara.
 */
class ReminderController extends Controller
{
    public function __construct(
        private readonly KasService $kas,
        private readonly PengingatService $pengingat,
    ) {}

    public function index(Request $request): View
    {
        // Filter tampilan, bukan aksi tulis — divalidasi di tempat, seperti
        // rentang tanggal di halaman laporan.
        $data = $request->validate(
            ['batas' => ['nullable', 'date']],
            [],
            ['batas' => 'batas pembayaran'],
        );

        $batas = $data['batas'] ?? PengingatService::batasBawaan()->toDateString();

        // Daftar penunggak dipakai apa adanya dari KasService, supaya urutan
        // dan angkanya sama persis dengan halaman laporan.
        $penunggak = $this->kas->daftarTunggakan($this->kelas());

        $teks = $this->pengingat->untukBanyakSiswa(
            $penunggak->pluck('siswa'),
            $batas,
            $this->kelas(),
        );

        return view('pengingat.index', [
            // Urutan daftarTunggakan (terbesar dulu) yang dipertahankan —
            // menagih paling masuk akal dimulai dari yang paling menumpuk.
            'daftar' => $penunggak
                ->map(fn (array $baris) => $teks->get($baris['siswa']->id))
                ->filter()
                ->values(),
            'batas' => $batas,
        ]);
    }
}
