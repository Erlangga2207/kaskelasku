<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\ExpenseCategory;
use App\Models\Student;
use App\Models\User;
use App\Support\CurrentClassroom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sengaja membuat DUA kelas dari DUA sekolah berbeda.
 *
 * Satu kelas saja tidak membuktikan apa pun soal isolasi: global scope yang rusak
 * tetap terlihat benar kalau di database cuma ada satu kelas.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->kategoriBawaan();

            $kelasA = $this->buatKelas(
                bendahara: ['nama' => 'Erlangga Haryo', 'email' => 'bendahara@kaskelas.test'],
                kelas: ['nama_kelas' => 'XII TRPL 1', 'sekolah' => 'SMKN 1 Subang', 'tipe_periode' => 'bulanan'],
                siswa: ['Adinda Ayu Lestari', 'Bagas Pratama', 'Citra Maharani', 'Dimas Nugroho', 'Elvira Safitri'],
            );

            $kelasB = $this->buatKelas(
                bendahara: ['nama' => 'Siti Rahmawati', 'email' => 'bendahara-b@kaskelas.test'],
                kelas: ['nama_kelas' => 'XI IPA 3', 'sekolah' => 'SMAN 2 Bandung', 'tipe_periode' => 'mingguan'],
                siswa: ['Fajar Ramadhan', 'Gita Puspita', 'Haris Setiawan', 'Indah Permata', 'Joko Susilo'],
            );

            $this->command?->info("Kelas A: {$kelasA->nama_kelas} — token {$kelasA->public_token}");
            $this->command?->info("Kelas B: {$kelasB->nama_kelas} — token {$kelasB->public_token}");
            $this->command?->info('Kata sandi kedua bendahara: RahasiaKuat123');
        });
    }

    /** Kategori bawaan sistem: classroom_id NULL, dipakai bersama semua kelas. */
    protected function kategoriBawaan(): void
    {
        CurrentClassroom::withoutTenancy(function () {
            foreach (['Konsumsi', 'Alat Tulis', 'Kebersihan', 'Dekorasi Kelas', 'Kegiatan Kelas', 'Lain-lain'] as $nama) {
                if (ExpenseCategory::whereNull('classroom_id')->where('nama', $nama)->exists()) {
                    continue;
                }

                // classroom_id bukan kolom fillable — diisi eksplisit, tidak lewat mass assignment.
                $kategori = new ExpenseCategory(['nama' => $nama]);
                $kategori->classroom_id = null;
                $kategori->save();
            }
        });
    }

    protected function buatKelas(array $bendahara, array $kelas, array $siswa): Classroom
    {
        $user = User::create([
            'nama' => $bendahara['nama'],
            'email' => $bendahara['email'],
            'password' => 'RahasiaKuat123',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $classroom = CurrentClassroom::withoutTenancy(function () use ($kelas, $user) {
            $classroom = new Classroom($kelas);
            $classroom->owner_id = $user->id;
            $classroom->public_token = Classroom::generateToken();
            $classroom->persetujuan_data_at = now(); // v1: persetujuan dicatat lewat seeder
            $classroom->save();

            return $classroom;
        });

        $classroom->users()->attach($user->id, ['peran' => 'bendahara', 'created_at' => now()]);

        CurrentClassroom::runFor($classroom, function () use ($siswa) {
            foreach ($siswa as $urutan => $nama) {
                Student::create([
                    'nama' => $nama,
                    'no_absen' => $urutan + 1,
                    'tgl_mulai_aktif' => now()->startOfYear(),
                ]);
            }
        });

        return $classroom;
    }
}
