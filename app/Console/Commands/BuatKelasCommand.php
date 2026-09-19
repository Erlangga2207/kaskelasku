<?php

namespace App\Console\Commands;

use App\Models\Classroom;
use App\Models\Period;
use App\Models\User;
use App\Services\KasService;
use App\Support\CurrentClassroom;
use App\Support\Uang;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Membuat satu kelas baru beserta bendaharanya lewat terminal.
 *
 * Sampai pendaftaran mandiri dibangun di v2.0, inilah satu-satunya cara resmi
 * menambah kelas di produksi. Seeder sengaja tidak disentuh: isinya data contoh
 * untuk pengembangan dan dua kelas lawan tanding test isolasi.
 */
class BuatKelasCommand extends Command
{
    protected $signature = 'kaskelas:buat-kelas';

    protected $description = 'Membuat kelas baru beserta akun bendahara, periode iuran, dan tagihannya';

    /** Batas percobaan per pertanyaan, supaya salah ketik tidak berujung loop tanpa akhir. */
    private const MAKS_PERCOBAAN = 3;

    public function handle(KasService $kas): int
    {
        $this->components->info('Membuat kelas baru. Tekan Ctrl+C kapan saja untuk membatalkan.');

        $bendahara = $this->tanyaBendahara();

        if ($bendahara === null) {
            return self::FAILURE;
        }

        $kelas = $this->tanyaKelas();

        if ($kelas === null) {
            return self::FAILURE;
        }

        $iuran = $this->tanyaIuran($kas, $kelas['tipe_periode']);

        if ($iuran === null) {
            return self::FAILURE;
        }

        // Satu transaksi untuk semuanya: kalau pembuatan periode gagal di tengah
        // jalan, akun bendahara tidak boleh tertinggal sebagai baris yatim.
        $hasil = DB::transaction(function () use ($bendahara, $kelas, $iuran, $kas) {
            $user = User::create([
                'nama' => $bendahara['nama'],
                'email' => $bendahara['email'],
                // Cast 'hashed' pada model User mengenali nilai yang sudah di-hash
                // dan meneruskannya apa adanya, jadi tidak terjadi hash ganda.
                'password' => Hash::make($bendahara['password']),
            ]);

            // Akun yang lahir dari perintah artisan dibuat oleh operator server,
            // bukan oleh pengunjung yang mengisi form. Alamat emailnya sudah
            // ditanggung orang yang mengetik perintah ini, jadi menahannya di
            // halaman verifikasi hanya menghalangi tanpa membuktikan apa pun.
            // Tidak lewat mass assignment: 'email_verified_at' sengaja tidak
            // masuk $fillable supaya tidak pernah bisa datang dari request.
            $user->markEmailAsVerified();

            $classroom = CurrentClassroom::withoutTenancy(function () use ($kelas, $user) {
                $classroom = new Classroom([
                    'nama_kelas' => $kelas['nama_kelas'],
                    'sekolah' => $kelas['sekolah'],
                    'tipe_periode' => $kelas['tipe_periode'],
                ]);

                $classroom->owner_id = $user->id;
                // generateToken() = Str::random(40) plus pemeriksaan tabrakan,
                // karena kolomnya UNIQUE lintas seluruh kelas.
                $classroom->public_token = Classroom::generateToken();
                $classroom->save();

                return $classroom->refresh();
            });

            $classroom->users()->attach($user->id, [
                'peran' => 'bendahara',
                'created_at' => now(),
            ]);

            // Periode dan tagihan memakai KasService yang sudah dipakai controller —
            // aturan tahun ajaran dan penentuan siswa aktif hanya hidup di satu tempat.
            [$jumlahPeriode, $jumlahTagihan] = CurrentClassroom::runFor($classroom, function () use ($kas, $classroom, $iuran) {
                $periode = $kas->generatePeriode(
                    $classroom,
                    $iuran['tgl_mulai'],
                    $iuran['nominal'],
                    $iuran['tgl_akhir'],
                );

                $tagihan = Period::urutWaktu()->get()
                    ->sum(fn (Period $p) => $kas->generateTagihanPeriode($p));

                return [$periode, $tagihan];
            });

            return compact('user', 'classroom', 'jumlahPeriode', 'jumlahTagihan');
        });

        $this->tampilkanRingkasan($hasil, $iuran);

        return self::SUCCESS;
    }

