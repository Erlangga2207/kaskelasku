<?php

namespace App\Http\Controllers;

use App\Support\Kapasitas;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Halaman publik: beranda, panduan, privasi, syarat, sitemap, robots.
 *
 * Semuanya Blade statis tanpa framework JS. Bukan karena anti-JavaScript, tapi
 * karena halaman ini dibuka dari tautan WhatsApp di jaringan seluler yang
 * sering lambat — dan halaman yang baru tampil setelah mengunduh satu megabita
 * JavaScript sudah ditinggalkan sebelum sempat terbaca.
 */
class PublicPageController extends Controller
{
    /**
     * Sumber tunggal daftar FAQ.
     *
     * Dipakai dua kali: untuk ditampilkan di halaman, dan untuk JSON-LD
     * FAQPage. Kalau keduanya ditulis terpisah, cepat atau lambat isinya
     * berbeda — dan jawaban terstruktur yang tidak sama dengan yang terlihat di
     * halaman adalah pelanggaran pedoman Google, bukan sekadar tidak rapi.
     *
     * @return array<int, array{tanya: string, jawab: string}>
     */
    public static function faq(): array
    {
        return [
            [
                'tanya' => 'Apakah KasKelas gratis?',
                'jawab' => 'Ya, gratis dan tanpa iklan. Tidak ada versi berbayar, tidak ada batas waktu coba, '
                    .'dan tidak ada fitur yang dikunci. Aplikasi ini dibuat karena pembuatnya sendiri jadi '
                    .'bendahara kelas dan butuh alat seperti ini.',
            ],
            [
                'tanya' => 'Apakah anggota kelas harus punya akun untuk melihat catatan kas?',
                'jawab' => 'Tidak. Hanya bendahara yang punya akun. Anggota kelas cukup membuka tautan kelas '
                    .'yang dibagikan di grup WhatsApp, dan langsung melihat saldo, rekap, serta status bayar. '
                    .'Halaman itu tidak bisa dipakai mengubah apa pun.',
            ],
            [
                'tanya' => 'Apakah data siswa aman?',
                'jawab' => 'Data yang disimpan sengaja dibuat sesedikit mungkin: hanya nama, nomor absen, dan '
                    .'status iuran. Tidak ada NIS, nomor HP, alamat, atau foto. Koneksi memakai HTTPS dan '
                    .'halaman kelas hanya bisa dibuka lewat tautan bertoken acak yang bisa diganti kapan saja '
                    .'oleh bendahara. Perlu dipahami juga bahwa siapa pun yang memegang tautan itu bisa '
                    .'melihat isinya, jadi bagikan hanya ke grup kelas.',
            ],
            [
                'tanya' => 'Bagaimana kalau saya salah mencatat pembayaran?',
                'jawab' => 'Pembayaran bisa dihapus, dan status tagihannya otomatis kembali seperti sebelum '
                    .'dibayar. Semua perubahan tercatat di audit log lengkap dengan waktu dan pelakunya, '
                    .'jadi tidak ada perubahan diam-diam. Setelah buku ditutup, koreksi dilakukan lewat '
                    .'transaksi penyesuaian bertanggal baru, bukan dengan mengubah data lama.',
            ],
            [
                'tanya' => 'Kenapa laporan saya masih nol padahal pembayaran sudah dicatat?',
                'jawab' => 'Hampir selalu karena periode iuran belum dibuat. Tanpa periode tidak ada tagihan, '
                    .'sehingga uang yang dicatat mengendap sebagai deposit dan belum masuk ke rekap mana pun. '
                    .'Buat periodenya lebih dulu, lalu uang yang sudah tercatat akan otomatis dialokasikan.',
            ],
            [
                'tanya' => 'Apakah saya bisa mengambil data saya kalau mau berhenti?',
                'jawab' => 'Bisa, kapan saja, tanpa minta izin. Seluruh data kelas — siswa, tagihan, '
                    .'pembayaran, pengeluaran — bisa diunduh sebagai berkas CSV yang langsung bisa dibuka '
                    .'di Excel atau Google Sheets.',
            ],
            [
                'tanya' => 'Bagaimana kalau bendaharanya ganti tahun depan?',
                'jawab' => 'Ada menu serah terima. Bendahara lama mengalihkan kelas ke akun bendahara baru, '
                    .'lalu mencetak laporan serah terima berisi saldo akhir dan daftar tunggakan, lengkap '
                    .'dengan kolom tanda tangan. Akun tidak perlu — dan sebaiknya tidak — dipakai bergantian.',
            ],
        ];
    }

