<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Classroom;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Period;
use App\Models\Student;
use App\Models\User;
use App\Services\KasService;
use App\Support\CurrentClassroom;
use App\Support\Uang;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membangun ulang kelas demo (PRD bagian 5).
 *
 * Kelas demo dipakai calon pengguna untuk melihat isi aplikasi sebelum
 * mendaftar. Datanya FIKTIF SELURUHNYA — nama-nama di bawah dikarang, dan
 * tidak boleh sekali pun diganti dengan data kelas sungguhan. Halaman demo
 * bisa dibuka siapa saja tanpa login; apa pun yang masuk ke sana sama dengan
 * dipublikasikan.
 *
 * Perintah ini menghapus lalu membangun ulang, bukan menambal. Demo yang
 * ditambal akan menumpuk pembayaran tiap kali direset sampai angkanya tidak
 * masuk akal lagi.
 */
class ResetDemoCommand extends Command
{
    protected $signature = 'kaskelas:reset-demo';

    protected $description = 'Membangun ulang kelas demo read-only dengan data contoh fiktif.';

    /** Nama karangan. Sengaja tidak diambil dari kelas mana pun. */
    protected const NAMA_SISWA = [
        'Adinda Rahmawati', 'Bagas Pratama', 'Citra Ayu Lestari', 'Dimas Nugroho',
        'Elsa Maharani', 'Fajar Ramadhan', 'Gita Permatasari', 'Hendra Wijaya',
        'Intan Cahyani', 'Joko Susilo', 'Kirana Dewi', 'Lukman Hakim',
        'Maya Anggraini', 'Naufal Ardiansyah', 'Oktaviani Putri', 'Putra Mahendra',
        'Qonita Salsabila', 'Rizky Firmansyah', 'Salma Nabila', 'Taufik Hidayat',
    ];

    public function handle(KasService $kas): int
    {
        if (! config('kaskelas.demo.aktif')) {
            $this->components->warn('Kelas demo dimatikan lewat config. Tidak ada yang dikerjakan.');

            return self::SUCCESS;
        }

        $kelas = CurrentClassroom::withoutTenancy(function () use ($kas) {
            $this->bersihkan();

            return $this->bangun($kas);
        });

        $this->components->info('Kelas demo dibangun ulang.');
        $this->components->twoColumnDetail('Nama kelas', $kelas->nama_kelas);
        $this->components->twoColumnDetail('Tautan publik', route('publik.kelas', $kelas->public_token));
        $this->components->twoColumnDetail('Halaman demo', route('demo'));

        return self::SUCCESS;
    }

    /** Menghapus demo lama beserta seluruh isinya. */
    protected function bersihkan(): void
    {
        $lama = Classroom::where('is_demo', true)->get();

        foreach ($lama as $kelas) {
            DB::transaction(function () use ($kelas) {
                foreach ([
                    'payment_allocations', 'payments', 'bills', 'expenses',
                    'campaigns', 'periods', 'students', 'expense_categories',
                    'book_closings', 'audit_logs', 'classroom_user',
                ] as $tabel) {
                    DB::table($tabel)->where('classroom_id', $kelas->id)->delete();
                }

                DB::table('classrooms')->where('id', $kelas->id)->delete();
            });
        }
    }

    protected function bangun(KasService $kas): Classroom
    {
        // Akun pemilik demo tidak pernah dipakai masuk: kata sandinya acak dan
        // tidak disimpan di mana pun. Demo hanya bisa dibuka lewat tautan publik.
        $pemilik = User::firstOrCreate(
            ['email' => 'demo@kaskelasku.my.id'],
            ['nama' => 'Bendahara Demo', 'password' => Str::random(64)],
        );

        // Akun pemilik demo sengaja DIMATIKAN dan tidak pernah diverifikasi:
        // ia hanya ada supaya kelas demo punya owner_id yang sah, dan tidak
        // boleh bisa dipakai masuk oleh siapa pun. Kata sandinya acak 64
        // karakter yang tidak pernah ditampilkan di mana pun.
        // forceFill, bukan mass assignment: 'is_active' sengaja di luar
        // $fillable supaya tidak pernah bisa datang dari sebuah form.
        $pemilik->forceFill(['is_active' => false, 'email_verified_at' => null])->save();

        $kelas = new Classroom([
            'nama_kelas' => config('kaskelas.demo.nama_kelas'),
            'sekolah' => config('kaskelas.demo.sekolah'),
            'tipe_periode' => 'bulanan',
        ]);

        $kelas->owner_id = $pemilik->id;
        $kelas->public_token = Classroom::generateToken();
        $kelas->persetujuan_data_at = now();
        $kelas->is_demo = true;
        $kelas->save();
        $kelas->refresh();

        // Auth::setUser, bukan login: perintah ini berjalan tanpa session.
        // Payment dan Expense mengisi created_by dari user yang sedang masuk —
        // aturan itu tidak dilonggarkan hanya karena pemanggilnya sebuah cron.
        // Data demo memang "dibuat oleh" bendahara demo, jadi inilah jawaban
        // yang jujur, bukan akal-akalan untuk melewati kolom NOT NULL.
        Auth::setUser($pemilik);

        try {
            return $this->isiDataContoh($kelas, $kas);
        } finally {
            Auth::forgetUser();
        }
    }

