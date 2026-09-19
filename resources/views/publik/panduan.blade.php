@php
    // Tempat screenshot. Sengaja placeholder dengan rasio tetap supaya
    // halamannya tidak "melompat" saat gambar aslinya dipasang nanti —
    // pergeseran tata letak itu buruk untuk pembaca DAN untuk skor Core Web Vitals.
    $langkah = [
        ['daftar', 'Daftar dan verifikasi email', 'Buka halaman Daftar, isi nama, email, dan kata sandi. Cek emailmu, klik tautan verifikasinya. Kalau tidak ketemu, lihat folder Spam atau Promosi — kiriman pertama dari alamat baru sering mendarat di sana.'],
        ['buat-kelas', 'Buat kelas', 'Isi nama kelas, nama sekolah, dan pilih iuran ditarik tiap bulan atau tiap minggu. Centang pernyataan tanggung jawab data siswa, lalu lanjut.'],
        ['siswa', 'Masukkan daftar siswa', 'Jangan mengetik satu per satu. Salin daftar absen dari grup WhatsApp atau Excel, lalu tempel sekaligus ke kotaknya. Bentuk "1. Budi", "1 Budi", atau "Budi" sama-sama dikenali, dan nomor absennya ikut terbaca.'],
        ['periode', 'Buat periode iuran', 'Tentukan sejak kapan iuran ditarik dan berapa nominalnya. Periode dan tagihan untuk semua siswa langsung dibuat sampai akhir tahun ajaran.'],
        ['bayar', 'Catat pembayaran', 'Pilih siswa, masukkan jumlah uang yang diterima. Uangnya otomatis dibagi ke tagihan terlama yang belum lunas.'],
        ['keluar', 'Catat pengeluaran', 'Pilih kategori, masukkan jumlah dan keterangannya. Bisa dilampiri foto struk. Pengeluaran yang melebihi saldo kas akan ditolak.'],
        ['bagikan', 'Bagikan tautan kelas', 'Buka Pengaturan, salin tautan kelas, kirim ke grup WhatsApp. Anggota kelas bisa langsung melihat saldo dan status bayar tanpa login.'],
        ['insidental', 'Buat iuran insidental', 'Untuk study tour, perpisahan, atau patungan kado. Uangnya dipisah dari kas rutin supaya tidak tercampur.'],
        ['pengingat', 'Kirim pengingat tunggakan', 'Buka menu Pengingat. Aplikasi menyusun teksnya lengkap dengan rincian dan total. Salin, tempel ke WhatsApp. Pengirimannya tetap kamu yang lakukan.'],
        ['tutup-buku', 'Tutup buku dan serah terima', 'Di akhir semester, tutup bukunya, cetak laporan serah terima bertanda tangan, lalu alihkan kelas ke bendahara berikutnya.'],
    ];
@endphp

<x-layouts.pemasaran
    judul="Panduan Penggunaan KasKelas untuk Bendahara Kelas"
    deskripsi="Panduan langkah demi langkah memakai aplikasi kas kelas: daftar, buat kelas, input siswa, buat periode iuran, catat pembayaran dan pengeluaran, sampai tutup buku dan serah terima bendahara.">

    <article class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight">Panduan penggunaan</h1>
        <p class="mt-3 text-lg text-ink-soft">
            Ditulis untuk bendahara yang belum pernah pakai aplikasi seperti ini. Ikuti urutannya
            dari atas — urutannya penting, terutama langkah 4.
        </p>

        {{-- Daftar isi: halaman ini panjang, dan orang biasanya datang untuk satu jawaban. --}}
        <nav aria-label="Daftar isi" class="mt-8 rounded-card border border-line bg-card p-5">
            <p class="font-bold">Isi panduan</p>
            <ol class="mt-3 grid gap-1.5 text-sm sm:grid-cols-2">
                @foreach ($langkah as $i => [$anchor, $judul, $isi])
                    <li>
                        <a href="#{{ $anchor }}" class="text-ink-soft underline-offset-2 hover:text-brand hover:underline">
                            {{ $i + 1 }}. {{ $judul }}
                        </a>
                    </li>
                @endforeach
                <li class="sm:col-span-2 pt-1">
                    <a href="#masalah" class="font-semibold text-brand underline-offset-2 hover:underline">
                        Masalah yang sering terjadi
                    </a>
                </li>
            </ol>
        </nav>

        {{-- ================= Langkah ================= --}}
        <div class="mt-12 space-y-12">
            @foreach ($langkah as $i => [$anchor, $judul, $isi])
                <section id="{{ $anchor }}" class="scroll-mt-20">
                    <h2 class="flex items-start gap-3 text-xl font-bold text-ink">
                        <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-sm font-extrabold text-brand-soft-ink">
                            {{ $i + 1 }}
                        </span>
                        {{ $judul }}
                    </h2>

                    <p class="mt-3 text-ink-soft">{{ $isi }}</p>

                    {{-- Kotak screenshot: rasio 16:10 dikunci lewat aspect-video-like
                         padding supaya tidak ada pergeseran tata letak saat diisi. --}}
                    <figure class="mt-4">
                        <div class="flex aspect-[16/10] w-full items-center justify-center rounded-card border border-dashed border-line-strong bg-surface text-sm text-ink-faint">
                            Screenshot: {{ $judul }}
                        </div>
                        <figcaption class="mt-2 text-xs text-ink-faint">{{ $judul }} — tampilan di ponsel.</figcaption>
                    </figure>

                    @if ($anchor === 'siswa')
                        <div class="mt-4 rounded-card border border-line bg-card p-5">
                            <p class="font-bold text-ink">Contoh daftar yang bisa ditempel</p>
                            <pre class="mt-2 overflow-x-auto rounded-lg bg-surface p-3 text-sm text-ink-soft">1. Adinda Rahmawati
