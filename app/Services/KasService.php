<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Classroom;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Period;
use App\Models\Student;
use App\Support\CurrentClassroom;
use App\Support\Uang;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Semua logika uang kas kelas berkumpul di sini: pembuatan periode & tagihan,
 * alokasi pembayaran, denda, dan saldo.
 *
 * Saldo dan status lunas TIDAK PERNAH disimpan sebagai kolom — selalu dihitung
 * ulang dari payments, payment_allocations, dan expenses.
 */
class KasService
{
    /*
    |--------------------------------------------------------------------------
    | Periode
    |--------------------------------------------------------------------------
    */

    /**
     * Tahun ajaran Indonesia berjalan Juli sampai Juni.
     * Jadi kelas yang mulai Januari 2026 berakhir 30 Juni 2026, sedangkan yang
     * mulai September 2026 berakhir 30 Juni 2027.
     */
    public function akhirTahunAjaran(CarbonInterface|string $mulai): CarbonImmutable
    {
        $mulai = CarbonImmutable::parse($mulai);
        $tahun = $mulai->month >= 7 ? $mulai->year + 1 : $mulai->year;

        return CarbonImmutable::create($tahun, 6, 30)->startOfDay();
    }

    /**
     * Membuat periode berurutan sampai akhir tahun ajaran.
     * Periode yang tanggal mulainya sudah ada dilewati, sehingga aman dipanggil ulang.
     *
     * @return int jumlah periode baru yang dibuat
     */
    public function generatePeriode(Classroom $kelas, CarbonInterface|string $mulai, string|int $nominal, CarbonInterface|string|null $sampai = null): int
    {
        $mulai = CarbonImmutable::parse($mulai)->startOfDay();
        $sampai = CarbonImmutable::parse($sampai ?? $this->akhirTahunAjaran($mulai))->startOfDay();

        if ($sampai->lt($mulai)) {
            throw new RuntimeException('Tanggal akhir periode mendahului tanggal mulai.');
        }

        $nominal = Uang::keDesimal(Uang::keSen($nominal));

        $rentang = $kelas->tipe_periode === 'mingguan'
            ? $this->rentangMingguan($mulai, $sampai)
            : $this->rentangBulanan($mulai, $sampai);

        $sudahAda = Period::pluck('tgl_mulai')
            ->map(fn ($t) => CarbonImmutable::parse($t)->toDateString())
            ->all();

        $dibuat = 0;

        DB::transaction(function () use ($rentang, $sudahAda, $nominal, $kelas, &$dibuat) {
            foreach ($rentang as $bagian) {
                if (in_array($bagian['tgl_mulai']->toDateString(), $sudahAda, true)) {
                    continue;
                }

                Period::create([
                    'label' => $bagian['label'],
                    'tipe' => $kelas->tipe_periode,
                    'tgl_mulai' => $bagian['tgl_mulai']->toDateString(),
                    'tgl_selesai' => $bagian['tgl_selesai']->toDateString(),
                    // Jatuh tempo = hari terakhir periode. Denda baru berjalan
                    // setelah masa tenggang, jadi tidak perlu tanggal terpisah.
                    'jatuh_tempo' => $bagian['tgl_selesai']->toDateString(),
                    'nominal' => $nominal,
                    'is_libur' => false,
                ]);

                $dibuat++;
            }
        });

        return $dibuat;
    }

    /** @return array<int, array{label: string, tgl_mulai: CarbonImmutable, tgl_selesai: CarbonImmutable}> */
    protected function rentangBulanan(CarbonImmutable $mulai, CarbonImmutable $sampai): array
    {
        $hasil = [];
        $kursor = $mulai->startOfMonth();

        while ($kursor->lte($sampai)) {
            $akhir = $kursor->endOfMonth()->startOfDay();

            $hasil[] = [
                'label' => $kursor->translatedFormat('F Y'),
                // Periode pertama dimulai pada tanggal kelas dibuat, bukan tanggal 1,
                // supaya tidak menagih bulan yang sudah lewat sebagian.
                'tgl_mulai' => $kursor->lt($mulai) ? $mulai : $kursor,
                'tgl_selesai' => $akhir->gt($sampai) ? $sampai : $akhir,
            ];

            $kursor = $kursor->addMonth()->startOfMonth();
        }

        return $hasil;
    }

