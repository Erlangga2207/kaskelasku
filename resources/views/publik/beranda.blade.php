{{--
    Beranda: Blade statis, tanpa framework JS.

    Halaman ini dibuka dari tautan WhatsApp di jaringan seluler yang sering
    lambat. Tidak ada gambar besar di atas lipatan, tidak ada skrip yang
    memblokir render, dan teks hero-nya HTML biasa — jadi yang menentukan LCP
    hanya CSS dan font. Font dimuat dengan display=swap supaya teks tampil
    lebih dulu daripada fontnya.
--}}
<x-layouts.pemasaran
    judul="KasKelas — Aplikasi Kas Kelas Online Gratis untuk Bendahara"
    deskripsi="Aplikasi pencatatan uang kas kelas online yang gratis. Catat iuran, pengeluaran, dan tunggakan, lalu bagikan rekapnya ke grup kelas lewat satu tautan — anggota kelas tidak perlu punya akun."
    :json-ld="$jsonLd">

    {{-- ================= Hero ================= --}}
    <section class="border-b border-line bg-card">
        <div class="mx-auto max-w-5xl px-4 py-14 sm:px-6 sm:py-20">
            <div class="max-w-2xl">
                {{-- SATU H1 di seluruh halaman. --}}
                <h1 class="text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl lg:text-5xl">
                    Catat uang kas kelas tanpa ribut di grup
                </h1>

                <p class="mt-4 text-lg text-ink-soft">
                    KasKelas adalah aplikasi pencatatan uang kas kelas online untuk bendahara.
                    Catat iuran dan pengeluaran sekali, lalu bagikan satu tautan ke grup kelas —
                    semua anggota bisa melihat sendiri saldo, rekap, dan siapa yang belum bayar.
                </p>

                <div class="mt-7 flex flex-wrap items-center gap-3">
                    @if ($kuotaPenuh)
                        <x-ui.button :href="route('daftar-tunggu')" size="lg">Masuk daftar tunggu</x-ui.button>
                    @else
                        <x-ui.button :href="route('daftar')" size="lg">Mulai gratis</x-ui.button>
                    @endif
                    <x-ui.button :href="route('demo')" variant="secondary" size="lg">Lihat kelas demo</x-ui.button>
                </div>

                <p class="mt-4 text-sm text-ink-faint">
                    Gratis &middot; tanpa iklan &middot; anggota kelas tidak perlu punya akun
                </p>
            </div>
        </div>
    </section>

    {{-- ================= Masalah ================= --}}
    <section aria-labelledby="masalah" class="mx-auto max-w-5xl px-4 py-14 sm:px-6">
        <h2 id="masalah" class="text-2xl font-extrabold tracking-tight">Kenapa kas kelas sering bermasalah</h2>
        <p class="mt-2 max-w-2xl text-ink-soft">
            Bukan karena bendaharanya tidak jujur. Biasanya karena catatannya ada di satu buku tulis
            yang hanya dipegang satu orang.
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['Catatan cuma di buku bendahara', 'Kalau bukunya hilang, ketinggalan, atau bendaharanya sakit, tidak ada yang tahu posisi uang kas.'],
                ['Ditanya terus di grup', '"Saya sudah bayar belum ya?" muncul tiap minggu, dan bendahara harus membuka catatan satu per satu.'],
                ['Serah terima berantakan', 'Ganti bendahara berarti menyalin ulang catatan setahun, dan sering ada selisih yang tidak bisa dijelaskan.'],
            ] as [$judul, $isi])
                <article class="rounded-card border border-line bg-card p-5">
                    <h3 class="font-bold">{{ $judul }}</h3>
                    <p class="mt-1.5 text-sm text-ink-soft">{{ $isi }}</p>
                </article>
            @endforeach
        </div>
    </section>

    {{-- ================= Cara kerja ================= --}}
    <section aria-labelledby="cara-kerja" class="border-y border-line bg-card">
        <div class="mx-auto max-w-5xl px-4 py-14 sm:px-6">
            <h2 id="cara-kerja" class="text-2xl font-extrabold tracking-tight">Cara kerjanya, tiga langkah</h2>

            <ol class="mt-8 grid gap-6 sm:grid-cols-3">
                @foreach ([
                    ['Siapkan kelas', 'Buat kelas, tempel daftar absen sekaligus, lalu tentukan iuran per bulan atau per minggu. Tagihannya dibuat otomatis.'],
                    ['Catat yang masuk dan keluar', 'Pilih siswa, masukkan jumlah uangnya. Pembayaran rapel dan cicilan langsung terbagi ke tagihan yang tepat.'],
                    ['Bagikan tautannya', 'Kirim satu tautan ke grup kelas. Anggota kelas melihat sendiri saldo dan status bayarnya, tanpa login dan tanpa bisa mengubah apa pun.'],
                ] as $i => [$judul, $isi])
                    <li>
                        <span class="flex size-9 items-center justify-center rounded-xl bg-brand-soft font-extrabold text-brand-soft-ink">
                            {{ $i + 1 }}
                        </span>
                        <h3 class="mt-3 font-bold">{{ $judul }}</h3>
                        <p class="mt-1.5 text-sm text-ink-soft">{{ $isi }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ================= Fitur (ditulis sebagai manfaat) ================= --}}
    <section aria-labelledby="fitur" class="mx-auto max-w-5xl px-4 py-14 sm:px-6">
        <h2 id="fitur" class="text-2xl font-extrabold tracking-tight">Yang bisa kamu lakukan</h2>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['bayar', 'Uang rapel langsung terbagi sendiri', 'Bayar tiga bulan sekaligus? Cukup masukkan jumlahnya — sistem yang membagi ke bulan mana saja. Kelebihannya disimpan dan otomatis dipakai bulan depan.'],
                ['laporan', 'Saldo yang tidak pernah salah hitung', 'Saldo, tunggakan, dan rekap per periode selalu dihitung ulang dari transaksinya. Tidak ada angka simpanan yang bisa basi.'],
                ['surat', 'Nagih tanpa canggung', 'Aplikasi menyusun teks pengingat lengkap dengan rincian dan totalnya. Tinggal salin, tempel ke WhatsApp. Pengirimannya tetap kamu yang pegang.'],
                ['dompet', 'Iuran dadakan tidak bercampur', 'Uang study tour dan uang kas harian dipisah otomatis, jadi kas rutin tidak ikut terpakai untuk acara.'],
                ['audit', 'Semua perubahan ada jejaknya', 'Setiap pencatatan, perubahan, dan penghapusan tersimpan lengkap dengan waktu dan pelakunya. Tidak ada yang bisa diubah diam-diam.'],
                ['kunci', 'Serah terima yang rapi', 'Tutup buku di akhir semester, cetak laporan serah terima dengan kolom tanda tangan, lalu alihkan kelas ke bendahara berikutnya.'],
            ] as [$ikon, $judul, $isi])
                <article class="rounded-card border border-line bg-card p-5">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-brand-soft text-brand-soft-ink">
                        <x-icon :name="$ikon" class="size-5" />
                    </span>
                    <h3 class="mt-3 font-bold">{{ $judul }}</h3>
                    <p class="mt-1.5 text-sm text-ink-soft">{{ $isi }}</p>
                </article>
            @endforeach
        </div>
    </section>

    {{-- ================= Demo ================= --}}
    <section aria-labelledby="demo" class="border-y border-line bg-card">
        <div class="mx-auto max-w-5xl px-4 py-14 sm:px-6">
            <div class="max-w-2xl">
                <h2 id="demo" class="text-2xl font-extrabold tracking-tight">Lihat dulu sebelum daftar</h2>
                <p class="mt-2 text-ink-soft">
                    Ada kelas demo berisi data contoh — dua puluh siswa fiktif, sebagian lunas,
                    sebagian menunggak, dan satu iuran study tour yang sedang berjalan. Persis seperti
                    yang akan dilihat anggota kelasmu nanti.
                </p>
                <div class="mt-6">
                    <x-ui.button :href="route('demo')" size="lg">Buka kelas demo</x-ui.button>
                </div>
            </div>
        </div>
    </section>

    {{-- ================= Transparansi data ================= --}}
    <section aria-labelledby="data" class="mx-auto max-w-5xl px-4 py-14 sm:px-6">
        <h2 id="data" class="text-2xl font-extrabold tracking-tight">Terus terang soal data</h2>
        <p class="mt-2 max-w-2xl text-ink-soft">
            Aplikasi ini memegang data anak orang. Ini yang perlu kamu tahu sebelum memakainya —
            termasuk bagian yang kurang enak didengar.
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <article class="rounded-card border border-line bg-card p-5">
                <h3 class="font-bold">Yang disimpan sesedikit mungkin</h3>
                <p class="mt-1.5 text-sm text-ink-soft">
                    Hanya nama, nomor absen, dan status iuran. Tidak ada NIS, nomor HP, alamat, atau foto —
                    kolomnya memang tidak dibuat, jadi tidak bisa diisi sekalipun kamu mau.
                </p>
            </article>

            <article class="rounded-card border border-line bg-card p-5">
                <h3 class="font-bold">Tidak dijual, tidak dibagikan</h3>
                <p class="mt-1.5 text-sm text-ink-soft">
                    Data kelasmu tidak dijual, tidak dibagikan ke pihak ketiga, dan tidak dipakai untuk iklan.
                    Tidak ada iklan sama sekali di aplikasi ini.
                </p>
            </article>

            {{--
                Bagian ini sengaja menyebut kelemahannya. Menjanjikan keamanan
                yang tidak benar-benar ada bukan cuma tidak jujur — untuk layanan
                yang memegang data anak-anak, itu masalah hukum.
            --}}
            <article class="rounded-card border border-tunggak-soft bg-tunggak-soft p-5">
                <h3 class="font-bold text-tunggak-soft-ink">Tautan kelas = kuncinya</h3>
                <p class="mt-1.5 text-sm text-tunggak-soft-ink">
                    Halaman kelas dibuka lewat tautan bertoken acak, tanpa login. Artinya
                    <strong>siapa pun yang memegang tautan itu bisa melihat isinya</strong>. Bagikan hanya ke
                    grup kelas, dan kalau telanjur tersebar, bendahara bisa mengganti tautannya kapan saja.
                </p>
            </article>

            <article class="rounded-card border border-line bg-card p-5">
                <h3 class="font-bold">Datanya bisa dibawa pulang</h3>
                <p class="mt-1.5 text-sm text-ink-soft">
                    Seluruh data kelas bisa diunduh sebagai CSV kapan saja, tanpa minta izin. Kelas juga bisa
                    dihapus sendiri, dengan tenggang 30 hari sebelum benar-benar permanen.
                </p>
            </article>
        </div>

        <p class="mt-6 text-sm text-ink-faint">
            Selengkapnya di <a class="font-semibold text-brand underline-offset-2 hover:underline" href="{{ route('privasi') }}">Kebijakan Privasi</a>.
        </p>
    </section>

    {{-- ================= FAQ ================= --}}
    <section id="faq" aria-labelledby="faq-judul" class="border-t border-line bg-card">
        <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
            <h2 id="faq-judul" class="text-2xl font-extrabold tracking-tight">Pertanyaan yang sering ditanya</h2>

            {{-- Isi di sini datang dari sumber yang sama dengan JSON-LD FAQPage
                 di <head>, jadi keduanya mustahil berbeda. --}}
            <dl class="mt-8 space-y-3">
                @foreach ($faq as $item)
                    <div class="rounded-card border border-line bg-surface">
                        <details class="group">
                            <summary class="flex cursor-pointer items-center justify-between gap-3 px-5 py-4">
                                <dt class="font-bold">{{ $item['tanya'] }}</dt>
                                <x-icon name="kanan" class="size-5 shrink-0 text-ink-faint transition-transform group-open:rotate-90" />
                            </summary>
                            <dd class="px-5 pb-4 text-sm text-ink-soft">{{ $item['jawab'] }}</dd>
                        </details>
                    </div>
                @endforeach
            </dl>

            <div class="mt-10 rounded-card border border-line bg-surface p-6 text-center">
                <p class="text-lg font-bold">Siap merapikan kas kelasmu?</p>
                <p class="mt-1 text-sm text-ink-soft">Gratis, dan datanya bisa kamu bawa pergi kapan saja.</p>
                <div class="mt-5 flex flex-wrap justify-center gap-3">
                    @if ($kuotaPenuh)
                        <x-ui.button :href="route('daftar-tunggu')" size="lg">Masuk daftar tunggu</x-ui.button>
                    @else
                        <x-ui.button :href="route('daftar')" size="lg">Buat kelas pertama</x-ui.button>
                    @endif
                    <x-ui.button :href="route('panduan')" variant="secondary" size="lg">Baca panduan</x-ui.button>
                </div>
            </div>
        </div>
    </section>
</x-layouts.pemasaran>
