# PROGRESS — KasKelas v1

Catatan posisi pengerjaan terhadap `TASKS.md`. Diperbarui 17 September 2026.

## STATUS SAAT INI

**Fase 0–5 selesai, dan Fase 7 (iuran insidental) selesai di kode.**
Fase 6 — deploy ke Hostinger lalu memakainya sebulan penuh di kelasmu — masih
terbuka, dan itu tetap tindakan yang hanya bisa kamu lakukan sendiri. Panduan
langkahnya ada di `DEPLOY.md`.

Fase 7 dikerjakan lebih dulu atas permintaan sendiri, di branch
`feat/fase-7-iuran-insidental` dan belum di-deploy. Perlu diingat alasan urutan
aslinya: fitur baru di atas produk yang belum dipakai siapa pun menambah hal yang
harus dirawat, jadi sebaiknya v1 tetap yang lebih dulu naik ke produksi.

```
php artisan test          # 125 test, 676 assertion — semua hijau (termasuk Fase 7)
```

---

## Yang sudah dikerjakan

### Fase 0 — Verifikasi lingkungan
- [x] Repo Git + `.gitignore` Laravel standar, satu branch per fase
- [x] MySQL 8.0.30 lokal terverifikasi (memenuhi syarat `CHECK` constraint)
- [x] PHP 8.3.13 + Composer 2.8.3 lokal terverifikasi
- [ ] **Cek versi PHP/MySQL, SSH, dan document root di hPanel Hostinger** — tabel
      pengisiannya sudah disiapkan di `DEPLOY.md` bagian 1
- [ ] Kunci pilihan domain (masih pertanyaan terbuka di PRD bagian 17)

### Fase 1 — Fondasi & Tenancy
- [x] Laravel 12 + Tailwind v4 + Alpine (tanpa Livewire/Inertia/Vue/React)
- [x] Migrasi seluruh tabel v1 sesuai `schema.sql`, termasuk `classroom_id`
- [x] Model + relasi Eloquent
- [x] Trait `BelongsToClassroom` + `ClassroomScope` yang **gagal-tertutup**
- [x] `CurrentClassroom` — kelas aktif dari session bendahara atau token publik
- [x] Auth bendahara: login, logout, rate limit 5x/menit, regenerasi session
- [x] Middleware `kelas` (session) dan `kelas.token` (halaman publik)
- [x] `AuditObserver` mencatat create/update/delete/restore beserta `classroom_id`
- [x] Seeder: **dua** kelas dari dua sekolah, 5 siswa per kelas
- [x] Test isolasi pertama

### Fase 2 — Data Master
- [x] CRUD siswa; yang sudah bertransaksi dinonaktifkan, bukan dihapus
- [x] Input massal dengan tempel daftar nama (membaca "1. Nama", "2) Nama", dll.)
- [x] Pengaturan kelas; tipe periode terkunci setelah ada periode/transaksi
- [x] Generate periode otomatis sampai akhir tahun ajaran (Juli–Juni)
- [x] Generate `bills` untuk siswa aktif per periode
- [x] Periode libur tidak menghasilkan tagihan, dan tidak bisa ditandai libur
      kalau tagihannya sudah dibayar
- [x] Ubah nominal satu periode tanpa menyentuh periode lain
- [x] Test isolasi modul siswa & periode

### Fase 3 — Transaksi
- [x] `KasService`: alokasi otomatis ke tagihan terlama yang belum lunas
- [x] Form pembayaran: pilih siswa → tagihan belum lunas tampil → alokasi otomatis
      atau diatur sendiri
- [x] Kelebihan bayar jadi deposit dan terpakai otomatis saat periode berikutnya dibuat
- [x] Upload bukti: maks 2 MB, jpg/png/pdf, validasi MIME asli, nama diacak,
      disimpan di `storage/app/private/kelas-{id}/`, diakses lewat controller
- [x] Hapus pembayaran: soft delete, alokasi dilepas, status tagihan pulih
- [x] CRUD pengeluaran + kategori, ditolak server bila melebihi saldo kas
- [x] Pembebasan tagihan dengan alasan wajib
- [x] Denda: mode tetap & harian, masa tenggang, batas maksimum — default nonaktif
- [x] Test logika uang lengkap (PRD 14.2)
- [x] Test isolasi modul pembayaran & pengeluaran

### Fase 4 — Pelaporan
- [x] Dashboard: saldo, total masuk, total keluar, jumlah penunggak
- [x] Daftar tunggakan, urut dari terbesar
- [x] Rekap per periode (tertagih / terkumpul / sisa / lunas)
- [x] Riwayat transaksi dengan filter tanggal
- [x] Ekspor laporan PDF (dompdf, lengkap dengan kolom tanda tangan)
- [x] Halaman audit log read-only
- [x] Test: saldo dashboard dibandingkan dengan `SUM(payments) − SUM(expenses)`
      langsung lewat SQL, di luar global scope