    /** @return array{nama: string, email: string, password: string}|null */
    private function tanyaBendahara(): ?array
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Bagian 1</>', '<fg=gray>Akun bendahara</>');

        $nama = $this->tanya(
            'Nama bendahara',
            ['required', 'string', 'max:100'],
            'nama',
        );

        if ($nama === null) {
            return null;
        }

        // unique:users,email menutup syarat "tolak kalau email sudah terdaftar".
        $email = $this->tanya(
            'Email bendahara',
            ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'email',
        );

        if ($email === null) {
            return null;
        }

        $password = $this->tanyaSandi();

        if ($password === null) {
            return null;
        }

        return ['nama' => $nama, 'email' => $email, 'password' => $password];
    }

    private function tanyaSandi(): ?string
    {
        for ($percobaan = 1; $percobaan <= self::MAKS_PERCOBAAN; $percobaan++) {
            $sandi = (string) $this->secret('Kata sandi (minimal 8 karakter)');
            $ulang = (string) $this->secret('Ulangi kata sandi');

            $validator = Validator::make(
                ['password' => $sandi, 'password_confirmation' => $ulang],
                ['password' => ['required', 'string', 'min:8', 'confirmed']],
                [],
                ['password' => 'kata sandi'],
            );

            if (! $validator->fails()) {
                return $sandi;
            }

            $this->galat($validator->errors()->first('password'), $percobaan);
        }

        return null;
    }

    /** @return array{nama_kelas: string, sekolah: string, tipe_periode: string}|null */
    private function tanyaKelas(): ?array
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Bagian 2</>', '<fg=gray>Identitas kelas</>');

        $namaKelas = $this->tanya(
            'Nama kelas (mis. XII TRPL 1)',
            ['required', 'string', 'max:100'],
            'nama kelas',
        );

        if ($namaKelas === null) {
            return null;
        }

        $sekolah = $this->tanya(
            'Nama sekolah',
            ['required', 'string', 'max:150'],
            'nama sekolah',
        );

        if ($sekolah === null) {
            return null;
        }

        // Tipe periode tidak bisa diubah lagi setelah ada transaksi, jadi pilihannya
        // dibatasi daftar tertutup — bukan teks bebas yang bisa salah ketik.
        $tipe = $this->choice('Tipe periode iuran', ['bulanan', 'mingguan'], 'bulanan');

        return ['nama_kelas' => $namaKelas, 'sekolah' => $sekolah, 'tipe_periode' => $tipe];
    }

    /** @return array{tgl_mulai: string, tgl_akhir: string, nominal: string}|null */
    private function tanyaIuran(KasService $kas, string $tipePeriode): ?array
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Bagian 3</>', '<fg=gray>Periode & nominal iuran</>');

        $tglMulai = $this->tanya(
            'Tanggal mulai iuran (YYYY-MM-DD)',
            ['required', 'date'],
            'tanggal mulai',
            now()->toDateString(),
        );

        if ($tglMulai === null) {
            return null;
        }

        $tglAkhir = $this->tanya(
            'Tanggal akhir tahun ajaran (YYYY-MM-DD)',
            ['required', 'date', 'after_or_equal:'.$tglMulai],
            'tanggal akhir tahun ajaran',
            $kas->akhirTahunAjaran($tglMulai)->toDateString(),
        );

        if ($tglAkhir === null) {
            return null;
        }

        $nominal = $this->tanya(
            'Nominal iuran per periode '.($tipePeriode === 'mingguan' ? '(per minggu)' : '(per bulan)'),
            ['required', 'numeric', 'min:0', 'max:9999999999'],
            'nominal iuran',
        );

        if ($nominal === null) {
            return null;
        }

        return ['tgl_mulai' => $tglMulai, 'tgl_akhir' => $tglAkhir, 'nominal' => $nominal];
    }

    /**
     * Bertanya sampai jawabannya lolos validasi.
     *
     * Ditanya ulang, bukan langsung gagal: kehilangan delapan jawaban sebelumnya
     * gara-gara satu salah ketik adalah cara tercepat membuat orang enggan memakai
     * perintah ini.
     *
     * @param  array<int, string>  $aturan
     */
    private function tanya(string $pertanyaan, array $aturan, string $label, ?string $bawaan = null): ?string
    {
        for ($percobaan = 1; $percobaan <= self::MAKS_PERCOBAAN; $percobaan++) {
            $jawaban = $this->ask($pertanyaan, $bawaan);

            $validator = Validator::make(
                ['nilai' => is_string($jawaban) ? trim($jawaban) : $jawaban],
                ['nilai' => $aturan],
                [],
                ['nilai' => $label],
            );

            if (! $validator->fails()) {
                return trim((string) $jawaban);
            }

            $this->galat($validator->errors()->first('nilai'), $percobaan);
        }

        return null;
    }

    private function galat(string $pesan, int $percobaan): void
    {
        $this->components->error($pesan);

        if ($percobaan >= self::MAKS_PERCOBAAN) {
            $this->components->warn('Sudah '.self::MAKS_PERCOBAAN.' kali salah. Perintah dibatalkan, tidak ada data yang tersimpan.');
        }
    }

    /**
     * @param  array{user: User, classroom: Classroom, jumlahPeriode: int, jumlahTagihan: int}  $hasil
     * @param  array{tgl_mulai: string, tgl_akhir: string, nominal: string}  $iuran
     */
    private function tampilkanRingkasan(array $hasil, array $iuran): void
    {
        /** @var Classroom $classroom */
        $classroom = $hasil['classroom'];

        $this->newLine();
        $this->components->info('Kelas berhasil dibuat.');

        $this->components->twoColumnDetail('<fg=green;options=bold>Kelas</>', $classroom->nama_kelas);
        $this->components->twoColumnDetail('Sekolah', $classroom->sekolah);
        $this->components->twoColumnDetail('Tipe periode', $classroom->tipe_periode);
        $this->components->twoColumnDetail('Bendahara', $hasil['user']->nama);
        $this->components->twoColumnDetail('Email', $hasil['user']->email);
        $this->components->twoColumnDetail('Nominal per periode', Uang::format($iuran['nominal']));
        $this->components->twoColumnDetail(
            'Rentang iuran',
            $iuran['tgl_mulai'].' s.d. '.$iuran['tgl_akhir'],
        );
        $this->components->twoColumnDetail('Periode dibuat', (string) $hasil['jumlahPeriode']);
        $this->components->twoColumnDetail('Tagihan dibuat', (string) $hasil['jumlahTagihan']);

        $this->newLine();
        $this->line('  <fg=gray>URL halaman kelas (bagikan ke grup kelas):</>');
        $this->line('  <fg=cyan;options=bold>'.route('publik.kelas', $classroom->public_token).'</>');

        $this->newLine();

        if ($hasil['jumlahTagihan'] === 0) {
            $this->components->warn(
                'Belum ada tagihan karena kelas ini belum punya siswa. '
                .'Masuk sebagai bendahara, tambahkan daftar siswa, dan tagihannya dibuat otomatis.'
            );
        }

        $this->components->bulletList([
            'Masuk di '.route('login').' memakai email dan kata sandi di atas.',
            'Tambahkan daftar siswa lewat menu Siswa → Tempel daftar nama.',
            'Persetujuan data siswa (UU PDP) belum tercatat — catat saat wali kelas menyetujuinya.',
        ]);
    }
}
