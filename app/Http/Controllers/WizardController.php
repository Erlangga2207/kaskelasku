<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetCurrentClassroom;
use App\Http\Requests\BuatKelasRequest;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Services\KasService;
use App\Support\CurrentClassroom;
use App\Support\Kapasitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Wizard penyiapan kelas (PRD bagian 5).
 *
 * Urutannya dipaksa: buat kelas → input siswa → buat periode.
 *
 * Ini bukan kerapian tampilan. Tanpa periode tidak ada tagihan, dan tanpa
 * tagihan setiap pembayaran yang dicatat mendarat sebagai deposit menggantung:
 * uangnya tercatat masuk, tapi seluruh laporan tetap menunjukkan nol dan tidak
 * ada satu pun pesan galat yang muncul. Bug itu pernah terjadi sungguhan di
 * kelas pertama yang memakai aplikasi ini, dan baru ketahuan berminggu-minggu
 * kemudian saat rekapnya dicocokkan dengan uang di amplop.
 *
 * Langkah siswa dan periode sengaja TIDAK menulis datanya sendiri — formulirnya
 * mengirim ke endpoint yang sudah ada (siswa.massal.store dan periode.store).
 * Menyalin logikanya ke sini berarti punya dua tempat yang bisa berbeda diam-diam.
 */
class WizardController extends Controller
{
    public function __construct(private readonly KasService $kas) {}

    /*
    |--------------------------------------------------------------------------
    | Langkah 1 — buat kelas
    |--------------------------------------------------------------------------
    */

    public function buatKelas(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (Kapasitas::akunSudahPenuh($user)) {
            return redirect()->route('dashboard')->with(
                'peringatan',
                'Satu akun maksimal '.Kapasitas::batasKelasPerAkun().' kelas. '
                    .'Hapus kelas yang sudah tidak dipakai kalau mau membuat yang baru.'
            );
        }

        if (Kapasitas::kuotaSistemPenuh()) {
            return redirect()->route('daftar-tunggu');
        }

        return view('wizard.kelas', [
            'punyaKelasLain' => Kapasitas::jumlahKelasAkun($user) > 0,
        ]);
    }

    public function simpanKelas(BuatKelasRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (Kapasitas::akunSudahPenuh($user) || Kapasitas::kuotaSistemPenuh()) {
            return back()->with('galat', 'Kuota kelas sudah penuh, kelas baru tidak bisa dibuat sekarang.');
        }

        $data = $request->validated();

        $kelas = DB::transaction(function () use ($data, $user) {
            // withoutTenancy: kelas ini BELUM jadi kelas aktif, jadi global scope
            // belum punya acuan. Ini satu-satunya tempat pembuatan kelas.
            $kelas = CurrentClassroom::withoutTenancy(function () use ($data, $user) {
                $kelas = new Classroom([
                    'nama_kelas' => $data['nama_kelas'],
                    'sekolah' => $data['sekolah'],
                    'tipe_periode' => $data['tipe_periode'],
                ]);

                $kelas->owner_id = $user->id;
                $kelas->public_token = Classroom::generateToken();
                // Waktu persetujuan dicatat sebagai bukti, bukan sekadar boolean:
                // UU PDP menuntut bisa menunjukkan KAPAN persetujuan diberikan.
                $kelas->persetujuan_data_at = now();
                $kelas->save();

                return $kelas->refresh();
            });

            $kelas->users()->attach($user->id, ['peran' => 'bendahara', 'created_at' => now()]);

            AuditLog::catat(
                aksi: 'create',
                namaTabel: 'classrooms',
                recordId: $kelas->id,
                dataLama: null,
                dataBaru: ['nama_kelas' => $kelas->nama_kelas, 'sekolah' => $kelas->sekolah],
                classroomId: $kelas->id,
            );

            return $kelas;
        });

        // Kelas yang baru dibuat langsung jadi kelas aktif — kalau tidak,
        // langkah berikutnya akan mengisi siswa ke kelas yang salah.
        $request->session()->put(SetCurrentClassroom::SESSION_KEY, $kelas->id);

        return redirect()->route('wizard.siswa')->with(
            'sukses',
            "Kelas {$kelas->nama_kelas} dibuat. Sekarang masukkan daftar siswanya."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Langkah 2 — daftar siswa
    |--------------------------------------------------------------------------
    */

    public function siswa(): View|RedirectResponse
    {
        $kelas = $this->kelas();

        if ($kelas->punyaSiswa()) {
            return redirect()->route('wizard.periode');
        }

        return view('wizard.siswa', [
            'kelas' => $kelas,
            'batasSiswa' => Kapasitas::batasSiswaPerKelas(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Langkah 3 — periode iuran
    |--------------------------------------------------------------------------
    */

    public function periode(): View|RedirectResponse
    {
        $kelas = $this->kelas();

        if (! $kelas->punyaSiswa()) {
            return redirect()->route('wizard.siswa')->with(
                'peringatan',
                'Masukkan daftar siswanya dulu — periode yang dibuat sebelum ada siswa tidak menghasilkan tagihan apa pun.'
            );
        }

        if ($kelas->punyaPeriode()) {
            return redirect()->route('dashboard');
        }

        return view('wizard.periode', [
            'kelas' => $kelas,
            'jumlahSiswa' => $kelas->students()->count(),
            'akhirTahunAjaran' => $this->kas->akhirTahunAjaran(now()),
        ]);
    }
}
