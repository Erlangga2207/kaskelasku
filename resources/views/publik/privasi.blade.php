{{--
    Ditulis supaya bisa dipahami siswa SMA, bukan supaya aman dibaca pengacara.
    Kalau kebijakan privasi hanya dimengerti orang yang sudah tahu isinya, dia
    tidak sedang memberi tahu siapa-siapa.
--}}
<x-layouts.pemasaran
    judul="Kebijakan Privasi — KasKelas"
    deskripsi="Penjelasan lengkap soal data apa yang disimpan KasKelas, siapa yang bisa melihatnya, berapa lama disimpan, dan bagaimana cara menghapusnya.">

    <article class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight">Kebijakan Privasi</h1>
        <p class="mt-2 text-sm text-ink-faint">Berlaku sejak {{ now()->translatedFormat('F Y') }}</p>

        <div class="mt-8 space-y-10 text-ink-soft">

            <section>
                <h2 class="text-xl font-bold text-ink">Singkatnya</h2>
                <ul class="mt-3 list-disc space-y-1.5 pl-5">
                    <li>Yang disimpan cuma nama siswa, nomor absen, dan catatan uang kas.</li>
                    <li>Data kamu <strong>tidak dijual</strong>, <strong>tidak dibagikan ke pihak ketiga</strong>, dan <strong>tidak dipakai untuk iklan</strong>.</li>
                    <li>Halaman kelas bisa dibuka siapa pun yang punya tautannya — jadi jangan sebar ke luar kelas.</li>
                    <li>Kamu bisa mengunduh semua datamu, atau menghapus kelasmu, kapan saja.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Data apa yang disimpan</h2>

                <h3 class="mt-4 font-bold text-ink">Tentang bendahara (yang punya akun)</h3>
                <ul class="mt-2 list-disc space-y-1.5 pl-5">
                    <li>Nama dan alamat email.</li>
                    <li>Kata sandi, disimpan dalam bentuk acak satu arah — tidak bisa dibaca kembali, termasuk oleh pengelola.</li>
                    <li>Catatan aktivitas: kapan masuk, dan perubahan data apa yang dilakukan, beserta alamat IP-nya.</li>
                </ul>

                <h3 class="mt-4 font-bold text-ink">Tentang siswa</h3>
                <ul class="mt-2 list-disc space-y-1.5 pl-5">
                    <li>Nama.</li>
                    <li>Nomor absen.</li>
                    <li>Tanggal mulai dan berhenti ikut iuran.</li>
                    <li>Catatan pembayaran: tanggal, jumlah, dan tagihan mana yang dilunasi.</li>
                </ul>

                <p class="mt-3">
                    Itu saja. <strong class="text-ink">Tidak ada NIS, nomor HP, alamat rumah, tanggal lahir,
                    nama orang tua, atau foto.</strong> Kolomnya memang tidak dibuat di aplikasi ini, jadi
                    bendahara tidak bisa memasukkannya sekalipun ingin. Ini keputusan yang disengaja: data
                    yang tidak pernah dikumpulkan adalah data yang tidak mungkin bocor.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Siapa yang bisa melihat</h2>

                <h3 class="mt-4 font-bold text-ink">Bendahara kelas</h3>
                <p class="mt-2">
                    Melihat seluruh data kelasnya sendiri, dan hanya kelasnya sendiri. Bendahara kelas lain
                    tidak bisa melihat data kelasmu — pemisahannya dilakukan di tingkat basis data, bukan
                    hanya dengan menyembunyikan tombol.
                </p>

                <h3 class="mt-4 font-bold text-ink">Anggota kelas</h3>
                <p class="mt-2">
                    Lewat tautan kelas, tanpa login. Yang tampil hanya nama, nomor absen, nominal, dan status
                    bayar. Bukti transfer, catatan pembayaran, dan data akun bendahara tidak pernah muncul di
                    halaman itu.
                </p>

                <h3 class="mt-4 font-bold text-ink">Yang perlu kamu sadari</h3>
                <p class="mt-2">
                    Tautan kelas berisi kode acak panjang dan tidak bisa ditebak, tapi
                    <strong class="text-ink">siapa pun yang memegang tautan itu bisa membukanya</strong> —
                    termasuk orang di luar kelas kalau tautannya diteruskan. Kalau itu terjadi, bendahara bisa
                    mengganti tautannya dari menu Pengaturan, dan tautan lama langsung mati.
                </p>

                <h3 class="mt-4 font-bold text-ink">Pengelola aplikasi</h3>
                <p class="mt-2">
                    Pengelola bisa melihat jumlah kelas, jumlah pengguna, jumlah transaksi, dan kapan sebuah
                    kelas terakhir dipakai — <strong class="text-ink">bukan isi transaksinya</strong>. Halaman
                    admin memang tidak punya jalan menuju detail transaksi kelas mana pun. Secara teknis
                    pengelola tetap punya akses ke basis data (itu berlaku untuk layanan web mana pun), tapi
                    tidak ada alur di aplikasi ini yang menampilkannya.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Yang tidak kami lakukan</h2>
                <ul class="mt-3 list-disc space-y-1.5 pl-5">
                    <li>Tidak menjual data ke siapa pun, dalam bentuk apa pun.</li>
                    <li>Tidak membagikan data ke pihak ketiga untuk kepentingan mereka.</li>
                    <li>Tidak memakai data untuk iklan, dan tidak menayangkan iklan.</li>
                    <li>Tidak memasang pelacak pihak ketiga seperti Google Analytics atau piksel media sosial.</li>
                </ul>
                <p class="mt-3">
                    Satu-satunya pihak luar yang terlibat adalah penyedia hosting tempat aplikasi ini berjalan
                    dan layanan pengiriman email untuk verifikasi — keduanya menyimpan data karena memang
                    dibutuhkan agar aplikasi bisa jalan, bukan untuk dipakai sendiri.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Berapa lama disimpan</h2>
                <ul class="mt-3 list-disc space-y-1.5 pl-5">
                    <li>Data kelas disimpan selama kelasnya masih ada.</li>
                    <li>Kelas yang dihapus masuk masa tenggang <strong class="text-ink">{{ config('kaskelas.daur.tenggang_hapus_hari') }} hari</strong> — masih bisa dipulihkan. Setelah itu dihapus permanen beserta seluruh isinya.</li>
                    <li>Kelas tanpa transaksi selama {{ config('kaskelas.daur.nonaktif_setelah_bulan') }} bulan ditandai nonaktif, tapi datanya tidak dihapus.</li>
                    <li>Catatan aktivitas (audit log) ikut terhapus saat kelasnya dihapus permanen.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Cara mengambil atau menghapus data</h2>

                <h3 class="mt-4 font-bold text-ink">Mengunduh semua data</h3>
                <p class="mt-2">
                    Masuk, buka menu <strong class="text-ink">Ekspor</strong>, lalu unduh berkas CSV-nya.
                    Berkas itu bisa langsung dibuka di Excel atau Google Sheets. Tidak perlu minta izin dan
                    tidak perlu menunggu.
                </p>

                <h3 class="mt-4 font-bold text-ink">Menghapus kelas</h3>
                <p class="mt-2">
                    Buka <strong class="text-ink">Pengaturan → Hapus kelas</strong>. Kelasnya langsung hilang
                    dari tampilan, dan benar-benar terhapus setelah masa tenggang.
                </p>

                <h3 class="mt-4 font-bold text-ink">Kalau kamu siswa dan ingin namamu dihapus</h3>
                <p class="mt-2">
                    Minta ke bendahara kelasmu — dia yang memegang data kelas dan bisa menghapusnya langsung.
                    Kalau ada kendala, hubungi pengelola lewat alamat di bawah.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Keamanan</h2>
                <p class="mt-2">
                    Koneksi ke aplikasi memakai HTTPS. Kata sandi disimpan dalam bentuk acak satu arah.
                    Data antar kelas dipisah di tingkat basis data, dan setiap perubahan tercatat di audit log.
                </p>
                {{--
                    Tidak ada klaim "100% aman" atau "terenkripsi end-to-end".
                    Keduanya tidak benar untuk aplikasi web biasa, dan klaim
                    keamanan palsu adalah masalah hukum, bukan sekadar berlebihan.
                --}}
                <p class="mt-3">
                    Yang tidak bisa kami janjikan: tidak ada layanan online yang bisa mengaku seratus persen
                    aman, dan aplikasi ini tidak memakai enkripsi ujung-ke-ujung — datanya bisa dibaca server
                    agar bisa dihitung dan ditampilkan. Karena itu jangan pernah menyimpan informasi sensitif
                    di kolom catatan, dan simpan salinan CSV secara berkala.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Anak di bawah umur</h2>
                <p class="mt-2">
                    Aplikasi ini memuat nama siswa, yang sebagian besar di bawah 18 tahun. Yang membuat akun
                    dan memasukkan data adalah bendahara kelas, dan dialah yang bertanggung jawab memastikan
                    penggunaannya wajar serta hanya membagikan tautan kelas ke anggota kelas. Kalau ada orang
                    tua atau wali yang keberatan namanya anaknya tercantum, sampaikan ke bendahara kelas atau
                    ke pengelola, dan namanya akan dihapus.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Kalau kebijakan ini berubah</h2>
                <p class="mt-2">
                    Perubahan penting akan diberitahukan lewat pengumuman di aplikasi sebelum berlaku.
                    Kalau kamu tidak setuju, kamu bisa mengunduh datamu lalu menghapus kelasmu.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Menghubungi kami</h2>
                <p class="mt-2">
                    Pertanyaan soal data bisa dikirim ke
                    <a class="font-semibold text-brand underline-offset-2 hover:underline"
                       href="mailto:halo@kaskelasku.my.id">halo@kaskelasku.my.id</a>.
                </p>
            </section>
        </div>
    </article>
</x-layouts.pemasaran>
