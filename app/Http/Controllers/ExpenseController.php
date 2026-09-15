<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\KasService;
use App\Support\Uang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(private readonly KasService $kas) {}

    public function index(Request $request): View
    {
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');

        $pengeluaran = $this->kelas()->expenses()
            ->with('category')
            ->when($dari, fn ($q) => $q->whereDate('tanggal', '>=', $dari))
            ->when($sampai, fn ($q) => $q->whereDate('tanggal', '<=', $sampai))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('pengeluaran.index', [
            'daftarPengeluaran' => $pengeluaran,
            'saldoKas' => $this->kas->saldoKas(),
            'dari' => $dari,
            'sampai' => $sampai,
        ]);
    }

    public function create(): View
    {
        return view('pengeluaran.form', [
            'pengeluaran' => new Expense(['tanggal' => now()->toDateString()]),
            'kategori' => $this->daftarKategori(),
            'saldoKas' => $this->kas->saldoKas(),
        ]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $expense = DB::transaction(function () use ($data, $request) {
            $expense = Expense::create([
                'tanggal' => $data['tanggal'],
                'category_id' => $data['category_id'],
                'jumlah' => Uang::keDesimal(Uang::keSen($data['jumlah'])),
                'keterangan' => $data['keterangan'],
            ]);

            if ($request->hasFile('bukti')) {
                $expense->forceFill([
                    'bukti_path' => $request->file('bukti')->store('kelas-'.$this->kelas()->id, 'local'),
                ])->save();
            }

            return $expense;
        });

        return redirect()->route('pengeluaran.index')->with(
            'sukses',
            'Pengeluaran '.Uang::format($expense->jumlah).' dicatat. Sisa saldo kas '
                .Uang::format(Uang::keDesimal($this->kas->saldoKas())).'.'
        );
    }

    public function edit(string $pengeluaran): View
    {
        return view('pengeluaran.form', [
            'pengeluaran' => $this->cariPengeluaran($pengeluaran),
            'kategori' => $this->daftarKategori(),
            'saldoKas' => $this->kas->saldoKas(),
        ]);
    }

    public function update(ExpenseRequest $request, string $pengeluaran): RedirectResponse
    {
        $expense = $this->cariPengeluaran($pengeluaran);
        $data = $request->validated();

        DB::transaction(function () use ($expense, $data, $request) {
            $expense->update([
                'tanggal' => $data['tanggal'],
                'category_id' => $data['category_id'],
                'jumlah' => Uang::keDesimal(Uang::keSen($data['jumlah'])),
                'keterangan' => $data['keterangan'],
            ]);

            if ($request->hasFile('bukti')) {
                $lama = $expense->bukti_path;
                $expense->forceFill([
                    'bukti_path' => $request->file('bukti')->store('kelas-'.$this->kelas()->id, 'local'),
                ])->save();

                if ($lama) {
                    Storage::disk('local')->delete($lama);
                }
            }
        });

        return redirect()->route('pengeluaran.index')->with('sukses', 'Pengeluaran diperbarui.');
    }

    public function destroy(string $pengeluaran): RedirectResponse
    {
        $expense = $this->cariPengeluaran($pengeluaran);
        $jumlah = Uang::format($expense->jumlah);

        // Soft delete: berkas bukti sengaja tidak ikut dihapus supaya jejaknya
        // masih bisa ditelusuri lewat audit log bila terjadi sengketa.
        $expense->delete();

        return redirect()->route('pengeluaran.index')->with('sukses', "Pengeluaran {$jumlah} dihapus.");
    }

    public function bukti(string $pengeluaran)
    {
        $expense = $this->cariPengeluaran($pengeluaran);

        abort_if($expense->bukti_path === null, 404);
        abort_unless(Storage::disk('local')->exists($expense->bukti_path), 404);

        return Storage::disk('local')->response(
            $expense->bukti_path,
            null,
            ['X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => 'inline'],
        );
    }

    protected function cariPengeluaran(string $id): Expense
    {
        return $this->kelas()->expenses()->findOrFail($id);
    }

    /** Kategori bawaan sistem + kategori buatan kelas ini. */
    protected function daftarKategori(): array
    {
        return ExpenseCategory::orderBy('nama')->get()
            ->mapWithKeys(fn (ExpenseCategory $k) => [
                $k->id => $k->nama.($k->isBawaan() ? '' : ' (kelas ini)'),
            ])
            ->all();
    }
}
