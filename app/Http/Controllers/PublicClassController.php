<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Student;
use App\Services\KasService;
use App\Support\CurrentClassroom;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Halaman kelas: read-only, tanpa login, dibuka dengan token pada URL.
 *
 * Yang boleh tampil di sini hanya nama, nomor absen, nominal, dan status bayar.
 * Bukti transfer, catatan pembayaran, data akun bendahara, dan audit log
 * TIDAK PERNAH menyentuh halaman ini.
 */
class PublicClassController extends Controller
{
    public function __construct(private readonly KasService $kas) {}

    public function show(): View
    {
        $kelas = CurrentClassroom::getOrFail();

        $tagihan = Bill::with(['period', 'allocations'])->get();

        $siswa = Student::aktif()->urutAbsen()->get()->map(function (Student $s) use ($tagihan) {
            $miliknya = $tagihan->where('student_id', $s->id)
                ->sortBy(fn (Bill $b) => $b->period?->tgl_mulai?->timestamp ?? PHP_INT_MAX);

            return [
                'siswa' => $s,
                'baris' => $miliknya->map(fn (Bill $b) => [
                    'label' => $b->period?->label ?? 'Iuran insidental',
                    'nominal' => $b->nominal,
                    'status' => $this->kas->statusTagihan($b),
                    'sisa' => $this->kas->sisaTagihan($b),
                ])->values(),
                'belum_lunas' => $miliknya->filter(fn (Bill $b) => $this->kas->sisaTagihan($b) > 0)->count(),
            ];
        });

        return view('publik.kelas', [
            'kelas' => $kelas,
            'ringkasan' => $this->kas->ringkasan($kelas),
            'rekap' => $this->kas->rekapPeriode(),
            'daftarSiswa' => $siswa,
            'diperbaruiPada' => now(),
        ]);
    }

    /** Manifest khusus per kelas, supaya anggota memasang pintasan ke kelasnya sendiri. */
    public function manifest(string $token): JsonResponse
    {
        $kelas = CurrentClassroom::getOrFail();

        return response()->json([
            'name' => 'Kas Kelas '.$kelas->nama_kelas,
            'short_name' => $kelas->nama_kelas,
            'description' => 'Rekap kas kelas '.$kelas->nama_kelas.' — '.$kelas->sekolah,
            'lang' => 'id',
            'start_url' => route('publik.kelas', $token),
            'scope' => route('publik.kelas', $token),
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#f1f5f9',
            'theme_color' => '#1d4ed8',
            'icons' => [
                ['src' => '/ikon/ikon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/ikon/ikon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/ikon/ikon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }
}
