<?php

namespace App\Http\Controllers;

use App\Http\Requests\CampaignRequest;
use App\Models\Bill;
use App\Models\Campaign;
use App\Models\Student;
use App\Services\KasService;
use App\Support\Uang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Iuran insidental (campaign): studi tour, perpisahan, bingkisan guru.
 *
 * Campaign hanya menerbitkan tagihan. Uangnya masuk lewat form pembayaran yang
 * sama dengan iuran rutin, dialokasikan mesin yang sama, dan dihitung dengan
 * rumus yang sama. Controller ini tidak pernah menyentuh payment_allocations
 * secara langsung.
 */
class CampaignController extends Controller
{
    public function __construct(private readonly KasService $kas) {}

    public function index(): View
    {
        return view('campaign.index', [
            'rekap' => $this->kas->rekapCampaign(),
            'saldoBebas' => $this->kas->saldoBebas(),
            'danaCampaign' => $this->kas->danaCampaignTertahan(),
            'saldoKas' => $this->kas->saldoKas(),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        // Tanpa siswa aktif, campaign hanya akan lahir tanpa satu pun tagihan.
        // Bendahara diarahkan mengisi daftar siswa dulu, bukan dibiarkan membuat
        // campaign kosong yang tampak berhasil.
        if ($this->siswaAktif()->isEmpty()) {
            return redirect()->route('siswa.index')->with(
                'peringatan',
                'Kelas ini belum punya siswa aktif, jadi campaign tidak akan menghasilkan tagihan apa pun. '
                    .'Tambahkan daftar siswa dulu di halaman ini.'
            );
        }

        return view('campaign.form', [
            'campaign' => new Campaign(['deadline' => null]),
            'daftarSiswa' => $this->siswaAktif(),
            // Bawaannya seluruh siswa aktif ikut; bendahara tinggal mengurangi.
            'pesertaTerpilih' => $this->siswaAktif()->pluck('id')->all(),
        ]);
    }

    public function store(CampaignRequest $request): RedirectResponse
    {
        $data = $request->validated();

        [$campaign, $dibuat] = DB::transaction(function () use ($data, $request) {
            $campaign = Campaign::create([
                'nama' => $data['nama'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'nominal_per_siswa' => Uang::keDesimal(Uang::keSen($data['nominal_per_siswa'])),
                'deadline' => $data['deadline'] ?? null,
            ]);

            return [$campaign, $this->kas->generateTagihanCampaign($campaign, $request->peserta())];
        });

        // Campaign tanpa tagihan adalah gejala, bukan keberhasilan. Pelajaran dari
        // bug Fase 2: kegagalan yang dilaporkan seolah sukses baru ketahuan
        // berminggu-minggu kemudian, saat angkanya sudah dipakai orang.
        if ($dibuat === 0) {
            return redirect()->route('campaign.show', $campaign)->with(
                'peringatan',
                "Campaign \"{$campaign->nama}\" tersimpan, tapi TIDAK ADA satu pun tagihan yang terbentuk. "
                    .'Periksa daftar peserta yang dipilih — tanpa tagihan, pembayaran untuk campaign ini '
                    .'hanya akan mengendap sebagai deposit siswa.'
            );
        }

        return redirect()->route('campaign.show', $campaign)->with(
            'sukses',
            "Campaign \"{$campaign->nama}\" dibuat beserta {$dibuat} tagihan peserta."
        );
    }

    public function show(string $campaign): View
    {
        $campaign = $this->cariCampaign($campaign);

        $tagihan = Bill::where('campaign_id', $campaign->id)
            ->with(['student', 'allocations'])
            ->get()
            ->sortBy(fn (Bill $bill) => [$bill->student?->no_absen ?? PHP_INT_MAX, $bill->student?->nama])
            ->values();

        return view('campaign.show', [
            'campaign' => $campaign,
            'ringkasan' => $this->kas->ringkasanCampaign($campaign),
            'tagihan' => $tagihan,
            'pengeluaran' => $this->kelas()->expenses()
                ->where('campaign_id', $campaign->id)
                ->with('category')
                ->orderByDesc('tanggal')
                ->get(),
            'kas' => $this->kas,
        ]);
    }

    public function edit(string $campaign): View|RedirectResponse
    {
        $campaign = $this->cariCampaign($campaign);

        if ($campaign->isDibatalkan()) {
            return redirect()->route('campaign.show', $campaign)
                ->with('galat', 'Campaign yang sudah dibatalkan tidak bisa diubah lagi.');
        }

        return view('campaign.form', [
            'campaign' => $campaign,
            'daftarSiswa' => $this->siswaAktif($campaign),
            'pesertaTerpilih' => Bill::where('campaign_id', $campaign->id)->pluck('student_id')->all(),
            'sudahBayar' => $this->kas->pesertaSudahBayar($campaign)->all(),
        ]);
    }

    /**
     * Mengubah campaign sekaligus menyelaraskan daftar pesertanya.
     *
     * Dua hal yang dijaga keras di sini, karena keduanya menyangkut uang yang
     * sudah nyata diterima:
     *   1. Peserta yang tagihannya sudah dibayar tidak bisa dikeluarkan.
     *   2. Nominal tidak bisa diubah setelah ada uang masuk — kalau tidak,
     *      angka "terkumpul" jadi tidak bisa dipertanggungjawabkan.
     */
    public function update(CampaignRequest $request, string $campaign): RedirectResponse
    {
        $campaign = $this->cariCampaign($campaign);

        if ($campaign->isDibatalkan()) {
            return back()->with('galat', 'Campaign yang sudah dibatalkan tidak bisa diubah lagi.');
        }

        $data = $request->validated();
        $pesertaBaru = $request->peserta();

        $pesertaLama = Bill::where('campaign_id', $campaign->id)->pluck('student_id')->all();
        $sudahBayar = $this->kas->pesertaSudahBayar($campaign)->all();

        $dikeluarkan = array_values(array_diff($pesertaLama, $pesertaBaru));
        $terkunci = array_intersect($dikeluarkan, $sudahBayar);

        if ($terkunci !== []) {
            $nama = Student::whereIn('id', $terkunci)->pluck('nama')->implode(', ');

            return back()->withInput()->with(
                'galat',
                "Peserta yang tagihannya sudah menerima pembayaran tidak bisa dikeluarkan: {$nama}. "
                    .'Hapus dulu pembayarannya bila memang keliru.'
            );
        }

        $nominalBaru = Uang::keSen($data['nominal_per_siswa']);
        $nominalBerubah = $nominalBaru !== Uang::keSen($campaign->nominal_per_siswa);

        if ($nominalBerubah && $sudahBayar !== []) {
            return back()->withInput()->with(
                'galat',
                'Campaign ini sudah menerima pembayaran, jadi nominal per siswa tidak bisa diubah lagi. '
                    .'Batalkan campaign dan buat yang baru bila nominalnya memang salah.'
            );
        }

        [$ditambah, $dicabut] = DB::transaction(function () use ($campaign, $data, $nominalBaru, $nominalBerubah, $pesertaBaru, $pesertaLama, $dikeluarkan) {
            $campaign->update([
                'nama' => $data['nama'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'nominal_per_siswa' => Uang::keDesimal($nominalBaru),
                'deadline' => $data['deadline'] ?? null,
            ]);

            if ($nominalBerubah) {
                Bill::where('campaign_id', $campaign->id)
                    ->where('is_bebas', false)
                    ->get()
                    ->each(fn (Bill $bill) => $bill->update(['nominal' => $campaign->nominal_per_siswa]));
            }

            $dicabut = $dikeluarkan === []
                ? 0
                : $this->kas->hapusTagihanCampaign($campaign, $dikeluarkan);

            $ditambah = $this->kas->generateTagihanCampaign(
                $campaign,
                array_values(array_diff($pesertaBaru, $pesertaLama)),
            );

            return [$ditambah, $dicabut];
        });

        $catatan = array_filter([
            $ditambah > 0 ? "{$ditambah} peserta baru ditagih" : null,
            $dicabut > 0 ? "{$dicabut} tagihan peserta dicabut" : null,
        ]);

        return redirect()->route('campaign.show', $campaign)->with(
            'sukses',
            "Campaign \"{$campaign->nama}\" diperbarui".($catatan === [] ? '.' : ' — '.implode(', ', $catatan).'.')
        );
    }

    /**
     * Menandai campaign selesai, membuka kembali, atau membatalkannya.
     *
     * Membatalkan TIDAK menghapus pembayaran: tagihannya ditarik, alokasinya
     * dilepas, dan uangnya kembali menjadi deposit siswa.
     */
    public function status(Request $request, string $campaign): RedirectResponse
    {
        $campaign = $this->cariCampaign($campaign);

        $data = $request->validate([
            'status' => ['required', 'in:aktif,selesai,dibatalkan'],
        ]);

        if ($campaign->isDibatalkan()) {
            return back()->with('galat', 'Campaign yang sudah dibatalkan tidak bisa diaktifkan lagi. Buat campaign baru bila perlu.');
        }

        if ($data['status'] !== 'dibatalkan') {
            $campaign->update(['status' => $data['status']]);

            return back()->with('sukses', $data['status'] === 'selesai'
                ? "Campaign \"{$campaign->nama}\" ditandai selesai. Sisa dananya tetap terpisah dari saldo bebas."
                : "Campaign \"{$campaign->nama}\" diaktifkan kembali.");
        }

        // Uang campaign yang sudah dibelanjakan tidak bisa ditarik kembali dari
        // toko. Membiarkannya "dibatalkan" akan mengembalikan uang yang sudah
        // tidak ada ke deposit siswa, dan saldo kas jadi berbohong.
        if ($this->kelas()->expenses()->where('campaign_id', $campaign->id)->exists()) {
            return back()->with(
                'galat',
                "Campaign \"{$campaign->nama}\" sudah punya pengeluaran, jadi tidak bisa dibatalkan. "
                    .'Hapus dulu pengeluaran yang ditandai campaign ini bila memang keliru, '
                    .'atau tandai campaign ini selesai.'
            );
        }

        $kembali = $this->kas->terkumpulCampaign($campaign);

        $dicabut = DB::transaction(function () use ($campaign) {
            $dicabut = $this->kas->hapusTagihanCampaign($campaign);
            $campaign->update(['status' => 'dibatalkan']);

            return $dicabut;
        });

        return redirect()->route('campaign.show', $campaign)->with(
            'sukses',
            "Campaign \"{$campaign->nama}\" dibatalkan, {$dicabut} tagihan ditarik. "
                .($kembali > 0
                    ? Uang::format(Uang::keDesimal($kembali)).' yang sudah dibayar TIDAK dihapus — '
                        .'uangnya kembali menjadi deposit siswa dan otomatis dipakai untuk tagihan lain yang belum lunas.'
                    : 'Belum ada uang yang masuk ke campaign ini.')
        );
    }

    /**
     * Menghapus campaign yang salah dibuat.
     *
     * Hanya boleh selama belum ada uang yang menyentuhnya. Kalau sudah ada,
     * jejaknya wajib tinggal — pakai "batalkan", yang meninggalkan riwayat.
     */
    public function destroy(string $campaign): RedirectResponse
    {
        $campaign = $this->cariCampaign($campaign);

        if ($this->kas->terkumpulCampaign($campaign) > 0
            || $this->kelas()->expenses()->where('campaign_id', $campaign->id)->exists()) {
            return back()->with(
                'galat',
                'Campaign ini sudah bersinggungan dengan uang, jadi tidak bisa dihapus. Batalkan saja supaya jejaknya tetap ada.'
            );
        }

        $nama = $campaign->nama;

        DB::transaction(function () use ($campaign) {
            $this->kas->hapusTagihanCampaign($campaign);
            $campaign->delete();
        });

        return redirect()->route('campaign.index')->with('sukses', "Campaign \"{$nama}\" dihapus beserta tagihannya.");
    }

    protected function cariCampaign(string $id): Campaign
    {
        // Lewat relasi kelas aktif: ID milik kelas lain berakhir 404, bukan data orang lain.
        return $this->kelas()->campaigns()->findOrFail($id);
    }

    /**
     * Siswa yang boleh jadi peserta: yang aktif, ditambah peserta lama yang
     * sudah terlanjur ditagih walau kini nonaktif — supaya tidak hilang diam-diam
     * dari daftar saat campaign diubah.
     */
    protected function siswaAktif(?Campaign $campaign = null): Collection
    {
        $pesertaLama = $campaign
            ? Bill::where('campaign_id', $campaign->id)->pluck('student_id')->all()
            : [];

        return $this->kelas()->students()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $pesertaLama))
            ->urutAbsen()
            ->get();
    }
}