### Fase 5 — Halaman Kelas & PWA
- [x] Grup route publik terpisah, hanya GET, tanpa middleware auth
- [x] Token 40 karakter acak per kelas + tombol ganti tautan
- [x] Token tak dikenal & kelas nonaktif → 404 polos
- [x] Header `X-Robots-Tag` + meta noindex + `robots.txt`
- [x] Halaman kelas: saldo, rekap periode, status bayar per siswa
- [x] Bukti transfer, catatan pembayaran, dan data akun tidak menyentuh halaman kelas
- [x] `manifest.webmanifest` global + manifest per kelas, ikon 192/512/maskable
- [x] Service worker: aset statis cache-first, halaman data network-first
- [x] Test isolasi token
- [ ] **Uji pasang ke home screen Android** — hanya bisa diuji di HP asli

---

## Fase 6 — sisa v1 (giliranmu)

Semua yang bisa disiapkan dari kode sudah beres:
- [x] Paksa skema HTTPS saat `APP_ENV=production` (`AppServiceProvider`)
- [x] `.env.example` menandai `APP_DEBUG=false` dan `SESSION_SECURE_COOKIE=true`
      untuk produksi
- [x] `DEPLOY.md` berisi langkah lengkap, termasuk jalur kalau SSH tidak tersedia

Yang harus kamu kerjakan:
- [ ] Deploy ke Hostinger, document root → `public/`
- [ ] Backup database manual pertama lewat phpMyAdmin, lalu **uji restore-nya**
- [ ] Uji seluruh alur utama di HP asli
- [ ] Pakai untuk kelasmu sendiri minimal satu bulan penuh

---

## Fase 7 — v1.1: Iuran Insidental (branch `feat/fase-7-iuran-insidental`)

Selesai di kode, **belum di-deploy**. `php artisan test` hijau seluruhnya.

- [x] Migrasi terpisah `2026_02_01_000100_create_campaigns_table` — tabel `campaigns`,
      FK `bills.campaign_id`, FK + index `expenses.campaign_id`. Migrasi v1 tidak
      disentuh sama sekali karena kelas nyata sudah berisi data.
- [x] CRUD campaign + pemilihan peserta (bawaan seluruh siswa aktif, bisa dikurangi)
- [x] Tagihan campaign: `period_id` NULL, `campaign_id` terisi
- [x] Mesin alokasi pembayaran **tidak diubah** — lihat catatan di bawah
- [x] Satu pembayaran bisa dipecah ke campuran tagihan rutin dan campaign
- [x] Dashboard & halaman pengeluaran memisahkan saldo bebas vs dana campaign
- [x] Pengeluaran bisa ditandai milik campaign; laporan terkumpul/terpakai/sisa
- [x] Campaign dibatalkan: tagihan ditarik, pembayaran utuh, sisanya jadi deposit
- [x] Progres campaign tampil di halaman kelas publik
- [x] Test alur nyata + test isolasi tenant modul campaign

### Keputusan yang diambil di fase ini

**Mesin alokasi benar-benar tidak berubah.** Yang ditambahkan ke `KasService`
hanya bagian baru (penerbitan tagihan campaign + hitungan dananya). Lima method
inti — `tagihanBelumLunas`, `alokasikanDeposit`, `alokasikanManual`,
`batalkanAlokasi`, `hapusPembayaran` — tidak berubah satu baris pun. Akibatnya
yang perlu diketahui: tagihan campaign (tanpa `jatuh_tempo` periode) selalu
diurutkan paling belakang, jadi alokasi otomatis menutup tunggakan rutin dulu.
Kalau uang yang diterima memang khusus untuk campaign, pakai alokasi manual di
form pembayaran.

**Jatuh tempo tagihan campaign = `campaigns.deadline`.** Dua filter pelaporan
(`tunggakanSiswa` dan `daftarTunggakan`) sebelumnya menganggap setiap tagihan
tanpa periode sudah jatuh tempo. Tanpa perubahan itu, membuat campaign akan
langsung melonjakkan jumlah penunggak di dashboard walau batas waktunya masih
sebulan lagi. Campaign tanpa deadline tidak pernah dihitung sebagai tunggakan.
Denda tetap hanya berlaku untuk iuran rutin.

**Saldo terbagi dua, dan pembagiannya dipaksakan server.** Pengeluaran bertanda
campaign dibatasi sisa dana campaign itu; pengeluaran biasa dibatasi saldo bebas
(kas − seluruh sisa dana campaign). Uang studi tour tidak bisa habis untuk beli
spidol tanpa ada yang sadar.

**Yang sengaja dikunci:**
- Nominal per siswa tidak bisa diubah setelah campaign menerima pembayaran.
- Peserta yang tagihannya sudah dibayar tidak bisa dikeluarkan dari campaign.
- Campaign yang sudah punya pengeluaran tidak bisa dibatalkan (uangnya sudah tidak
  ada di kas, jadi tidak bisa dikembalikan jadi deposit) — tandai selesai saja.
