<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Campaign;
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
            ->with(['category', 'campaign'])
            ->when($dari, fn ($q) => $q->whereDate('tanggal', '>=', $dari))
            ->when($sampai, fn ($q) => $q->whereDate('tanggal', '<=', $sampai))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('pengeluaran.index', [
            'daftarPengeluaran' => $pengeluaran,
            'saldoKas' => $this->kas->saldoKas(),
            'saldoBebas' => $this->kas->saldoBebas(),
            'danaCampaign' => $this->kas->danaCampaignTertahan(),
            'dari' => $dari,
            'sampai' => $sampai,
        ]);
    }

    public function create(): View
    {
        return view('pengeluaran.form', [
            'pengeluaran' => new Expense(['tanggal' => now()->toDateString()]),
            'kategori' => $this->daftarKategori(),
            'campaign' => $this->daftarCampaign(),
            'saldoKas' => $this->kas->saldoKas(),
            'saldoBebas' => $this->kas->saldoBebas(),
            'kas' => $this->kas,
        ]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $expense = DB::transaction(function () use ($data, $request) {
            $expense = Expense::create([
                'tanggal' => $data['tanggal'],
                'category_id' => $data['category_id'],
                'campaign_id' => $data['campaign_id'] ?? null,
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

        $sisa = $expense->campaign_id
            ? 'Sisa dana campaign '.Uang::format(Uang::keDesimal($this->kas->sisaCampaign($expense->campaign))).'.'
            : 'Sisa saldo bebas '.Uang::format(Uang::keDesimal($this->kas->saldoBebas())).'.';

        return redirect()->route('pengeluaran.index')->with(
            'sukses',
            'Pengeluaran '.Uang::format($expense->jumlah).' dicatat. '.$sisa
        );
    }

    public function edit(string $pengeluaran): View
    {
        $expense = $this->cariPengeluaran($pengeluaran);

        return view('pengeluaran.form', [
            'pengeluaran' => $expense,
            'kategori' => $this->daftarKategori(),
            'campaign' => $this->daftarCampaign($expense),
            'saldoKas' => $this->kas->saldoKas(),
            'saldoBebas' => $this->kas->saldoBebas($expense),
            'kas' => $this->kas,
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
                'campaign_id' => $data['campaign_id'] ?? null,
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

    /**
     * Campaign yang masih boleh menerima pengeluaran.
     *
     * Campaign lama yang sudah dibatalkan tidak muncul, kecuali memang sedang
     * menempel pada pengeluaran yang sedang diubah — supaya riwayatnya tidak
     * hilang diam-diam dari formulir.
     */
    protected function daftarCampaign(?Expense $kecuali = null): array
    {
        return Campaign::where(function ($query) use ($kecuali) {
            $query->berjalan();

            if ($kecuali?->campaign_id) {
                // Dibungkus closure supaya "or" ini tidak pernah lolos dari
                // filter classroom_id milik global scope.
                $query->orWhere('id', $kecuali->campaign_id);
            }
        })
            ->urutBaru()
            ->get()
            ->mapWithKeys(fn (Campaign $c) => [$c->id => $c->nama])
            ->all();
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
