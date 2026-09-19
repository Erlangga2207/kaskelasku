<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Classroom;
use App\Models\Student;
use App\Support\CurrentClassroom;
use App\Support\Uang;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Generator teks pengingat tunggakan (PRD bagian 6.1).
 *
 * Kelas ini TIDAK menghitung uang. Seluruh angka — sisa tagihan, denda, dan
 * apakah sebuah tagihan sudah jatuh tempo — diambil dari KasService, supaya
 * angka di teks pengingat mustahil berbeda dari angka di laporan. Tugas kelas
 * ini murni merangkai angka-angka itu menjadi teks siap tempel.
 *
 * Dipisah dari KasService karena ini soal penyajian, bukan soal uang; dan
 * dipisah dari controller karena dipakai di tiga tempat: halaman pengingat,
 * pratinjau template di Pengaturan, dan test.
 *
 * TIDAK ADA pengiriman otomatis di sini. Aplikasi hanya menyiapkan teksnya;
 * bendahara menyalin dan mengirim sendiri lewat WhatsApp.
 */
class PengingatService
{
    /**
     * Template bawaan, dipakai selama kelas belum menyimpan template sendiri.
     *
     * Nadanya sengaja menagih tanpa menuduh, dan selalu menyediakan jalan
     * keluar ("kalau sudah dibayar, kabari") — catatan bendahara bisa saja
     * yang tertinggal, bukan orangnya yang belum bayar.
     */
    public const TEMPLATE_BAWAAN = <<<'TEKS'
        Halo {nama}, mohon izin mengingatkan soal iuran kas kelas ya.

        Yang belum lunas:
        {rincian}

        Total: {total}

        Mohon dilunasi sebelum {batas}. Kalau ternyata sudah dibayar tapi masih tertulis belum lunas, tolong kabari bendahara supaya catatannya diperbaiki. Terima kasih 🙏
        TEKS;

    /** Placeholder yang dikenali. Di luar daftar ini tidak diganti apa pun. */
    public const PLACEHOLDER = ['{nama}', '{rincian}', '{total}', '{batas}'];

    public function __construct(private readonly KasService $kas) {}

    /** Batas pembayaran bawaan: sepekan dari hari ini. */
    public static function batasBawaan(): CarbonImmutable
    {
        return CarbonImmutable::now()->addWeek()->startOfDay();
    }

    /** Template milik kelas, atau template bawaan bila belum pernah diubah. */
    public function template(?Classroom $kelas = null): string
    {
        $kelas ??= CurrentClassroom::getOrFail();
        $template = trim((string) $kelas->template_pengingat);

        return $template === '' ? static::TEMPLATE_BAWAAN : $template;
    }

    /**
     * Teks pengingat untuk seorang siswa.
     *
     * @return string|null null bila siswa tidak punya tunggakan — siswa yang
     *                     tidak menunggak tidak boleh menghasilkan teks apa pun
     */
    public function untukSiswa(
        Student $siswa,
        CarbonInterface|string|null $batas = null,
        ?Classroom $kelas = null,
    ): ?string {
        return $this->untukBanyakSiswa(collect([$siswa]), $batas, $kelas)->get($siswa->id)['teks'] ?? null;
    }

    /**
     * Pengingat untuk sekumpulan siswa sekaligus.
     *
     * Menagih hampir selalu dilakukan untuk banyak orang sekali jalan, jadi
     * seluruh tagihan diambil dalam SATU query lalu dikelompokkan di memori.
     * Kalau tiap siswa memanggil KasService sendiri-sendiri, halaman pengingat
     * akan menembak satu query per baris.
     *
     * Siswa tanpa tunggakan tidak muncul di hasil.
     *
     * @param  Collection<int, Student>  $daftarSiswa
     * @return Collection<int, array{siswa: Student, rincian: Collection<int, array{label: string, sisa: int, denda: int}>, total: int, teks: string}>
     */
    public function untukBanyakSiswa(
        Collection $daftarSiswa,
        CarbonInterface|string|null $batas = null,
        ?Classroom $kelas = null,
    ): Collection {
        $kelas ??= CurrentClassroom::getOrFail();

        if ($daftarSiswa->isEmpty()) {
            return collect();
        }

        $hariIni = CarbonImmutable::now()->startOfDay();
        $template = $this->template($kelas);
        $batasTeks = $this->formatBatas($batas);

        $tagihan = Bill::whereIn('student_id', $daftarSiswa->pluck('id')->all())
            ->where('is_bebas', false)
            ->with(['period', 'campaign', 'allocations'])
            ->get()
            ->groupBy('student_id');

        return $daftarSiswa->mapWithKeys(function (Student $siswa) use ($tagihan, $hariIni, $kelas, $template, $batasTeks) {
            $rincian = $this->rincianDariTagihan($tagihan->get($siswa->id, collect()), $hariIni, $kelas);
            $total = $rincian->sum(fn (array $baris) => $baris['sisa'] + $baris['denda']);

            if ($total <= 0) {
                return [];
            }

            return [$siswa->id => [
                'siswa' => $siswa,
                'rincian' => $rincian,
                'total' => $total,
                'teks' => $this->render($template, [
                    'nama' => $siswa->nama,
                    'rincian' => $rincian->map(fn (array $baris) => $this->barisRincian($baris))->implode("\n"),
                    'total' => Uang::format(Uang::keDesimal($total)),
                    'batas' => $batasTeks,
                ]),
            ]];
        });
    }

