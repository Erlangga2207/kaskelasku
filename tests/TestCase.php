<?php

namespace Tests;

use App\Models\Classroom;
use App\Models\Period;
use App\Models\Student;
use App\Models\User;
use App\Services\KasService;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CurrentClassroom menyimpan state statis; tanpa ini kelas dari test
        // sebelumnya bisa bocor ke test berikutnya dan membuat hasilnya menyesatkan.
        CurrentClassroom::forget();
    }

    protected function tearDown(): void
    {
        CurrentClassroom::forget();

        parent::tearDown();
    }

    /**
     * Membuat satu kelas lengkap dengan bendaharanya.
     *
     * @return array{0: Classroom, 1: User}
     */
    protected function buatKelas(string $namaKelas = 'XII TRPL 1', string $sekolah = 'SMKN 1 Subang'): array
    {
        $user = User::create([
            'nama' => 'Bendahara '.$namaKelas,
            'email' => str()->uuid().'@kaskelas.test',
            'password' => 'RahasiaKuat123',
        ]);

        // Sejak v2.0 seluruh route bendahara menuntut email terverifikasi.
        // Helper ini mewakili bendahara yang SUDAH selesai mendaftar, jadi
        // verifikasinya diberikan di sini. Alur pendaftaran dan verifikasinya
        // sendiri diuji terpisah di PendaftaranTest.
        $user->forceFill(['email_verified_at' => now()])->save();

        $classroom = CurrentClassroom::withoutTenancy(function () use ($namaKelas, $sekolah, $user) {
            $classroom = new Classroom([
                'nama_kelas' => $namaKelas,
                'sekolah' => $sekolah,
                'tipe_periode' => 'bulanan',
            ]);
            $classroom->owner_id = $user->id;
            $classroom->public_token = Classroom::generateToken();
            $classroom->save();

            // refresh() supaya nilai default dari database (denda_aktif, status, dll)
            // ikut termuat — bukan null seperti pada objek yang baru dibuat.
            return $classroom->refresh();
        });

        $classroom->users()->attach($user->id, ['peran' => 'bendahara', 'created_at' => now()]);

        return [$classroom, $user];
    }

    /** Menjalankan pembuatan data di dalam konteks sebuah kelas. */
    protected function dalamKelas(Classroom $classroom, callable $callback): mixed
    {
        return CurrentClassroom::runFor($classroom, $callback);
    }

    /**
     * Membuat periode iuran lengkap dengan tagihannya, lewat KasService.
     *
     * Sejak v2.0 kelas tanpa satu pun sumber tagihan dipantulkan middleware
     * 'siap' ke wizard, jadi test yang menembak route Bayar/Keluar/Laporan
     * harus memakai kelas yang penyiapannya memang sudah selesai. Lewat service
     * yang sama dengan controller supaya aturan tahun ajaran tidak dipalsukan
     * di test.
     *
     * Dua langkah, persis seperti PeriodController: generatePeriode() hanya
     * melahirkan periodenya, tagihannya terbit lewat generateTagihanPeriode().
     * Kalau helper ini cuma memanggil yang pertama, ia akan diam-diam membuat
     * kelas berisi periode tanpa tagihan — keadaan yang justru jadi sumber bug
     * Fase 2 dulu.
     *
     * @return int Jumlah tagihan yang terbentuk.
     */
    protected function buatPeriode(
        Classroom $classroom,
        string $mulai = '2026-01-01',
        int|string $nominal = 5000,
        ?string $sampai = '2026-03-31',
    ): int {
        return $this->dalamKelas($classroom, function () use ($classroom, $mulai, $nominal, $sampai) {
            $kas = app(KasService::class);
            $sebelum = $classroom->periods()->pluck('id')->all();

            $kas->generatePeriode($classroom, $mulai, $nominal, $sampai);

            return $classroom->periods()
                ->whereNotIn('id', $sebelum)
                ->get()
                ->sum(fn (Period $periode) => $kas->generateTagihanPeriode($periode));
        });
    }

    protected function buatSiswa(Classroom $classroom, string $nama = 'Siswa Uji', array $atribut = []): Student
    {
        return $this->dalamKelas($classroom, fn () => Student::create(array_merge([
            'nama' => $nama,
            'no_absen' => 1,
            'tgl_mulai_aktif' => now()->startOfYear()->toDateString(),
        ], $atribut)));
    }
}
