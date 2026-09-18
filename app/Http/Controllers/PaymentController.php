<?php

namespace App\Http\Controllers;

use App\Exceptions\PeriodeTerkunciException;
use App\Http\Requests\PaymentRequest;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\Student;
use App\Services\KasService;
use App\Support\Uang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(private readonly KasService $kas) {}

    public function index(Request $request): View
    {
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');

        $pembayaran = $this->kelas()->payments()
            ->with(['student', 'allocations'])
            ->when($dari, fn ($q) => $q->whereDate('tanggal', '>=', $dari))
            ->when($sampai, fn ($q) => $q->whereDate('tanggal', '<=', $sampai))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('pembayaran.index', [
            'daftarPembayaran' => $pembayaran,
            'dari' => $dari,
            'sampai' => $sampai,
            'kas' => $this->kas,
        ]);
    }

    public function create(Request $request): View
    {
        // Penjagaan "belum ada sumber tagihan" pindah ke middleware 'siap' di
        // v2.0. Bukan cuma karena rapi: penjagaan yang dulu ada di sini hanya
        // menutup satu route, sedangkan URL /pembayaran, /pengeluaran, dan
        // /laporan tetap bisa diketik langsung. Ia juga salah sejak ada iuran
        // insidental — kelas yang punya campaign hidup memang sudah punya
        // tagihan meski belum punya satu pun periode rutin.
        $siswa = $request->query('siswa')
            ? $this->kelas()->students()->find($request->query('siswa'))
            : null;

        // Urutannya tetap seperti yang dipakai alokasi otomatis (terlama dulu);
        // pengelompokan di bawah hanya memisah tampilannya, tidak mengubah urutan.
        $tagihan = $siswa ? $this->kas->tagihanBelumLunas($siswa) : collect();

        return view('pembayaran.form', [
            'daftarSiswa' => $this->kelas()->students()->aktif()->urutAbsen()->get(),
            'siswaTerpilih' => $siswa,
            'tagihan' => $tagihan,
            'tagihanRutin' => $tagihan->filter(fn (Bill $b) => $b->period_id !== null)->values(),
            // Satu campaign hanya pernah menerbitkan satu tagihan per siswa,
            // jadi daftar ini otomatis berisi satu baris per iuran insidental.
            'tagihanInsidental' => $tagihan->filter(fn (Bill $b) => $b->campaign_id !== null)->values(),
            'deposit' => $siswa ? $this->kas->depositSiswa($siswa) : 0,
            'kas' => $this->kas,
        ]);
    }

    public function store(PaymentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $payment = DB::transaction(function () use ($data, $request) {
                $payment = Payment::create([
                    'student_id' => $data['student_id'],
                    'tanggal' => $data['tanggal'],
                    'jumlah' => Uang::keDesimal(Uang::keSen($data['jumlah'])),
                    'metode' => $data['metode'],
                    'catatan' => $data['catatan'] ?? null,
                ]);

                if ($request->hasFile('bukti')) {
                    $payment->forceFill(['bukti_path' => $this->simpanBukti($request)])->save();
                }

                $rincian = array_filter($data['alokasi'] ?? [], fn ($v) => $v !== null && $v !== '');

                if (($data['mode_alokasi'] ?? 'otomatis') === 'manual') {
                    // Sengaja TIDAK memanggil alokasikanDeposit sesudahnya: sisa yang
                    // tidak dibagi bendahara memang harus mengendap sebagai deposit,
                    // bukan dilempar balik ke tagihan terlama oleh mesin otomatis.
                    $this->kas->alokasikanManual($payment, $rincian);
                } else {
                    $this->kas->alokasikanDeposit($payment->student);
                }

                return $payment;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('galat', $e->getMessage());
        }

        $deposit = $this->kas->depositSiswa($payment->student);

        return redirect()->route('pembayaran.index')->with(
            'sukses',
            'Pembayaran '.Uang::format($payment->jumlah).' dari '.$payment->student->nama.' dicatat.'
                .($deposit > 0 ? ' Sisa '.Uang::format(Uang::keDesimal($deposit)).' disimpan sebagai deposit dan otomatis dipakai untuk tagihan berikutnya.' : '')
        );
    }

    public function destroy(string $pembayaran): RedirectResponse
    {
        $payment = $this->cariPembayaran($pembayaran);
        $nama = $payment->student->nama;
        $jumlah = Uang::format($payment->jumlah);

        // Penolakannya datang dari model, bukan dari sini — controller hanya
        // menerjemahkannya jadi pesan, bukan jadi halaman galat 500.
        try {
            $this->kas->hapusPembayaran($payment);
        } catch (PeriodeTerkunciException $e) {
            return back()->with('galat', $e->getMessage());
        }

        return redirect()->route('pembayaran.index')->with(
            'sukses',
            "Pembayaran {$jumlah} dari {$nama} dihapus. Status tagihannya kembali seperti sebelum dibayar."
        );
    }

    /** Bukti transfer hanya bisa diambil lewat controller yang memeriksa kepemilikan kelas. */
    public function bukti(string $pembayaran)
    {
        $payment = $this->cariPembayaran($pembayaran);

        abort_if($payment->bukti_path === null, 404);
        abort_unless(Storage::disk('local')->exists($payment->bukti_path), 404);

        return Storage::disk('local')->response(
            $payment->bukti_path,
            null,
            ['X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => 'inline'],
        );
    }

    protected function cariPembayaran(string $id): Payment
    {
        return $this->kelas()->payments()->with('student')->findOrFail($id);
    }

    /**
     * Berkas disimpan di storage privat, dipisah per kelas, dengan nama acak.
     * Nama asli dari pengguna tidak pernah dipakai sebagai nama berkas.
     */
    protected function simpanBukti(Request $request): string
    {
        return $request->file('bukti')->store('kelas-'.$this->kelas()->id, 'local');
    }
}
