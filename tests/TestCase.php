<?php

namespace Tests;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
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

    protected function buatSiswa(Classroom $classroom, string $nama = 'Siswa Uji', array $atribut = []): Student
    {
        return $this->dalamKelas($classroom, fn () => Student::create(array_merge([
            'nama' => $nama,
            'no_absen' => 1,
            'tgl_mulai_aktif' => now()->startOfYear()->toDateString(),
        ], $atribut)));
    }
}
