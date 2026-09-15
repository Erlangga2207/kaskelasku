<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Models\Student;
use App\Services\KasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(private readonly KasService $kas) {}

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari'));
        $status = $request->query('status', 'aktif');

        $siswa = $this->kelas()->students()
            ->when($cari !== '', fn ($q) => $q->where('nama', 'like', '%'.$cari.'%'))
            ->when($status === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($status === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->urutAbsen()
            ->paginate(25)
            ->withQueryString();

        return view('siswa.index', [
            'daftarSiswa' => $siswa,
            'cari' => $cari,
            'status' => $status,
            'jumlahAktif' => $this->kelas()->students()->where('is_active', true)->count(),
            'jumlahNonaktif' => $this->kelas()->students()->where('is_active', false)->count(),
        ]);
    }

    public function create(): View
    {
        return view('siswa.form', [
            'siswa' => new Student(['tgl_mulai_aktif' => now()->toDateString()]),
            'nomorBerikutnya' => (int) $this->kelas()->students()->max('no_absen') + 1,
        ]);
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $siswa = Student::create($request->validated());

        // Siswa baru di tengah tahun langsung mendapat tagihan periode yang
        // rentangnya masih mencakup tanggal mulai aktifnya.
        $tagihan = $this->kas->generateTagihanSiswa($siswa);

        return redirect()->route('siswa.index')->with(
            'sukses',
            "Siswa {$siswa->nama} ditambahkan".($tagihan > 0 ? " beserta {$tagihan} tagihan periode." : '.')
        );
    }

    public function edit(string $siswa): View
    {
        return view('siswa.form', [
            'siswa' => $this->cariSiswa($siswa),
            'nomorBerikutnya' => null,
        ]);
    }

    public function update(StudentRequest $request, string $siswa): RedirectResponse
    {
        $siswa = $this->cariSiswa($siswa);
        $siswa->update($request->validated());

        // Tanggal berhenti bisa berubah, jadi tagihan yang tidak lagi berlaku
        // dibuang — kecuali yang sudah menerima pembayaran.
        $dibuang = $this->kas->bersihkanTagihanTakBerlaku($siswa);
        $ditambah = $this->kas->generateTagihanSiswa($siswa);

        $catatan = array_filter([
            $ditambah > 0 ? "{$ditambah} tagihan ditambahkan" : null,
            $dibuang > 0 ? "{$dibuang} tagihan yang tidak berlaku dibuang" : null,
        ]);

        return redirect()->route('siswa.index')->with(
            'sukses',
            "Data {$siswa->nama} diperbarui".($catatan ? ' ('.implode(', ', $catatan).').' : '.')
        );
    }

    /** Menonaktifkan siswa. Penghapusan hanya untuk siswa yang belum punya jejak transaksi. */
    public function destroy(string $siswa): RedirectResponse
    {
        $siswa = $this->cariSiswa($siswa);

        if ($this->punyaTransaksi($siswa)) {
            $siswa->update(['is_active' => false, 'tgl_berhenti' => $siswa->tgl_berhenti ?? now()->toDateString()]);
            $this->kas->bersihkanTagihanTakBerlaku($siswa);

            return redirect()->route('siswa.index')->with(
                'peringatan',
                "{$siswa->nama} sudah punya transaksi, jadi dinonaktifkan — bukan dihapus. Riwayat pembayarannya tetap utuh."
            );
        }

        $nama = $siswa->nama;
        $siswa->bills()->delete();
        $siswa->delete();

        return redirect()->route('siswa.index')->with('sukses', "Siswa {$nama} dihapus.");
    }

    public function restore(string $siswa): RedirectResponse
    {
        $siswa = $this->cariSiswa($siswa);
        $siswa->update(['is_active' => true, 'tgl_berhenti' => null]);

        $ditambah = $this->kas->generateTagihanSiswa($siswa);

        return redirect()->route('siswa.index')->with(
            'sukses',
            "{$siswa->nama} diaktifkan kembali".($ditambah > 0 ? " dengan {$ditambah} tagihan baru." : '.')
        );
    }

    /** Pencarian lewat relasi kelas aktif: ID milik kelas lain otomatis 404. */
    protected function cariSiswa(string $id): Student
    {
        return $this->kelas()->students()->findOrFail($id);
    }

    protected function punyaTransaksi(Student $siswa): bool
    {
        return $siswa->payments()->withTrashed()->exists()
            || $siswa->bills()->has('allocations')->exists();
    }
}
