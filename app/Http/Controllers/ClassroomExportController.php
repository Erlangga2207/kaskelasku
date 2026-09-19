<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\BookClosing;
use App\Models\Campaign;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Period;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor seluruh data kelas ke CSV (PRD bagian 5).
 *
 * Ini bukan fitur tambahan, ini jalan keluar. Syarat Layanan menyebut layanan
 * ini gratis dan apa adanya; janji seperti itu hanya jujur kalau datanya benar-
 * benar bisa dibawa pergi kapan saja, tanpa minta izin dan tanpa menunggu.
 *
 * Satu berkas per jenis data, bukan satu CSV raksasa bersekat: CSV bersekat
 * terlihat rapi di editor teks tapi berantakan begitu dibuka di Excel, dan
 * Excel-lah yang akan dipakai bendahara.
 */
class ClassroomExportController extends Controller
{
    /** Kolom tiap jenis ekspor + cara mengambil barisnya. */
    protected const JENIS = [
        'siswa', 'periode', 'tagihan', 'pembayaran', 'alokasi',
        'pengeluaran', 'campaign', 'tutup-buku',
    ];

    public function index(): View
    {
        return view('ekspor.index', [
            'kelas' => $this->kelas(),
            'jenis' => static::JENIS,
            'jumlah' => [
                'siswa' => Student::count(),
                'periode' => Period::count(),
                'tagihan' => Bill::count(),
                'pembayaran' => Payment::count(),
                'alokasi' => PaymentAllocation::count(),
                'pengeluaran' => Expense::count(),
                'campaign' => Campaign::count(),
                'tutup-buku' => BookClosing::count(),
            ],
        ]);
    }

    public function unduh(string $jenis): StreamedResponse|RedirectResponse
    {
        if (! in_array($jenis, static::JENIS, true)) {
            abort(404);
        }

        [$judul, $baris] = $this->data($jenis);

        $namaBerkas = Str::slug($this->kelas()->nama_kelas.' '.$jenis.' '.now()->format('Y-m-d')).'.csv';

        return response()->streamDownload(function () use ($judul, $baris) {
            $keluaran = fopen('php://output', 'w');

            // BOM UTF-8: tanpa ini Excel di Windows membaca "Añisa" jadi "AÃ±isa".
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, $judul);

            foreach ($baris as $satu) {
                fputcsv($keluaran, $satu);
            }

            fclose($keluaran);
        }, $namaBerkas, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Seluruh query di bawah lewat model bertrait BelongsToClassroom, jadi
     * global scope yang membatasinya ke kelas aktif — bukan filter manual yang
     * bisa terlupa di salah satu cabang.
     *
     * @return array{0: array<int, string>, 1: iterable<int, array<int, string|int|null>>}
     */
    protected function data(string $jenis): array
    {
        return match ($jenis) {
            'siswa' => [
                ['id', 'no_absen', 'nama', 'tgl_mulai_aktif', 'tgl_berhenti', 'aktif'],
                Student::orderBy('no_absen')->get()->map(fn (Student $s) => [
                    $s->id, $s->no_absen, $s->nama,
                    $s->tgl_mulai_aktif?->toDateString(),
                    $s->tgl_berhenti?->toDateString(),
                    $s->is_active ? 'ya' : 'tidak',
                ]),
            ],

            'periode' => [
                ['id', 'label', 'tipe', 'tgl_mulai', 'tgl_selesai', 'jatuh_tempo', 'nominal', 'libur'],
                Period::orderBy('tgl_mulai')->get()->map(fn (Period $p) => [
                    $p->id, $p->label, $p->tipe,
                    $p->tgl_mulai?->toDateString(), $p->tgl_selesai?->toDateString(),
                    $p->jatuh_tempo?->toDateString(), $p->nominal,
                    $p->is_libur ? 'ya' : 'tidak',
                ]),
            ],

            'tagihan' => [
                ['id', 'siswa_id', 'siswa', 'periode', 'campaign', 'nominal', 'dibebaskan', 'alasan_bebas'],
                Bill::with(['student', 'period', 'campaign'])->orderBy('id')->get()->map(fn (Bill $b) => [
                    $b->id, $b->student_id, $b->student?->nama,
                    $b->period?->label, $b->campaign?->nama,
                    $b->nominal, $b->is_bebas ? 'ya' : 'tidak', $b->alasan_bebas,
                ]),
            ],

            'pembayaran' => [
                ['id', 'tanggal', 'siswa_id', 'siswa', 'jumlah', 'metode', 'catatan'],
                Payment::with('student')->orderBy('tanggal')->orderBy('id')->get()->map(fn (Payment $p) => [
                    $p->id, $p->tanggal?->toDateString(), $p->student_id, $p->student?->nama,
                    $p->jumlah, $p->metode, $p->catatan,
                ]),
            ],

            'alokasi' => [
                ['id', 'payment_id', 'bill_id', 'jumlah'],
                PaymentAllocation::orderBy('id')->get()->map(fn (PaymentAllocation $a) => [
                    $a->id, $a->payment_id, $a->bill_id, $a->jumlah,
                ]),
            ],

            'pengeluaran' => [
                ['id', 'tanggal', 'kategori', 'campaign', 'jumlah', 'keterangan'],
                Expense::with(['category', 'campaign'])->orderBy('tanggal')->orderBy('id')->get()->map(fn (Expense $e) => [
                    $e->id, $e->tanggal?->toDateString(), $e->category?->nama, $e->campaign?->nama,
                    $e->jumlah, $e->keterangan,
                ]),
            ],

            'campaign' => [
                ['id', 'nama', 'deskripsi', 'nominal_per_siswa', 'deadline', 'status'],
                Campaign::orderBy('id')->get()->map(fn (Campaign $c) => [
                    $c->id, $c->nama, $c->deskripsi, $c->nominal_per_siswa,
                    $c->deadline?->toDateString(), $c->status,
                ]),
            ],

            default => [
                ['id', 'label', 'tgl_mulai', 'tgl_selesai', 'saldo_awal', 'total_masuk', 'total_keluar', 'saldo_akhir', 'ditutup_pada'],
                BookClosing::orderBy('tgl_mulai')->get()->map(fn (BookClosing $c) => [
                    $c->id, $c->label,
                    $c->tgl_mulai?->toDateString(), $c->tgl_selesai?->toDateString(),
                    $c->saldo_awal, $c->total_masuk, $c->total_keluar, $c->saldo_akhir,
                    $c->closed_at?->toDateTimeString(),
                ]),
            ],
        };
    }
}