    public function beranda(): View
    {
        return view('publik.beranda', [
            'faq' => static::faq(),
            'kuotaPenuh' => Kapasitas::kuotaSistemPenuh(),
            'sisaKuota' => Kapasitas::sisaKuotaSistem(),
            'jsonLd' => $this->jsonLdBeranda(),
        ]);
    }

    public function panduan(): View
    {
        return view('publik.panduan');
    }

    public function privasi(): View
    {
        return view('publik.privasi');
    }

    public function syarat(): View
    {
        return view('publik.syarat');
    }

    /**
     * Sitemap hanya memuat halaman yang MEMANG boleh diindeks.
     *
     * Dashboard, halaman kelas bertoken, dan seluruh area bendahara sengaja
     * tidak ada di sini — dan juga sudah ditandai noindex di layout-nya. Dua
     * lapis, karena sitemap yang keliru bisa mengundang crawler ke tempat yang
     * seharusnya tidak pernah dikunjungi.
     */
    public function sitemap(): Response
    {
        $halaman = [
            ['loc' => route('beranda'), 'prioritas' => '1.0', 'ubah' => 'weekly'],
            ['loc' => route('panduan'), 'prioritas' => '0.8', 'ubah' => 'monthly'],
            ['loc' => route('privasi'), 'prioritas' => '0.3', 'ubah' => 'yearly'],
            ['loc' => route('syarat'), 'prioritas' => '0.3', 'ubah' => 'yearly'],
        ];

        $xml = view('publik.sitemap', ['halaman' => $halaman])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $baris = [
            'User-agent: *',
            'Allow: /$',
            'Allow: /panduan',
            'Allow: /privasi',
            'Allow: /syarat',
            '',
            '# Area bendahara dan halaman kelas bertoken tidak pernah boleh diindeks.',
            '# Halaman kelas berisi nama siswa; terindeks sekali saja sudah cukup',
            '# untuk membuatnya bisa ditemukan orang yang tidak pernah diberi tautannya.',
            'Disallow: /dashboard',
            'Disallow: /kelas/',
            'Disallow: /siswa',
            'Disallow: /pembayaran',
            'Disallow: /pengeluaran',
            'Disallow: /laporan',
            'Disallow: /periode',
            'Disallow: /campaign',
            'Disallow: /pengingat',
            'Disallow: /tutup-buku',
            'Disallow: /pengaturan',
            'Disallow: /audit',
            'Disallow: /ekspor',
            'Disallow: /admin',
            'Disallow: /masuk',
            'Disallow: /wizard',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $baris)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * SoftwareApplication + FAQPage.
     *
     * offers dengan harga 0 ditulis apa adanya karena aplikasinya memang gratis.
     * Tidak ada aggregateRating: menaruh bintang tanpa ulasan sungguhan adalah
     * data palsu, dan itu bisa membuat seluruh data terstruktur situs diabaikan.
     */
    protected function jsonLdBeranda(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'SoftwareApplication',
                    'name' => 'KasKelas',
                    'url' => route('beranda'),
                    'applicationCategory' => 'FinanceApplication',
                    'operatingSystem' => 'Web',
                    'inLanguage' => 'id-ID',
                    'description' => 'Aplikasi pencatatan uang kas kelas online untuk bendahara kelas: '
                        .'mencatat iuran, pengeluaran, dan tunggakan, serta membagikan rekapnya ke anggota kelas.',
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => '0',
                        'priceCurrency' => 'IDR',
                    ],
                ],
                [
                    '@type' => 'FAQPage',
                    'mainEntity' => array_map(fn (array $item) => [
                        '@type' => 'Question',
                        'name' => $item['tanya'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['jawab']],
                    ], static::faq()),
                ],
            ],
        ];
    }
}
