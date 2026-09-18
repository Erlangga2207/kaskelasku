<x-layouts.pemasaran
    judul="Syarat Layanan — KasKelas"
    deskripsi="Syarat penggunaan KasKelas: layanan gratis, disediakan apa adanya, dan pengguna dianjurkan mengekspor datanya secara berkala.">

    <article class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight">Syarat Layanan</h1>
        <p class="mt-2 text-sm text-ink-faint">Berlaku sejak {{ now()->translatedFormat('F Y') }}</p>

        <div class="mt-8 space-y-10 text-ink-soft">

            <section>
                <h2 class="text-xl font-bold text-ink">Singkatnya</h2>
                <ul class="mt-3 list-disc space-y-1.5 pl-5">
                    <li>Gratis, tanpa iklan, tanpa versi berbayar.</li>
                    <li>Disediakan <strong>apa adanya</strong> — tidak ada jaminan selalu bisa diakses.</li>
                    <li><strong>Ekspor datamu secara berkala.</strong> Ini bukan basa-basi, ini permintaan serius.</li>
                    <li>Kamu bertanggung jawab atas data siswa yang kamu masukkan.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Layanan ini gratis</h2>
                <p class="mt-2">
                    KasKelas gratis dipakai. Tidak ada biaya berlangganan, tidak ada masa coba yang berakhir,
                    tidak ada fitur yang dikunci, dan tidak ada iklan.
                </p>
                <p class="mt-3">
                    Kalau suatu hari ada bagian yang harus berbayar agar layanannya bisa terus berjalan, itu
                    akan diumumkan jauh hari, dan data yang sudah ada tetap bisa diekspor gratis. Fitur yang
                    hari ini gratis tidak akan tiba-tiba dikunci di belakang tagihan.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Disediakan apa adanya</h2>
                <p class="mt-2">
                    Layanan ini diberikan <strong class="text-ink">sebagaimana adanya</strong>, tanpa jaminan
                    dalam bentuk apa pun. Tidak ada janji bahwa layanan akan selalu bisa diakses, bebas dari
                    kesalahan, atau cocok untuk keperluan tertentu.
                </p>
                <p class="mt-3">
                    Terus terang: aplikasi ini dibuat oleh satu orang dan berjalan di satu paket hosting
                    sederhana. Server bisa mati, hosting bisa bermasalah, dan bug bisa lolos. Sudah ada
                    cadangan data berkala dan sudah ada test otomatis, tapi itu tetap bukan jaminan.
                </p>
                <p class="mt-3">
                    Sejauh diizinkan hukum yang berlaku, pengelola tidak bertanggung jawab atas kerugian yang
                    timbul dari penggunaan atau ketidaktersediaan layanan ini —
                    <strong class="text-ink">termasuk kehilangan data</strong>.
                </p>
            </section>

            <section class="rounded-card border border-tunggak-soft bg-tunggak-soft p-5">
                <h2 class="text-xl font-bold text-tunggak-soft-ink">Ekspor datamu secara berkala</h2>
                <p class="mt-2 text-tunggak-soft-ink">
                    Karena layanan ini disediakan apa adanya, <strong>simpan salinan datamu sendiri</strong>.
                    Buka menu <strong>Ekspor</strong>, unduh CSV-nya, simpan di Google Drive atau di laptop.
                    Lakukan minimal sebulan sekali, dan wajib sebelum tutup buku atau serah terima bendahara.
                </p>
                <p class="mt-3 text-sm text-tunggak-soft-ink">
                    Uang kas kelas itu uang sungguhan milik puluhan orang. Catatannya tidak boleh hanya ada
                    di satu tempat — termasuk kalau tempat itu adalah aplikasi ini.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Tanggung jawab kamu sebagai bendahara</h2>
                <ul class="mt-3 list-disc space-y-1.5 pl-5">
                    <li>Memasukkan data siswa seperlunya saja, sesuai kolom yang disediakan.</li>
                    <li>Membagikan tautan kelas hanya kepada anggota kelas.</li>
                    <li>Menjaga kerahasiaan kata sandi akunmu, dan tidak memakai akun bersama orang lain.</li>
                    <li>Memastikan angka yang dicatat sesuai uang yang benar-benar diterima dan dikeluarkan.</li>
                    <li>Menghapus data siswa yang memintanya, atau meneruskan permintaan itu ke pengelola.</li>
                </ul>
                <p class="mt-3">
                    Aplikasi ini mencatat apa yang kamu masukkan. Kalau angkanya salah masuk, yang salah bukan
                    aplikasinya — dan yang diminta pertanggungjawaban oleh teman sekelas tetap kamu.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Yang tidak boleh dilakukan</h2>
                <ul class="mt-3 list-disc space-y-1.5 pl-5">
                    <li>Memakai layanan ini untuk hal yang melanggar hukum.</li>
                    <li>Memasukkan data pribadi sensitif di kolom catatan.</li>
                    <li>Mencoba mengakses data kelas yang bukan milikmu.</li>
                    <li>Membebani server dengan permintaan otomatis dalam jumlah besar.</li>
                </ul>
                <p class="mt-3">
                    Akun yang melanggar bisa dinonaktifkan. Kalau itu terjadi karena kekeliruan, hubungi
                    pengelola dan datamu tetap bisa diekspor.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Batas kapasitas</h2>
                <p class="mt-2">
                    Ada batas jumlah kelas per akun ({{ config('kaskelas.batas.kelas_per_akun') }} kelas),
                    jumlah siswa per kelas ({{ config('kaskelas.batas.siswa_per_kelas') }} siswa), dan jumlah
                    kelas di seluruh sistem. Batas itu ada karena keterbatasan server, bukan untuk mendorong
                    siapa pun membayar — tidak ada yang bisa dibayar.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Menghentikan pemakaian</h2>
                <p class="mt-2">
                    Kamu bisa berhenti kapan saja. Ekspor datamu lebih dulu, lalu hapus kelasmu dari menu
                    Pengaturan. Setelah masa tenggang
                    {{ config('kaskelas.daur.tenggang_hapus_hari') }} hari, datanya hilang permanen.
                </p>
                <p class="mt-3">
                    Kalau layanan ini suatu saat dihentikan, pemberitahuan akan diberikan minimal 30 hari
                    sebelumnya supaya semua orang sempat mengekspor datanya.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Hukum yang berlaku</h2>
                <p class="mt-2">
                    Syarat ini tunduk pada hukum Republik Indonesia, termasuk Undang-Undang Perlindungan Data
                    Pribadi.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-bold text-ink">Menghubungi kami</h2>
                <p class="mt-2">
                    <a class="font-semibold text-brand underline-offset-2 hover:underline"
                       href="mailto:halo@kaskelasku.my.id">halo@kaskelasku.my.id</a>
                </p>
            </section>
        </div>
    </article>
</x-layouts.pemasaran>