    /** @return array<int, array{label: string, tgl_mulai: CarbonImmutable, tgl_selesai: CarbonImmutable}> */
    protected function rentangMingguan(CarbonImmutable $mulai, CarbonImmutable $sampai): array
    {
        $hasil = [];
        $kursor = $mulai->startOfWeek(CarbonInterface::MONDAY);

        while ($kursor->lte($sampai)) {
            $akhir = $kursor->endOfWeek(CarbonInterface::SUNDAY)->startOfDay();
            $awalEfektif = $kursor->lt($mulai) ? $mulai : $kursor;
            $akhirEfektif = $akhir->gt($sampai) ? $sampai : $akhir;

            $hasil[] = [
                'label' => $awalEfektif->translatedFormat('j M').' – '.$akhirEfektif->translatedFormat('j M Y'),
                'tgl_mulai' => $awalEfektif,
                'tgl_selesai' => $akhirEfektif,
            ];

            $kursor = $kursor->addWeek();
        }

        return $hasil;
    }

    /*
    |--------------------------------------------------------------------------
    | Tagihan
    |--------------------------------------------------------------------------
    */

    /**
     * Membuat tagihan satu periode untuk semua siswa yang aktif pada rentangnya.
     * Periode libur tidak menghasilkan tagihan sama sekali.
     */
    public function generateTagihanPeriode(Period $period): int
    {
        if ($period->is_libur) {
            return 0;
        }

        $sudahPunya = Bill::where('period_id', $period->id)->pluck('student_id')->all();

        $dibuat = 0;

        DB::transaction(function () use ($period, $sudahPunya, &$dibuat) {
            Student::aktif()->get()->each(function (Student $siswa) use ($period, $sudahPunya, &$dibuat) {
                if (in_array($siswa->id, $sudahPunya) || ! $siswa->aktifPadaPeriode($period)) {
                    return;
                }

                Bill::create([
                    'student_id' => $siswa->id,
                    'period_id' => $period->id,
                    'nominal' => $period->nominal,
                ]);

                $dibuat++;

                // Kelebihan bayar periode lalu langsung menutup tagihan baru ini.
                $this->alokasikanDeposit($siswa);
            });
        });

        return $dibuat;
    }

    /** Dipakai saat siswa baru masuk di tengah tahun. */
    public function generateTagihanSiswa(Student $siswa): int
    {
        if (! $siswa->is_active) {
            return 0;
        }

        $sudahPunya = Bill::where('student_id', $siswa->id)->pluck('period_id')->all();

        $dibuat = 0;

        DB::transaction(function () use ($siswa, $sudahPunya, &$dibuat) {
            Period::where('is_libur', false)->get()->each(function (Period $period) use ($siswa, $sudahPunya, &$dibuat) {
                if (in_array($period->id, $sudahPunya) || ! $siswa->aktifPadaPeriode($period)) {
                    return;
                }

                Bill::create([
                    'student_id' => $siswa->id,
                    'period_id' => $period->id,
                    'nominal' => $period->nominal,
                ]);

                $dibuat++;
            });

            if ($dibuat > 0) {
                $this->alokasikanDeposit($siswa);
            }
        });

        return $dibuat;
    }

    /**
     * Membuang tagihan yang seharusnya tidak ada lagi — misalnya setelah siswa
     * diberi tanggal berhenti, atau periode ditandai libur.
     *
     * Tagihan yang sudah menerima pembayaran TIDAK pernah dibuang: uangnya nyata,
     * dan menghapus tagihannya akan membuat saldo tidak bisa dipertanggungjawabkan.
     */
    public function bersihkanTagihanTakBerlaku(Student $siswa): int
    {
        $terhapus = 0;

        DB::transaction(function () use ($siswa, &$terhapus) {
            Bill::where('student_id', $siswa->id)
                ->whereNotNull('period_id')
                ->with('period')
                ->doesntHave('allocations')
                ->get()
                ->each(function (Bill $bill) use ($siswa, &$terhapus) {
                    if ($bill->period && $siswa->aktifPadaPeriode($bill->period) && ! $bill->period->is_libur) {
                        return;
                    }

                    $bill->delete();
                    $terhapus++;
                });
        });

        return $terhapus;
    }

    /** Menghapus seluruh tagihan sebuah periode. Gagal bila ada yang sudah dibayar. */
    public function hapusTagihanPeriode(Period $period): int
    {
        if ($this->periodePunyaPembayaran($period)) {
            throw new RuntimeException('Periode ini sudah menerima pembayaran, tagihannya tidak boleh dihapus.');
        }

        return Bill::where('period_id', $period->id)->get()->each->delete()->count();
    }