2. Bagas Pratama
3. Citra Ayu Lestari</pre>
                            <p class="mt-2 text-sm text-ink-faint">
                                Nama yang sudah ada tidak akan digandakan — yang kembar otomatis dilewati
                                dan dilaporkan.
                            </p>
                        </div>
                    @endif

                    @if ($anchor === 'periode')
                        {{-- Peringatan paling penting di seluruh panduan. --}}
                        <x-ui.alert tipe="peringatan" class="mt-4" judul="Tanpa langkah ini, semuanya akan nol">
                            Periode iuran yang menerbitkan tagihan. Kalau kamu melewatinya dan langsung
                            mencatat pembayaran, uangnya <strong>tercatat masuk tapi mengendap sebagai
                            deposit</strong> — rekap per periode dan daftar tunggakan tetap menunjukkan
                            nol, dan tidak ada pesan galat apa pun yang muncul.
                            <br><br>
                            Kabar baiknya: kalau telanjur, buat saja periodenya sekarang. Uang yang sudah
                            dicatat akan otomatis dialokasikan ke tagihan yang baru terbentuk.
                        </x-ui.alert>
                    @endif

                    @if ($anchor === 'bayar')
                        <div class="mt-4 space-y-3">
                            @foreach ([
                                ['Bayar normal', 'Siswa bayar pas satu periode. Masukkan jumlahnya, selesai.'],
                                ['Bayar rapel', 'Siswa bayar tiga bulan sekaligus. Masukkan total uangnya — sistem membagi sendiri ke Januari, Februari, Maret, berurutan dari yang terlama.'],
                                ['Nyicil', 'Siswa bayar setengah dulu. Tagihannya jadi berstatus "kurang", sisanya tercatat. Saat dia melunasi nanti, cukup catat pembayaran baru.'],
                                ['Kelebihan bayar', 'Siswa membayar lebih dari tagihannya. Kelebihannya disimpan sebagai deposit dan otomatis dipakai untuk tagihan periode berikutnya.'],
                                ['Alokasi manual', 'Kalau siswa minta uangnya dipakai untuk bulan tertentu (bukan yang terlama), pilih "Atur sendiri" lalu tentukan jumlah per tagihan. Sisa yang tidak dibagi akan mengendap sebagai deposit.'],
                            ] as [$kasus, $penjelasan])
                                <div class="rounded-xl border border-line bg-card p-4">
                                    <p class="font-semibold text-ink">{{ $kasus }}</p>
                                    <p class="mt-1 text-sm text-ink-soft">{{ $penjelasan }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($anchor === 'bagikan')
                        <x-ui.alert tipe="peringatan" class="mt-4">
                            Siapa pun yang punya tautan itu bisa melihat isinya, termasuk kalau diteruskan
                            ke luar kelas. Kalau telanjur tersebar, ganti tautannya dari Pengaturan —
                            tautan lama langsung mati.
                        </x-ui.alert>
                    @endif
                </section>
            @endforeach
        </div>

        {{-- ================= Masalah yang sering terjadi ================= --}}
        <section id="masalah" class="mt-16 scroll-mt-20">
            <h2 class="text-2xl font-extrabold tracking-tight">Masalah yang sering terjadi</h2>

            <div class="mt-6 space-y-4">
                @foreach ([
                    [
                        'Kenapa laporan saya nol padahal sudah input pembayaran?',
                        'Hampir selalu karena periode iuran belum dibuat. Tanpa periode tidak ada tagihan, jadi uang yang kamu catat tidak punya tempat mendarat dan mengendap sebagai deposit. Buka menu Periode, buat periodenya sekarang — uang yang sudah tercatat akan otomatis dialokasikan begitu tagihannya terbentuk. Kalau setelah itu masih nol, periksa "mulai ditagih sejak" pada data siswanya: siswa yang tanggal mulainya setelah periode itu memang tidak ditagih untuk periode tersebut.',
                    ],
                    [
                        'Kenapa pembayaran masuk ke tagihan yang salah?',
                        'Secara bawaan uang selalu dipakai untuk tagihan terlama yang belum lunas — itu memang aturan yang benar untuk kas kelas, supaya tunggakan lama tidak menumpuk. Kalau siswa minta uangnya dipakai untuk bulan tertentu, pilih "Atur sendiri" saat mencatat, lalu tentukan sendiri pembagiannya. Kalau terlanjur salah, hapus pembayarannya lalu catat ulang — status tagihannya otomatis kembali seperti semula.',
                    ],
                    [
                        'Bagaimana kalau ada siswa pindah di tengah tahun?',
                        'Jangan dihapus. Buka data siswanya, isi "tanggal berhenti" dengan tanggal dia keluar. Tagihan untuk periode setelah tanggal itu tidak akan dibuat, sementara riwayat pembayarannya yang lalu tetap utuh di laporan. Untuk siswa yang baru masuk di tengah tahun, isi "tanggal mulai aktif" dengan tanggal dia bergabung — dia tidak akan ditagih untuk bulan-bulan sebelumnya.',
                    ],
                    [
                        'Bagaimana kalau saya salah input?',
                        'Pembayaran bisa dihapus, dan status tagihannya langsung pulih seperti sebelum dibayar. Pengeluaran juga bisa diubah atau dihapus. Semuanya tercatat di audit log lengkap dengan waktu dan pelakunya, jadi tidak ada yang hilang diam-diam. Pengecualiannya: kalau periodenya sudah ditutup buku, data lama tidak bisa diubah lagi — koreksinya dilakukan dengan mencatat transaksi penyesuaian bertanggal hari ini.',
                    ],
                    [
                        'Bagaimana kalau ganti bendahara?',
                        'Buka menu Tutup buku. Tutup dulu bukunya untuk rentang kepengurusanmu, cetak laporan serah terimanya (berisi saldo akhir, rincian pengeluaran, daftar tunggakan, dan kolom tanda tangan), lalu pakai Alih kepemilikan untuk memindahkan kelas ke akun bendahara baru. Bendahara baru harus punya akunnya sendiri — jangan berbagi akun, karena begitu satu akun dipakai berdua, audit log berhenti bisa menjawab siapa yang mencatat apa.',
                    ],
                    [
                        'Saya lupa kata sandi / salah menulis email saat daftar.',
                        'Kalau emailnya benar, pakai fitur lupa kata sandi di halaman Masuk. Kalau emailnya yang salah ketik dan akunnya belum diverifikasi, daftar ulang saja dengan alamat yang benar — akun yang belum diverifikasi belum menyimpan data apa pun.',
                    ],
                ] as [$tanya, $jawab])
                    <div class="rounded-card border border-line bg-card">
                        <details class="group">
                            <summary class="flex cursor-pointer items-center justify-between gap-3 px-5 py-4">
                                <h3 class="font-bold">{{ $tanya }}</h3>
                                <x-icon name="kanan" class="size-5 shrink-0 text-ink-faint transition-transform group-open:rotate-90" />
                            </summary>
                            <p class="px-5 pb-4 text-sm text-ink-soft">{{ $jawab }}</p>
                        </details>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="mt-12 rounded-card border border-line bg-card p-6 text-center">
            <p class="text-lg font-bold">Masih bingung?</p>
            <p class="mt-1 text-sm text-ink-soft">
                Coba dulu di kelas demo — datanya contoh semua, jadi tidak ada yang bisa rusak.
            </p>
            <div class="mt-5 flex flex-wrap justify-center gap-3">
                <x-ui.button :href="route('demo')" size="lg">Buka kelas demo</x-ui.button>
                <x-ui.button :href="route('daftar')" variant="secondary" size="lg">Daftar sekarang</x-ui.button>
            </div>
        </div>
    </article>
</x-layouts.pemasaran>