- Campaign hanya bisa dihapus permanen selama belum tersentuh uang; selebihnya
  dibatalkan, supaya jejaknya tetap ada di audit log.

### Rehearsal migrasi

Migrasi diuji di atas **salinan database lokal yang sudah berisi data v1**
(95 tagihan, 3 pengeluaran): naik → turun → naik, semuanya bersih. CHECK
constraint `chk_bills_sumber` tetap hidup setelah foreign key campaign dipasang,
dan itu diuji langsung lewat test (insert dengan dua sumber sekaligus → ditolak
database).

Database lokal `kaskelas` **belum** dimigrasikan — jalankan sendiri:

```
php artisan migrate
```

---

## Catatan teknis yang perlu diingat

**Uang.** Semua hitungan dilakukan dalam satuan sen (integer) lewat
`App\Support\Uang`, lalu dikembalikan ke string `DECIMAL(12,2)` saat disimpan.
Tidak ada `float` di mana pun, dan sengaja tidak memakai `bcmath` karena ekstensi
itu belum tentu ada di shared hosting.

**Gagal-tertutup.** Kalau `CurrentClassroom` kosong, global scope memaksa query
mengembalikan nol baris (`where 1 = 0`), bukan seluruh baris. Halaman kosong bisa
diperbaiki; data kelas lain yang bocor tidak bisa ditarik kembali.

**`classroom_id` ditimpa, bukan diisi kalau kosong.** Trait `BelongsToClassroom`
menimpa nilainya dari kelas aktif pada setiap `creating`, dan menolak perubahan
`classroom_id` pada `updating`. Jadi hidden input yang diutak-atik tidak berefek.

**Kategori pengeluaran bawaan lahir dari migrasi**, bukan seeder
(`2026_01_01_001100_seed_kategori_pengeluaran_bawaan.php`). Seeder tidak selalu
dijalankan di produksi, sedangkan tanpa kategori pengeluaran pertama tidak bisa
dicatat sama sekali.

**Service worker tidak pernah menyajikan halaman data dari cache** selama jaringan
masih bisa dihubungi. Saldo basi lebih berbahaya daripada halaman gagal dimuat.

**Pengingat tidak pernah mengirim apa pun.** Aplikasi hanya menyusun teksnya;
bendahara yang menyalin dan mengirim lewat WhatsApp. Ini keputusan, bukan
keterbatasan sementara: mengirim otomatis menuntut menyimpan nomor HP, dan nomor
HP siswa tidak boleh masuk aplikasi ini (UU PDP, PRD bagian 1).

**`PengingatService` tidak menghitung uang sendiri.** Sisa tagihan, denda, dan
"sudah jatuh tempo atau belum" semuanya ditanyakan ke `KasService`. Kalau suatu
saat angka di teks pengingat berbeda dari angka di laporan, berarti ada hitungan
kedua yang menyelinap masuk — itu bug, bukan selisih pembulatan.

**`{batas}` diisi bendahara, bukan diambil dari jatuh tempo tagihan.** Jatuh
tempo tiap periode sudah tertulis di `{rincian}`; yang dibutuhkan kalimat
penutup adalah tenggat baru yang disepakati saat menagih. Bawaannya sepekan dari
hari pengingat dibuat, dan bisa diganti di halaman Pengingat.

**QRIS disajikan lewat route bertoken, bukan disimpan di folder publik.**
Berkasnya ada di `storage/app/private/kelas-{id}/qris/` dengan nama acak, dan
satu-satunya pintu masuk adalah `GET /kelas/{token}/qris`. Menaruhnya di
`public/uploads` berarti siapa pun yang menebak URL-nya bisa membukanya tanpa
token kelas sama sekali.

**Halaman kelas hanya dilindungi token, dan token bisa tersebar.** Karena itu
form unggah QRIS memuat peringatan tegas: gambar itu akan terlihat siapa pun
yang memegang tautan kelas. Peringatannya ada di form, bukan di dokumentasi —
yang membaca dokumentasi hanya yang sudah curiga.


**Akun uji lokal** (dari `php artisan db:seed`):
`bendahara@kaskelas.test` / `RahasiaKuat123` — kelas XII TRPL 1, SMKN 1 Subang.
Kelas kedua (`bendahara-b@kaskelas.test`) sengaja ada supaya test isolasi punya
lawan tanding; jangan dihapus dari seeder.

**MySQL lokal** dinyalakan manual:
`C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe --datadir=C:\laragon\data\mysql-8`

---

## Pertanyaan terbuka yang belum terjawab (PRD bagian 17)

1. Domain belum dikunci: `kaskelasku.com` atau `kaskelas.app`.
2. Model biaya setelah v2.0 belum diputuskan. Harus diputuskan **sebelum**
   membuka pendaftaran, bukan setelah ada 50 kelas.
3. Peran wali kelas sebagai pengawas read-only — ditunda sampai ada permintaan nyata.