    public function periodePunyaPembayaran(Period $period): bool
    {
        return Bill::where('period_id', $period->id)->has('allocations')->exists();
    }

    /**
     * Menyelaraskan nominal tagihan setelah nominal periode diubah.
     * Hanya periode ini yang terpengaruh — periode lain tidak ikut berubah.
     */
    public function selaraskanNominalTagihan(Period $period): int
    {
        return Bill::where('period_id', $period->id)
            ->where('is_bebas', false)
            ->get()
            ->filter(fn (Bill $bill) => Uang::keSen($bill->nominal) !== Uang::keSen($period->nominal))
            ->each(fn (Bill $bill) => $bill->update(['nominal' => $period->nominal]))
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Perhitungan (PRD bagian 12)
    |--------------------------------------------------------------------------
    | Semua nilai dikembalikan dalam satuan SEN agar bebas dari galat pembulatan.
    | Pemanggil memakai Uang::format() atau Uang::keDesimal() saat menampilkan
    | atau menyimpannya.
    */

    /** SUM(payment_allocations.jumlah) untuk satu tagihan. */
    public function dibayarTagihan(Bill $bill): int
    {
        $alokasi = $bill->relationLoaded('allocations')
            ? $bill->allocations
            : $bill->allocations()->get();

        return $alokasi->sum(fn (PaymentAllocation $a) => Uang::keSen($a->jumlah));
    }

    /** Sisa yang masih harus dibayar. Tagihan bebas selalu 0. */
    public function sisaTagihan(Bill $bill): int
    {
        if ($bill->is_bebas) {
            return 0;
        }

        return max(0, Uang::keSen($bill->nominal) - $this->dibayarTagihan($bill));
    }

    /** belum | kurang | lunas | bebas — dihitung, tidak pernah disimpan sebagai kolom. */
    public function statusTagihan(Bill $bill): string
    {
        if ($bill->is_bebas) {
            return 'bebas';
        }

        $dibayar = $this->dibayarTagihan($bill);
        $nominal = Uang::keSen($bill->nominal);

        return match (true) {
            $dibayar <= 0 => 'belum',
            $dibayar >= $nominal => 'lunas',
            default => 'kurang',
        };
    }

    /** Saldo deposit siswa = uang yang sudah diterima tapi belum dialokasikan. */
    public function depositSiswa(Student $siswa): int
    {
        $diterima = Uang::keSen((string) Payment::where('student_id', $siswa->id)->sum('jumlah'));

        $teralokasi = Uang::keSen((string) PaymentAllocation::whereIn(
            'payment_id',
            Payment::where('student_id', $siswa->id)->select('id')
        )->sum('jumlah'));

        return max(0, $diterima - $teralokasi);
    }

    /** Saldo kas kelas = seluruh pembayaran dikurangi seluruh pengeluaran. */
    public function saldoKas(): int
    {
        return Uang::keSen((string) Payment::sum('jumlah'))
            - Uang::keSen((string) Expense::sum('jumlah'));
    }

    public function totalMasuk(): int
    {
        return Uang::keSen((string) Payment::sum('jumlah'));
    }

    public function totalKeluar(): int
    {
        return Uang::keSen((string) Expense::sum('jumlah'));
    }

    /**
     * Denda satu tagihan.
     *
     * Dihitung saat ditampilkan, bukan disimpan lewat cron: kalau disimpan,
     * mengubah pengaturan denda akan meninggalkan angka lama yang salah.
     */
    public function dendaTagihan(Bill $bill, ?Classroom $kelas = null, ?CarbonInterface $per = null): int
    {
        $kelas ??= CurrentClassroom::getOrFail();

        if (! $kelas->denda_aktif || $bill->is_bebas || $bill->period === null) {
            return 0;
        }

        if ($this->sisaTagihan($bill) <= 0) {
            return 0;
        }

        $per = CarbonImmutable::parse($per ?? now())->startOfDay();
        $jatuhTempo = CarbonImmutable::parse($bill->period->jatuh_tempo)->startOfDay();
        $hariTelat = $jatuhTempo->diffInDays($per, false);

        if ($hariTelat <= $kelas->grace_days) {
            return 0;
        }

        $nominalDenda = Uang::keSen($kelas->denda_nominal);

        $denda = $kelas->denda_mode === 'harian'
            ? ($hariTelat - $kelas->grace_days) * $nominalDenda
            : $nominalDenda;

        $maks = $kelas->denda_maks === null ? null : Uang::keSen($kelas->denda_maks);

        return $maks === null ? $denda : min($denda, $maks);
    }

    /** Tunggakan = sisa tagihan yang sudah jatuh tempo, ditambah dendanya. */
    public function tunggakanSiswa(Student $siswa, ?Classroom $kelas = null): int
    {
        $kelas ??= CurrentClassroom::getOrFail();
        $hariIni = now()->startOfDay();

        return Bill::where('student_id', $siswa->id)
            ->where('is_bebas', false)
            ->with(['period', 'allocations'])
            ->get()
            ->filter(fn (Bill $bill) => $bill->period === null
                || CarbonImmutable::parse($bill->period->jatuh_tempo)->lte($hariIni))
            ->sum(fn (Bill $bill) => $this->sisaTagihan($bill) + $this->dendaTagihan($bill, $kelas));
    }

    /*
    |--------------------------------------------------------------------------
    | Alokasi pembayaran
    |--------------------------------------------------------------------------
    | Satu mesin untuk semua kasus: bayar pas, rapel, cicil, bayar di muka.
    | Iuran insidental (v1.1) juga memakai mesin ini tanpa cabang kode baru —
    | kalau sampai butuh cabang baru, berarti desainnya yang salah.
    */

    /**
     * Tagihan siswa yang belum lunas, terlama lebih dulu.
     *
     * Urutan inilah yang membuat rapel bekerja: uang selalu menutup tunggakan
     * paling tua sebelum menyentuh tagihan yang belum jatuh tempo.
     */
    public function tagihanBelumLunas(Student $siswa): Collection
    {
        return Bill::where('student_id', $siswa->id)
            ->where('is_bebas', false)
            ->with(['period', 'allocations'])
            ->get()
            ->sortBy([
                fn (Bill $a, Bill $b) => ($a->period?->jatuh_tempo?->timestamp ?? PHP_INT_MAX)
                    <=> ($b->period?->jatuh_tempo?->timestamp ?? PHP_INT_MAX),
                fn (Bill $a, Bill $b) => $a->id <=> $b->id,
            ])
            ->filter(fn (Bill $bill) => $this->sisaTagihan($bill) > 0)
            ->values();
    }

    /**
     * Membagi uang yang belum teralokasi milik seorang siswa ke tagihannya.
     *
     * Dipanggil setelah pembayaran baru, dan juga setelah tagihan baru muncul —
     * itulah yang membuat kelebihan bayar otomatis terpakai di periode berikutnya.
     *
     * @return int jumlah sen yang berhasil dialokasikan
     */
    public function alokasikanDeposit(Student $siswa): int
    {
        return DB::transaction(function () use ($siswa) {
            $tagihan = $this->tagihanBelumLunas($siswa);

            if ($tagihan->isEmpty()) {
                return 0;
            }

            $pembayaran = Payment::where('student_id', $siswa->id)
                ->with('allocations')
                ->orderBy('tanggal')
                ->orderBy('id')
                ->get();

            $terpakai = 0;
            $indeks = 0;
            $sisaTagihan = $tagihan->map(fn (Bill $bill) => $this->sisaTagihan($bill))->all();

            foreach ($pembayaran as $bayar) {
                $sisaBayar = Uang::keSen($bayar->jumlah)
                    - $bayar->allocations->sum(fn (PaymentAllocation $a) => Uang::keSen($a->jumlah));

                while ($sisaBayar > 0 && $indeks < $tagihan->count()) {
                    if ($sisaTagihan[$indeks] <= 0) {
                        $indeks++;

                        continue;
                    }

                    $porsi = min($sisaBayar, $sisaTagihan[$indeks]);

                    PaymentAllocation::create([
                        'payment_id' => $bayar->id,
                        'bill_id' => $tagihan[$indeks]->id,
                        'jumlah' => Uang::keDesimal($porsi),
                    ]);

                    $sisaBayar -= $porsi;
                    $sisaTagihan[$indeks] -= $porsi;
                    $terpakai += $porsi;
                }
            }

            return $terpakai;
        });
    }

    /**
     * Alokasi manual: bendahara menentukan sendiri tagihan mana yang dibayar.
     *
     * @param  array<int|string, string|int|null>  $rincian  [bill_id => jumlah]
     *
     * @throws RuntimeException bila total melebihi nilai pembayaran atau sisa tagihan
     */
    public function alokasikanManual(Payment $payment, array $rincian): int
    {
        return DB::transaction(function () use ($payment, $rincian) {
            $this->batalkanAlokasi($payment);

            $sisaBayar = Uang::keSen($payment->jumlah);
            $terpakai = 0;

            foreach ($rincian as $billId => $jumlah) {
                $porsi = Uang::keSen($jumlah);

                if ($porsi <= 0) {
                    continue;
                }

                // Lewat relasi kelas aktif, bukan Bill::find() dari input mentah.
                $bill = Bill::where('id', $billId)
                    ->where('student_id', $payment->student_id)
                    ->with('allocations')
                    ->first();

                if ($bill === null) {
                    throw new RuntimeException('Tagihan yang dipilih tidak ada di kelas ini.');
                }

                if ($bill->is_bebas) {
                    throw new RuntimeException('Tagihan yang dibebaskan tidak bisa dibayar.');
                }

                if ($porsi > $this->sisaTagihan($bill)) {
                    throw new RuntimeException(
                        "Alokasi untuk tagihan {$bill->id} melebihi sisa tagihannya."
                    );
                }

                if ($porsi > $sisaBayar) {
                    throw new RuntimeException('Total alokasi melebihi jumlah uang yang dibayarkan.');
                }

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'bill_id' => $bill->id,
                    'jumlah' => Uang::keDesimal($porsi),
                ]);

                $sisaBayar -= $porsi;
                $terpakai += $porsi;
            }

            return $terpakai;
        });
    }

    /** Melepas seluruh alokasi sebuah pembayaran; uangnya kembali jadi deposit siswa. */
    public function batalkanAlokasi(Payment $payment): void
    {
        PaymentAllocation::where('payment_id', $payment->id)->get()->each->delete();
    }

    /**
     * Menghapus pembayaran: alokasinya dilepas dan barisnya di-soft delete.
     * Status tagihan otomatis pulih karena dihitung ulang dari alokasi yang tersisa.
     */
    public function hapusPembayaran(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $siswa = $payment->student;

            $this->batalkanAlokasi($payment);
            $payment->delete();

            // Deposit siswa lain yang menganggur bisa jadi kini punya tempat.
            if ($siswa) {
                $this->alokasikanDeposit($siswa);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Pelaporan
    |--------------------------------------------------------------------------
    | Semua angka di bawah dihitung ulang dari tabel transaksi setiap kali
    | diminta. Tidak ada kolom ringkasan yang disimpan, sehingga laporan
    | mustahil "basi" setelah ada transaksi baru.
    */

    /** Angka utama dashboard & halaman kelas. */
    public function ringkasan(?Classroom $kelas = null): array
    {
        $kelas ??= CurrentClassroom::getOrFail();
        $tunggakan = $this->daftarTunggakan($kelas);

        return [
            'saldo' => $this->saldoKas(),
            'masuk' => $this->totalMasuk(),
            'keluar' => $this->totalKeluar(),
            'siswa_aktif' => Student::aktif()->count(),
            'penunggak' => $tunggakan->count(),
            'total_tunggakan' => $tunggakan->sum('tunggakan'),
            'deposit' => $tunggakan->sum('deposit'),
        ];
    }

    /**
     * Tunggakan seluruh siswa, terbesar lebih dulu.
     *
     * Query-nya sengaja dimuat sekali lalu dihitung di PHP: satu kelas paling
     * banyak 60 siswa, jadi jauh lebih murah daripada satu query per siswa.
     *
     * @return Collection<int, array{siswa: Student, tunggakan: int, deposit: int, belum_lunas: int}>
     */
    public function daftarTunggakan(?Classroom $kelas = null, bool $termasukNonaktif = true): Collection
    {
        $kelas ??= CurrentClassroom::getOrFail();
        $hariIni = now()->startOfDay();

        $siswa = Student::when(! $termasukNonaktif, fn ($q) => $q->where('is_active', true))
            ->urutAbsen()
            ->get();

        $tagihan = Bill::where('is_bebas', false)
            ->with(['period', 'allocations'])
            ->get()
            ->groupBy('student_id');

        $dibayar = Payment::selectRaw('student_id, SUM(jumlah) AS total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $teralokasi = PaymentAllocation::join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->whereNull('payments.deleted_at')
            ->selectRaw('payments.student_id AS student_id, SUM(payment_allocations.jumlah) AS total')
            ->groupBy('payments.student_id')
            ->pluck('total', 'student_id');

        return $siswa
            ->map(function (Student $s) use ($tagihan, $dibayar, $teralokasi, $kelas, $hariIni) {
                $miliknya = $tagihan->get($s->id, collect());

                $tunggakan = $miliknya
                    ->filter(fn (Bill $b) => $b->period === null
                        || CarbonImmutable::parse($b->period->jatuh_tempo)->lte($hariIni))
                    ->sum(fn (Bill $b) => $this->sisaTagihan($b) + $this->dendaTagihan($b, $kelas));

                return [
                    'siswa' => $s,
                    'tunggakan' => $tunggakan,
                    'deposit' => max(0, Uang::keSen((string) $dibayar->get($s->id, 0))
                        - Uang::keSen((string) $teralokasi->get($s->id, 0))),
                    'belum_lunas' => $miliknya->filter(fn (Bill $b) => $this->sisaTagihan($b) > 0)->count(),
                ];
            })
            ->filter(fn (array $baris) => $baris['tunggakan'] > 0)
            ->sortByDesc('tunggakan')
            ->values();
    }

    /**
     * Rekap per periode: berapa yang ditagihkan, terkumpul, dan siapa yang lunas.
     *
     * @return Collection<int, array{periode: Period, tertagih: int, terkumpul: int, sisa: int, lunas: int, jumlah_tagihan: int}>
     */
    public function rekapPeriode(): Collection
    {
        $tagihan = Bill::with('allocations')->get()->groupBy('period_id');

        return Period::urutWaktu()->get()->map(function (Period $periode) use ($tagihan) {
            $miliknya = $tagihan->get($periode->id, collect());

            $tertagih = $miliknya->sum(fn (Bill $b) => $b->is_bebas ? 0 : Uang::keSen($b->nominal));
            $terkumpul = $miliknya->sum(fn (Bill $b) => $this->dibayarTagihan($b));

            return [
                'periode' => $periode,
                'tertagih' => $tertagih,
                'terkumpul' => $terkumpul,
                'sisa' => max(0, $tertagih - $terkumpul),
                'lunas' => $miliknya->filter(fn (Bill $b) => $this->sisaTagihan($b) <= 0)->count(),
                'jumlah_tagihan' => $miliknya->count(),
            ];
        })->values();
    }

    /**
     * Riwayat masuk & keluar dalam satu urutan waktu.
     *
     * @return Collection<int, array{tanggal: \Carbon\CarbonInterface, jenis: string, keterangan: string, jumlah: int, model: \Illuminate\Database\Eloquent\Model}>
     */
    public function riwayatTransaksi(?string $dari = null, ?string $sampai = null): Collection
    {
        $pembayaran = Payment::with('student')
            ->when($dari, fn ($q) => $q->whereDate('tanggal', '>=', $dari))
            ->when($sampai, fn ($q) => $q->whereDate('tanggal', '<=', $sampai))
            ->get()
            ->map(fn (Payment $p) => [
                'tanggal' => $p->tanggal,
                'jenis' => 'masuk',
                'keterangan' => 'Iuran '.($p->student?->nama ?? 'siswa terhapus')
                    .($p->catatan ? ' — '.$p->catatan : ''),
                'jumlah' => Uang::keSen($p->jumlah),
                'model' => $p,
            ]);

        $pengeluaran = Expense::with('category')
            ->when($dari, fn ($q) => $q->whereDate('tanggal', '>=', $dari))
            ->when($sampai, fn ($q) => $q->whereDate('tanggal', '<=', $sampai))
            ->get()
            ->map(fn (Expense $e) => [
                'tanggal' => $e->tanggal,
                'jenis' => 'keluar',
                'keterangan' => $e->keterangan.' ('.($e->category?->nama ?? 'tanpa kategori').')',
                'jumlah' => Uang::keSen($e->jumlah),
                'model' => $e,
            ]);

        return $pembayaran->concat($pengeluaran)
            ->sortByDesc(fn (array $baris) => $baris['tanggal']->timestamp)
            ->values();
    }
}
