<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BookClosing;
use App\Models\Classroom;
use App\Models\Expense;
use App\Models\Payment;
use App\Support\CurrentClassroom;
use App\Support\Uang;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tutup buku & serah terima (PRD bagian 7 dan 10).
 *
 * Dipisah dari KasService dengan sengaja: KasService menjawab "berapa saldonya
 * SEKARANG", kelas ini menjawab "berapa saldonya WAKTU ITU, dan siapa yang
 * menandatanganinya". Yang pertama selalu dihitung ulang, yang kedua dibekukan.
 * Mencampur keduanya di satu kelas adalah cara tercepat membuat angka historis
 * ikut bergerak saat ada transaksi baru.
 *
 * Seluruh angka dihitung dalam sen (integer) lalu disimpan sebagai DECIMAL(12,2),
 * mengikuti aturan uang yang sama seperti di KasService.
 */
class TutupBukuService
{
    public function __construct(private readonly KasService $kas) {}

    /**
     * Menghitung snapshot sebuah rentang TANPA menyimpannya.
     *
     * Dipakai dua kali: untuk pratinjau sebelum bendahara menekan "Tutup Buku",
     * dan untuk test yang mencocokkan hasilnya dengan hitungan manual.
     *
     * @return array{saldo_awal: int, total_masuk: int, total_keluar: int, saldo_akhir: int}
     */
    public function hitung(CarbonInterface|string $dari, CarbonInterface|string $sampai): array
    {
        $mulai = CarbonImmutable::parse($dari)->toDateString();
        $selesai = CarbonImmutable::parse($sampai)->toDateString();

        // Saldo awal = seluruh mutasi SEBELUM rentang ini, bukan saldo closing
        // sebelumnya. Kalau diambil dari closing sebelumnya, satu closing yang
        // keliru akan diwariskan ke semua closing sesudahnya.
        $masukSebelum = $this->jumlahPembayaran(null, $this->hariSebelum($mulai));
        $keluarSebelum = $this->jumlahPengeluaran(null, $this->hariSebelum($mulai));

        $masuk = $this->jumlahPembayaran($mulai, $selesai);
        $keluar = $this->jumlahPengeluaran($mulai, $selesai);

        $saldoAwal = $masukSebelum - $keluarSebelum;

        return [
            'saldo_awal' => $saldoAwal,
            'total_masuk' => $masuk,
            'total_keluar' => $keluar,
            'saldo_akhir' => $saldoAwal + $masuk - $keluar,
        ];
    }

    /**
     * Menutup sebuah rentang: menghitung snapshot lalu menyimpannya.
     *
     * @throws RuntimeException bila rentangnya tidak masuk akal atau tumpang tindih
     */
    public function tutup(
        string $label,
        CarbonInterface|string $dari,
        CarbonInterface|string $sampai,
        ?string $catatan = null,
        ?Classroom $kelas = null,
    ): BookClosing {
        $kelas ??= CurrentClassroom::getOrFail();
        $mulai = CarbonImmutable::parse($dari)->startOfDay();
        $selesai = CarbonImmutable::parse($sampai)->startOfDay();

        if ($selesai->lessThan($mulai)) {
            throw new RuntimeException('Tanggal akhir tidak boleh mendahului tanggal mulai.');
        }

        if ($bentrok = $this->cariTumpangTindih($mulai, $selesai)) {
            throw new RuntimeException(
                'Rentang ini bertabrakan dengan tutup buku "'.$bentrok->label.'" ('
                .$bentrok->rentangTeks().'). Satu tanggal hanya boleh ditutup sekali.'
            );
        }

        return DB::transaction(function () use ($label, $mulai, $selesai, $catatan, $kelas) {
            $angka = $this->hitung($mulai, $selesai);

            $closing = new BookClosing([
                'label' => $label,
                'tgl_mulai' => $mulai->toDateString(),
                'tgl_selesai' => $selesai->toDateString(),
                'catatan' => $catatan,
            ]);

            $closing->forceFill([
                'saldo_awal' => Uang::keDesimal($angka['saldo_awal']),
                'total_masuk' => Uang::keDesimal($angka['total_masuk']),
                'total_keluar' => Uang::keDesimal($angka['total_keluar']),
                'saldo_akhir' => Uang::keDesimal($angka['saldo_akhir']),
                'closed_by' => auth()->id(),
                'closed_at' => now(),
            ])->save();

            AuditLog::catat(
                aksi: 'close_book',
                namaTabel: 'book_closings',
                recordId: $closing->id,
                dataLama: null,
                dataBaru: [
                    'label' => $closing->label,
                    'tgl_mulai' => $closing->tgl_mulai->toDateString(),
                    'tgl_selesai' => $closing->tgl_selesai->toDateString(),
                    'saldo_awal' => $closing->saldo_awal,
                    'total_masuk' => $closing->total_masuk,
                    'total_keluar' => $closing->total_keluar,
                    'saldo_akhir' => $closing->saldo_akhir,
                ],
                classroomId: $kelas->id,
            );

            return $closing;
        });
    }

