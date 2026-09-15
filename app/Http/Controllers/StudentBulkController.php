<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\KasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Menambah banyak siswa sekaligus dengan menempel daftar nama.
 *
 * Bendahara biasanya sudah punya daftar absen di grup kelas atau di Excel;
 * memaksa mengetik satu per satu 30 kali adalah cara tercepat membuat aplikasi
 * ini ditinggalkan.
 */
class StudentBulkController extends Controller
{
    public function __construct(private readonly KasService $kas) {}

    public function create(): View
    {
        return view('siswa.massal', [
            'nomorBerikutnya' => (int) $this->kelas()->students()->max('no_absen') + 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'daftar' => ['required', 'string', 'max:6000'],
            'tgl_mulai_aktif' => ['required', 'date'],
        ], [], ['daftar' => 'daftar nama']);

        $namaAda = $this->kelas()->students()
            ->pluck('nama')
            ->map(fn ($n) => mb_strtolower($n))
            ->all();

        $nomorTerpakai = $this->kelas()->students()->whereNotNull('no_absen')->pluck('no_absen')->all();
        $nomorBerikutnya = (int) ($this->kelas()->students()->max('no_absen') ?? 0);

        $baris = $this->uraikan($data['daftar']);

        if ($baris === []) {
            return back()->withInput()->with('galat', 'Tidak ada nama yang bisa dibaca dari daftar itu.');
        }

        $dibuat = 0;
        $dilewati = [];

        DB::transaction(function () use ($baris, $namaAda, &$nomorTerpakai, &$nomorBerikutnya, $data, &$dibuat, &$dilewati) {
            foreach ($baris as $item) {
                if (in_array(mb_strtolower($item['nama']), $namaAda, true)) {
                    $dilewati[] = $item['nama'];

                    continue;
                }

                $noAbsen = $item['no_absen'];

                if ($noAbsen === null || in_array($noAbsen, $nomorTerpakai, true)) {
                    $noAbsen = ++$nomorBerikutnya;
                }

                $nomorTerpakai[] = $noAbsen;
                $nomorBerikutnya = max($nomorBerikutnya, $noAbsen);
                $namaAda[] = mb_strtolower($item['nama']);

                $siswa = Student::create([
                    'nama' => $item['nama'],
                    'no_absen' => $noAbsen,
                    'tgl_mulai_aktif' => $data['tgl_mulai_aktif'],
                ]);

                $this->kas->generateTagihanSiswa($siswa);
                $dibuat++;
            }
        });

        $pesan = "{$dibuat} siswa ditambahkan.";

        if ($dilewati !== []) {
            $pesan .= ' Dilewati karena namanya sudah ada: '.implode(', ', array_slice($dilewati, 0, 5))
                .(count($dilewati) > 5 ? ' dan '.(count($dilewati) - 5).' lainnya' : '').'.';
        }

        return redirect()->route('siswa.index')->with($dibuat > 0 ? 'sukses' : 'peringatan', $pesan);
    }

    /**
     * Menerima bentuk daftar yang lazim: "1. Budi", "1 Budi", "1) Budi", atau "Budi".
     *
     * @return array<int, array{nama: string, no_absen: int|null}>
     */
    protected function uraikan(string $teks): array
    {
        $hasil = [];

        foreach (preg_split('/\r\n|\r|\n/', $teks) as $baris) {
            $baris = trim(str_replace("\t", ' ', $baris));

            if ($baris === '') {
                continue;
            }

            $noAbsen = null;

            if (preg_match('/^(\d{1,3})\s*[.)\-:]?\s+(.+)$/u', $baris, $cocok)) {
                $noAbsen = (int) $cocok[1];
                $baris = $cocok[2];
            }

            $nama = trim(preg_replace('/\s+/u', ' ', $baris));

            if ($nama === '' || mb_strlen($nama) > 100) {
                continue;
            }

            $hasil[] = ['nama' => $nama, 'no_absen' => $noAbsen && $noAbsen <= 200 ? $noAbsen : null];
        }

        return $hasil;
    }
}