    /**
     * Rincian tunggakan seorang siswa: tagihan yang sudah jatuh tempo dan
     * masih menyisakan uang, terlama lebih dulu.
     *
     * @return Collection<int, array{label: string, sisa: int, denda: int}>
     */
    public function rincianTunggakan(Student $siswa, ?Classroom $kelas = null): Collection
    {
        $kelas ??= CurrentClassroom::getOrFail();

        return $this->rincianDariTagihan(
            // tagihanBelumLunas sudah menyaring is_bebas dan sisa nol, serta
            // mengurutkan terlama dulu — tidak perlu diulang di sini.
            $this->kas->tagihanBelumLunas($siswa),
            CarbonImmutable::now()->startOfDay(),
            $kelas,
        );
    }

    /** Mengganti placeholder. Nilai yang tidak dikenali dibiarkan apa adanya. */
    public function render(string $template, array $nilai): string
    {
        $gantian = [];

        foreach ($nilai as $kunci => $isi) {
            $gantian['{'.$kunci.'}'] = (string) $isi;
        }

        return strtr($template, $gantian);
    }

    /**
     * Contoh isi placeholder untuk pratinjau template di Pengaturan.
     *
     * Angka karangan, bukan data siswa sungguhan: pratinjau dibuka sebelum
     * template disimpan, dan tidak semua kelas punya penunggak saat mengatur.
     *
     * @return array{nama: string, rincian: string, total: string, batas: string}
     */
    public function contoh(): array
    {
        return [
            'nama' => 'Adinda',
            'rincian' => "- Januari 2026: Rp 5.000\n- Februari 2026: Rp 5.000 + denda Rp 1.000",
            'total' => 'Rp 11.000',
            'batas' => $this->formatBatas(null),
        ];
    }

    /**
     * @param  Collection<int, Bill>  $tagihan
     * @return Collection<int, array{label: string, sisa: int, denda: int}>
     */
    protected function rincianDariTagihan(Collection $tagihan, CarbonImmutable $hariIni, Classroom $kelas): Collection
    {
        return $tagihan
            // Definisi tunggakan dipinjam utuh dari KasService: hanya tagihan
            // yang SUDAH jatuh tempo yang pantas ditagih lewat pengingat.
            ->filter(fn (Bill $bill) => $this->kas->sudahJatuhTempo($bill, $hariIni))
            ->sortBy([
                fn (Bill $a, Bill $b) => ($this->kas->jatuhTempoTagihan($a)?->timestamp ?? PHP_INT_MAX)
                    <=> ($this->kas->jatuhTempoTagihan($b)?->timestamp ?? PHP_INT_MAX),
                fn (Bill $a, Bill $b) => $a->id <=> $b->id,
            ])
            ->map(fn (Bill $bill) => [
                'label' => $bill->label(),
                'sisa' => $this->kas->sisaTagihan($bill),
                'denda' => $this->kas->dendaTagihan($bill, $kelas),
            ])
            ->filter(fn (array $baris) => $baris['sisa'] + $baris['denda'] > 0)
            ->values();
    }

    /** @param  array{label: string, sisa: int, denda: int}  $baris */
    protected function barisRincian(array $baris): string
    {
        $teks = '- '.$baris['label'].': '.Uang::format(Uang::keDesimal($baris['sisa']));

        if ($baris['denda'] > 0) {
            $teks .= ' + denda '.Uang::format(Uang::keDesimal($baris['denda']));
        }

        return $teks;
    }

    protected function formatBatas(CarbonInterface|string|null $batas): string
    {
        return CarbonImmutable::parse($batas ?? static::batasBawaan())->translatedFormat('j F Y');
    }
}