    /** Mengisi kelas demo dengan siswa, periode, pembayaran, belanja, dan satu campaign. */
    protected function isiDataContoh(Classroom $kelas, KasService $kas): Classroom
    {
        return CurrentClassroom::runFor($kelas, function () use ($kelas, $kas) {
            $mulai = CarbonImmutable::now()->startOfMonth()->subMonths(4);

            foreach (static::NAMA_SISWA as $i => $nama) {
                Student::create([
                    'nama' => $nama,
                    'no_absen' => $i + 1,
                    'tgl_mulai_aktif' => $mulai->toDateString(),
                ]);
            }

            // Empat bulan iuran @ Rp 10.000 sampai bulan berjalan.
            $kas->generatePeriode($kelas, $mulai, 10000, CarbonImmutable::now()->endOfMonth());

            foreach ($kelas->periods()->get() as $periode) {
                $kas->generateTagihanPeriode($periode);
            }

            $this->bayarSebagian($kas, $mulai);
            $this->belanjakan($mulai);
            $this->iuranInsidental($kas, $mulai);

            return $kelas;
        });
    }

    /**
     * Pola pembayaran yang mirip kelas sungguhan: sebagian besar lunas,
     * beberapa nyicil, beberapa belum bayar sama sekali.
     *
     * Demo yang semua siswanya lunas tidak menunjukkan apa pun — justru kolom
     * tunggakan yang paling ingin dilihat calon bendahara.
     */
    protected function bayarSebagian(KasService $kas, CarbonImmutable $mulai): void
    {
        $siswa = Student::urutAbsen()->get();

        // Dihitung dari periode yang benar-benar terbentuk, bukan dari selisih
        // tanggal. diffInMonths() mengembalikan pecahan, dan pecahan yang
        // dikalikan nominal menghasilkan angka seperti Rp 55.896,92 —
        // nominal yang mustahil ada di kas kelas mana pun.
        $bulanTertagih = Period::where('is_libur', false)->count();

        foreach ($siswa as $i => $murid) {
            // 4 dari 20 siswa menunggak penuh, 3 menyicil, sisanya lunas.
            $bulanDibayar = match (true) {
                $i % 5 === 4 => 0,
                $i % 7 === 3 => max(1, $bulanTertagih - 2),
                default => $bulanTertagih,
            };

            if ($bulanDibayar === 0) {
                continue;
            }

            Payment::create([
                'student_id' => $murid->id,
                'tanggal' => $mulai->addDays(5 + $i % 20)->toDateString(),
                'jumlah' => Uang::keDesimal($bulanDibayar * 10000 * 100),
                'metode' => $i % 3 === 0 ? 'transfer' : 'tunai',
                'catatan' => null,
            ]);

            $kas->alokasikanDeposit($murid->fresh());
        }
    }

    protected function belanjakan(CarbonImmutable $mulai): void
    {
        $kategori = ExpenseCategory::orderBy('id')->get();

        $belanja = [
            ['Spidol dan penghapus papan tulis', 45000, 10],
            ['Air galon kelas 2 bulan', 60000, 35],
            ['Perlengkapan kebersihan', 38000, 60],
            ['Konsumsi rapat kelas', 75000, 80],
        ];

        foreach ($belanja as $i => [$keterangan, $jumlah, $offsetHari]) {
            Expense::create([
                'tanggal' => $mulai->addDays($offsetHari)->toDateString(),
                'category_id' => $kategori[$i % max(1, $kategori->count())]->id ?? $kategori->first()->id,
                'jumlah' => Uang::keDesimal($jumlah * 100),
                'keterangan' => $keterangan,
            ]);
        }
    }

    /** Satu iuran insidental yang masih berjalan, supaya fiturnya terlihat. */
    protected function iuranInsidental(KasService $kas, CarbonImmutable $mulai): void
    {
        $campaign = Campaign::create([
            'nama' => 'Study Tour Kelas',
            'deskripsi' => 'Iuran tambahan untuk study tour akhir semester.',
            'nominal_per_siswa' => Uang::keDesimal(150000 * 100),
            'deadline' => CarbonImmutable::now()->addMonth()->toDateString(),
            'status' => 'aktif',
        ]);

        $peserta = Student::aktif()->pluck('id')->all();
        $kas->generateTagihanCampaign($campaign, $peserta);

        // Baru sebagian yang setor — itulah gunanya progres di halaman kelas.
        foreach (Student::urutAbsen()->take(8)->get() as $i => $murid) {
            Payment::create([
                'student_id' => $murid->id,
                'tanggal' => CarbonImmutable::now()->subDays(10 - $i)->toDateString(),
                'jumlah' => Uang::keDesimal(150000 * 100),
                'metode' => 'tunai',
                'catatan' => 'Setoran study tour',
            ]);

            $kas->alokasikanDeposit($murid->fresh());
        }
    }
}