    /**
     * Membuka kembali closing TERAKHIR.
     *
     * Hanya yang terakhir, karena membuka closing di tengah akan membuat seluruh
     * closing sesudahnya memuat saldo awal yang tidak lagi benar — dan angka itu
     * sudah telanjur ditandatangani di laporan serah terima.
     *
     * @throws RuntimeException bila yang diminta bukan closing terakhir
     */
    public function bukaKembali(BookClosing $closing, ?Classroom $kelas = null): void
    {
        $kelas ??= CurrentClassroom::getOrFail();

        if (! $closing->bolehDibuka()) {
            $terakhir = BookClosing::terakhir();

            throw new RuntimeException(
                'Hanya tutup buku terakhir yang bisa dibuka lagi. Yang terakhir sekarang adalah "'
                .$terakhir?->label.'" ('.$terakhir?->rentangTeks().'). Buka yang itu dulu kalau '
                .'memang harus mundur sampai ke "'.$closing->label.'".'
            );
        }

        DB::transaction(function () use ($closing, $kelas) {
            // Dicatat SEBELUM dihapus, supaya isinya masih lengkap saat disalin
            // ke audit log. Baris closing-nya hilang; jejaknya tidak.
            AuditLog::catat(
                aksi: 'reopen_book',
                namaTabel: 'book_closings',
                recordId: $closing->id,
                dataLama: [
                    'label' => $closing->label,
                    'tgl_mulai' => $closing->tgl_mulai->toDateString(),
                    'tgl_selesai' => $closing->tgl_selesai->toDateString(),
                    'saldo_akhir' => $closing->saldo_akhir,
                    'closed_by' => $closing->closed_by,
                ],
                dataBaru: null,
                classroomId: $kelas->id,
            );

            $closing->delete();
        });
    }

    /**
     * Rincian pengeluaran per kategori dalam sebuah rentang, untuk laporan
     * serah terima. Terbesar lebih dulu — yang paling perlu dijelaskan di rapat.
     *
     * @return Collection<int, array{nama: string, jumlah: int}>
     */
    public function rincianKategori(CarbonInterface|string $dari, CarbonInterface|string $sampai): Collection
    {
        $mulai = CarbonImmutable::parse($dari)->toDateString();
        $selesai = CarbonImmutable::parse($sampai)->toDateString();

        return Expense::with('category')
            ->whereDate('tanggal', '>=', $mulai)
            ->whereDate('tanggal', '<=', $selesai)
            ->get()
            ->groupBy(fn (Expense $e) => $e->category?->nama ?? 'Tanpa kategori')
            ->map(fn (Collection $baris, string $nama) => [
                'nama' => $nama,
                'jumlah' => $baris->sum(fn (Expense $e) => Uang::keSen($e->jumlah)),
            ])
            ->sortByDesc('jumlah')
            ->values();
    }

    /** Snapshot seluruh closing kelas ini, terbaru dulu. */
    public function riwayat(): Collection
    {
        return BookClosing::with('closedBy')->urutTerbaru()->get();
    }

    /**
     * Tunggakan yang masih terbuka pada saat serah terima.
     *
     * Diambil apa adanya dari KasService — bendahara baru perlu tahu piutang yang
     * ia warisi, dan angka itu tidak boleh punya versi kedua.
     */
    public function tunggakanTerbuka(?Classroom $kelas = null): Collection
    {
        return $this->kas->daftarTunggakan($kelas ?? CurrentClassroom::getOrFail());
    }

    protected function cariTumpangTindih(CarbonImmutable $mulai, CarbonImmutable $selesai): ?BookClosing
    {
        return BookClosing::query()
            ->whereDate('tgl_mulai', '<=', $selesai->toDateString())
            ->whereDate('tgl_selesai', '>=', $mulai->toDateString())
            ->first();
    }

    protected function hariSebelum(string $tanggal): string
    {
        return CarbonImmutable::parse($tanggal)->subDay()->toDateString();
    }

    protected function jumlahPembayaran(?string $dari, string $sampai): int
    {
        return Uang::keSen((string) Payment::query()
            ->when($dari, fn ($q) => $q->whereDate('tanggal', '>=', $dari))
            ->whereDate('tanggal', '<=', $sampai)
            ->sum('jumlah'));
    }

    protected function jumlahPengeluaran(?string $dari, string $sampai): int
    {
        return Uang::keSen((string) Expense::query()
            ->when($dari, fn ($q) => $q->whereDate('tanggal', '>=', $dari))
            ->whereDate('tanggal', '<=', $sampai)
            ->sum('jumlah'));
    }
}
