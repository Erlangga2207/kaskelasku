<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookClosingRequest;
use App\Models\BookClosing;
use App\Services\TutupBukuService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tutup buku & laporan serah terima (PRD bagian 7).
 *
 * Controller ini TIDAK memeriksa penguncian transaksi — pemeriksaan itu duduk
 * di lapisan model (trait TerkunciTutupBuku), supaya berlaku untuk semua jalur
 * tulis, termasuk yang ditulis nanti.
 */
class BookClosingController extends Controller
{
    public function __construct(private readonly TutupBukuService $tutupBuku) {}

    public function index(Request $request): View
    {
        [$dari, $sampai] = $this->rentangPratinjau($request);

        return view('tutup-buku.index', [
            'riwayat' => $this->tutupBuku->riwayat(),
            'terakhir' => BookClosing::terakhir(),
            // Pratinjau angka sebelum bendahara menekan tombol. Menutup buku
            // tanpa melihat angkanya dulu adalah cara paling mudah membekukan
            // rentang yang salah.
            'pratinjau' => $this->tutupBuku->hitung($dari, $sampai),
            'dari' => $dari,
            'sampai' => $sampai,
            'kelas' => $this->kelas(),
        ]);
    }

    public function store(BookClosingRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $closing = $this->tutupBuku->tutup(
                label: $data['label'],
                dari: $data['tgl_mulai'],
                sampai: $data['tgl_selesai'],
                catatan: $data['catatan'] ?? null,
                kelas: $this->kelas(),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('galat', $e->getMessage());
        }

        return redirect()->route('tutup-buku.index')->with(
            'sukses',
            "Buku periode \"{$closing->label}\" ditutup. Transaksi bertanggal {$closing->rentangTeks()} "
                .'sekarang terkunci — koreksi setelah ini dilakukan lewat transaksi penyesuaian bertanggal baru.'
        );
    }

    /** Membuka kembali closing terakhir. Yang di tengah ditolak service. */
    public function destroy(string $closing): RedirectResponse
    {
        $record = $this->cariClosing($closing);
        $label = $record->label;

        try {
            $this->tutupBuku->bukaKembali($record, $this->kelas());
        } catch (RuntimeException $e) {
            return back()->with('galat', $e->getMessage());
        }

        return redirect()->route('tutup-buku.index')->with(
            'sukses',
            "Tutup buku \"{$label}\" dibuka kembali. Transaksi di rentang itu bisa diubah lagi, "
                .'dan pembukaannya tercatat di audit log.'
        );
    }

    /** Laporan serah terima: ringkasan bertanda tangan untuk rapat kelas. */
    public function serahTerima(string $closing): Response
    {
        $record = $this->cariClosing($closing);

        $pdf = Pdf::loadView('tutup-buku.serah-terima', [
            'kelas' => $this->kelas(),
            'closing' => $record,
            'kategori' => $this->tutupBuku->rincianKategori($record->tgl_mulai, $record->tgl_selesai),
            'tunggakan' => $this->tutupBuku->tunggakanTerbuka($this->kelas()),
            'dicetakPada' => now(),
        ])->setPaper('a4');

        $namaBerkas = Str::slug('serah terima '.$this->kelas()->nama_kelas.' '.$record->label).'.pdf';

        return $pdf->download($namaBerkas);
    }

    /** Lewat relasi kelas aktif: ID closing kelas lain berakhir 404, bukan data orang lain. */
    protected function cariClosing(string $id): BookClosing
    {
        return BookClosing::query()->findOrFail($id);
    }

    /**
     * Rentang yang sedang dipratinjau.
     *
     * Bawaannya: sejak hari setelah closing terakhir sampai hari ini — rentang
     * yang paling mungkin ingin ditutup berikutnya, dan yang dijamin tidak
     * bertabrakan dengan closing yang sudah ada.
     *
     * @return array{0: string, 1: string}
     */
    protected function rentangPratinjau(Request $request): array
    {
        $data = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ], [], ['dari' => 'tanggal awal', 'sampai' => 'tanggal akhir']);

        $bawaanMulai = BookClosing::terakhir()?->tgl_selesai?->copy()->addDay()
            ?? $this->kelas()->periods()->min('tgl_mulai')
            ?? now()->startOfMonth();

        return [
            $data['dari'] ?? CarbonImmutable::parse($bawaanMulai)->toDateString(),
            $data['sampai'] ?? now()->toDateString(),
        ];
    }
}
