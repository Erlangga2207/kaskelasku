# PROGRESS — KasKelas

Catatan posisi pengerjaan terhadap `TASKS.md`. Diperbarui 18 September 2026.

## STATUS SAAT INI

**Fase 0-5, 7, 8/9 (pengingat, QRIS, tutup buku), dan 10 selesai di kode.**
Fase 6 - deploy ke Hostinger lalu memakainya sebulan penuh di kelasmu - masih
terbuka, dan itu tetap tindakan yang hanya bisa kamu lakukan sendiri. Langkahnya
ada di `DEPLOY.md`, termasuk bagian 8b yang baru untuk v2.0 (SMTP, cron, demo).

```
php artisan test          # 276 test, 2.117 assertion - semua hijau
```

Catatan urutan: Fase 7 dan seterusnya dikerjakan lebih dulu atas permintaan
sendiri. Alasan urutan aslinya tetap berlaku - fitur baru di atas produk yang
belum dipakai siapa pun menambah hal yang harus dirawat. Sekarang seluruh v1,
v1.1, v1.2, dan v2.0 sudah ada di kode, dan yang tersisa justru satu-satunya
langkah yang tidak bisa diambil alih siapa pun: memakainya untuk uang sungguhan.

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

---

## Fase 10 — v2.0: Peluncuran Publik (branch `feat/fase-10-publik`)

Selesai di kode, **belum di-deploy**. `php artisan test` hijau seluruhnya.

```
php artisan test          # 276 test, 2.117 assertion — semua hijau
```

Domain yang dituju: `kaskelasku.my.id`.

### Pendaftaran & wizard

- [x] Pendaftaran bendahara (nama, email, kata sandi) + centang Syarat Layanan
- [x] Verifikasi email WAJIB sebelum kelas bisa dibuat — tautan bertanda tangan
      dan kedaluwarsa, isi emailnya ditulis ulang dalam bahasa Indonesia
- [x] Rate limit: daftar 5x/jam per IP, kirim ulang verifikasi 3x/10 menit
- [x] Wizard tiga langkah dengan urutan **dipaksa di server**: kelas → siswa → periode
- [x] Pemilih kelas aktif; hasilnya ke SESSION, tidak pernah ke URL
- [x] Batas kapasitas dari config: 100 kelas se-sistem, 5 kelas/akun, 60 siswa/kelas
- [x] Kuota se-sistem penuh → form pendaftaran diganti halaman daftar tunggu
      (hanya mengumpulkan email, tidak ada kolom lain)
- [x] Centang persetujuan data siswa saat buat kelas, waktunya ke `persetujuan_data_at`
- [x] Ekspor seluruh data kelas ke CSV (8 berkas, ber-BOM UTF-8 supaya Excel benar)
- [x] Hapus kelas bertenggang 30 hari + pemulihan; penghapusan permanen lewat cron
- [x] Kelas tanpa transaksi 12 bulan ditandai nonaktif lewat cron

### Landing page, SEO, dan halaman wajib

- [x] Beranda Blade statis tanpa framework JS (hero, masalah, 3 langkah, fitur,
      demo, transparansi data, FAQ, footer)
- [x] Satu H1, title & meta description bermuatan kata yang benar-benar dicari
- [x] Open Graph + Twitter Card lengkap dengan ukuran gambar
- [x] JSON-LD SoftwareApplication + FAQPage, lahir dari sumber FAQ yang SAMA
      dengan yang tampil di halaman
- [x] `sitemap.xml` + `robots.txt`: hanya beranda, panduan, privasi, syarat yang
      boleh diindeks; dashboard dan halaman kelas noindex di dua lapis
- [x] Canonical URL, HTTPS dipaksa saat `APP_ENV=production`
- [x] Kebijakan Privasi, Syarat Layanan, dan Panduan Penggunaan 10 langkah
      beserta bagian "Masalah yang sering terjadi"

### Kelas demo & admin platform

- [x] Kelas demo read-only 20 siswa fiktif: sebagian lunas, sebagian nyicil,
      sebagian menunggak, plus satu iuran insidental berjalan
- [x] Demo memakai halaman kelas publik yang memang hanya GET — bukan tiruan
      dashboard yang dibuat read-only
- [x] `kaskelas:reset-demo` membangun ULANG, bukan menambal; dijadwalkan harian
- [x] Dashboard admin platform agregat saja, tanpa satu pun jalan menuju detail
      transaksi kelas mana pun

### Keputusan yang diambil di fase ini

**Gerbang penyiapan ada di middleware, bukan di controller.** `PastikanSetupSelesai`
memantulkan setiap halaman yang angkanya baru masuk akal setelah penyiapan
selesai (Bayar, Keluar, Laporan, Audit, Pengingat, Tutup buku). Siswa, Periode,
dan Pengaturan sengaja TIDAK ikut digerbang — justru di sanalah penyiapannya
diselesaikan. Penjagaan lama di `PaymentController::create()` dihapus: ia hanya
menutup satu route, sedangkan URL yang lain tetap bisa diketik langsung.

**Yang berbahaya bukan "tidak ada periode", tapi "tidak ada tagihan sama sekali".**
Karena itu gerbangnya memakai `punyaSumberTagihan()`: periode non-libur ATAU
campaign hidup. Kelas yang sudah menerbitkan tagihan campaign tidak perlu
dipantulkan lagi — pembayarannya tidak akan jadi deposit menggantung.

**Akun serah terima langsung dianggap terverifikasi.** Biayanya diakui: alamat
emailnya memang belum dibuktikan. Alternatifnya lebih buruk — bendahara lama
sudah dicabut aksesnya di transaksi yang sama, jadi kalau bendahara baru
tertahan di halaman verifikasi dan emailnya ternyata salah ketik, kelas itu
tidak punya satu pun orang yang bisa membukanya lagi.

**Admin platform bukan kunci induk.** Perannya hanya membuka satu halaman angka
gabungan. Di kelas orang lain dia tetap dijawab 404 seperti orang asing, dan itu
diuji — bukan sekadar dijanjikan lewat komentar.

### Bug yang ditemukan dan diperbaiki saat menulis test fase ini

Semuanya lolos dari pembacaan mata, dan baru muncul begitu ditembak test:

1. **Beranda dan seluruh halaman pemasaran ber-`noindex`.** Kelimanya masih
   menunjuk `layouts.publik` (layout halaman kelas) setelah layout pemasaran
   dipisah ke `layouts.pemasaran`. Akibatnya: tanpa Open Graph, tanpa JSON-LD,
   dan justru menyuruh mesin pencari TIDAK mengindeks — seluruh pekerjaan SEO
   di fase ini jadi nol tanpa satu pun gejala yang terlihat.
2. **Form daftar tunggu 500 saat dikirim.** `created_at` dioper lewat mass
   assignment padahal tidak `$fillable`.
3. **`runFor()` di dalam `withoutTenancy()` tidak memulihkan tenancy.** Baris
   baru lahir dengan `classroom_id` kosong dan query-nya melihat seluruh kelas.
   Sekarang `runFor()` mematikan bypass selama callback berjalan — "jalankan
   sebagai kelas X" dan "lewati batas kelas" adalah dua permintaan berlawanan,
   dan yang lebih spesifik yang menang.
4. **`kaskelas:reset-demo` gagal total**: mass assignment `is_active`, lalu
   `created_by` NULL karena perintah artisan tidak punya user yang masuk.
5. **Nominal demo seperti Rp 55.896,92**: `diffInMonths()` mengembalikan
   pecahan. Sekarang dihitung dari jumlah periode yang benar-benar terbentuk.
6. **`verified` bawaan Laravel memantul ke route `verification.notice`** yang
   tidak ada di sini — setiap halaman bendahara jadi 500. Tujuannya sekarang
   dioper sebagai parameter alias middleware di `bootstrap/app.php`.
7. **`kaskelas:buat-kelas` membuat akun yang langsung terkunci** di halaman
   verifikasi. Akun yang lahir dari perintah artisan sekarang ditandai
   terverifikasi — yang menanggung alamatnya adalah operator yang mengetik
   perintahnya.

### AUDIT ISOLASI MENYELURUH — hasil

Syarat selesainya fase ini, dan bagian yang paling penting di seluruh v2.0.
Sampai v1.2 aplikasi ini hanya dipakai satu orang, jadi kebocoran antar kelas
tidak berdampak pada siapa pun. Setelah pendaftaran dibuka, kebocoran berarti
catatan uang satu kelas terlihat orang asing.

**44 test isolasi tenant**, tersebar di 9 berkas:

| Berkas | Test | Cakupan |
|---|---:|---|
| `IsolasiMenyeluruhTest` | 31 | Sapuan seluruh route ber-id + lapisan model |
| `IsolasiCampaignTest` | 6 | Campaign, peserta, dana |
| `IsolasiTenantTest` | 6 | Global scope, gagal-tertutup, `classroom_id` dari request |
| `IsolasiDataMasterTest` | 4 | Siswa, periode |
| `TransaksiHttpTest` | 3 | Pembayaran, pengeluaran, bukti transfer |
| `PengingatQrisTest` | 2 | Template pengingat, QRIS |
| `TutupBukuTest` | 2 | Tutup buku, alih kepemilikan |
| `PelaporanTest` | 2 | Laporan, audit log |
| `DaurHidupKelasTest` | 2 | Pemulihan kelas, ekspor CSV |

`IsolasiMenyeluruhTest` bekerja berbeda dari yang lain: ia **digerakkan dari
daftar route**, bukan dari daftar modul. Setiap route bendahara yang menerima id
(22 route) ditembak dengan id milik kelas lain memakai payload yang sepenuhnya
sah, lalu dituntut menjawab 404. Payloadnya sengaja dibuat valid — 404 yang
muncul karena validasi gagal tidak membuktikan apa pun soal isolasi.

Berkas itu juga memuat satu test penjaga:
`test_tidak_ada_route_bendahara_ber_id_yang_luput_dari_sapuan` membandingkan
daftar yang diuji dengan seluruh route terdaftar. Modul baru yang lupa diuji
akan **langsung menggagalkan test**, bukan menunggu ada yang ingat menuliskannya.

**Modul yang tercakup**, semuanya dengan pola bendahara kelas A → id kelas B → 404:
siswa, periode, tagihan, pembayaran, alokasi (otomatis & manual), bukti transfer,
pengeluaran, kategori pengeluaran, campaign, tutup buku, alih kepemilikan,
pengaturan, QRIS, template pengingat, token halaman kelas, ekspor CSV, pemilih
kelas aktif, pemulihan kelas terhapus, dan dashboard admin platform.

**Tidak ada modul yang belum tercakup.** Route ber-id yang sengaja dikecualikan
dari sapuan, beserta alasannya:

- `publik.kelas`, `publik.manifest`, `publik.qris` — bertoken, bukan ber-id;
  diuji di `HalamanKelasPublikTest` dan `IsolasiMenyeluruhTest`
- `verification.verify` — milik akun, bukan kelas; diuji di `PendaftaranTest`
- `kelas.restore` — diuji di `DaurHidupKelasTest`
- `ekspor.unduh` — parameternya jenis berkas, bukan id baris
- `storage.local*` — route bawaan Laravel, tidak menyentuh data tenant

Selain sapuan route, audit ini menuntut tiga hal yang tidak kelihatan dari
lapisan HTTP:

- Setiap model tenant dibandingkan hitungannya dengan SQL langsung di luar
  global scope. Kalau scope-nya mati, kedua angkanya berbeda.
- Tanpa kelas aktif, seluruh model tenant mengembalikan **nol** baris, bukan
  seluruh baris. Halaman kosong bisa diperbaiki; data yang telanjur terlihat
  tidak bisa ditarik kembali.
- 15 halaman daftar disapu isinya dan tidak boleh memuat satu pun jejak kelas
  lain — nama siswa, nama sekolah, nama campaign, keterangan belanja, label
  tutup buku, maupun token halaman kelasnya.

### Yang harus dikerjakan sendiri sebelum diumumkan

- [ ] Migrasi di produksi: `php artisan migrate --force`. Migrasi v2.0 terpisah
      dan hanya menambah kolom; tabel lama tidak disentuh sama sekali.
- [ ] Pasang SMTP Hostinger dan **uji kirim sungguhan** (lihat `DEPLOY.md` 8b).
      Tanpa ini pendaftaran berhenti total: akun terbuat, email verifikasi tidak
      pernah sampai, kelas tidak bisa dibuat.
- [ ] Pasang cron `schedule:run` setiap menit. Tanpa ini kelas yang dihapus
      tidak pernah benar-benar hilang dan demo tidak pernah direset.
- [ ] `php artisan kaskelas:reset-demo` sekali setelah deploy
- [ ] Tandai satu akun sebagai `admin_platform`
- [ ] Isi tangkapan layar di halaman panduan (sekarang masih placeholder)
- [ ] Daftarkan `sitemap.xml` ke Google Search Console

### Catatan jujur soal SEO

Domain baru butuh 1–3 bulan sebelum mulai muncul di hasil pencarian, dan kata
kunci segeneral "aplikasi kas kelas" sulit ditembus. Semua yang dikerjakan di
fase ini adalah **syarat perlu, bukan jaminan**. Tidak ada janji peringkat di
sini. Halaman-halamannya juga sengaja tidak memuat satu pun klaim keamanan yang
tidak bisa dipertanggungjawabkan — ada test yang memeriksa itu, karena klaim
palsu soal keamanan adalah masalah hukum, bukan sekadar berlebihan.

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


**Penguncian tutup buku duduk di model, bukan di controller.** Trait
`TerkunciTutupBuku` dipasang di `Payment` dan `Expense`, dan `PaymentAllocation`
punya penjaganya sendiri yang mengambil tanggal dari pembayaran induknya.
Alasannya sama dengan alasan global scope tenancy ada di model: pemeriksaan yang
harus diingat setiap kali menulis controller baru adalah pemeriksaan yang cepat
atau lambat akan terlupakan, dan kunci yang bisa terlupakan bukan kunci. Form
Request hanya menyalin pesannya ke bawah kolom tanggal supaya enak dibaca —
bukan di situ penolakannya terjadi.

**Alokasi dikunci pada ubah & hapus, sengaja TIDAK pada simpan baru.**
Pembayaran baru bertanggal hari ini boleh melunasi tagihan periode yang sudah
ditutup: itu menambah catatan, bukan mengubah sejarah, dan uang masuknya sendiri
jatuh di luar rentang tertutup. Kalau simpan baru ikut dikunci, tunggakan
periode tertutup mustahil dilunasi selamanya — diuji di
`test_alokasi_deposit_dari_pembayaran_baru_tetap_boleh_berjalan`.

**Mengubah tanggal transaksi ke luar rentang tertutup juga ditolak.** Baris yang
duduk di periode tertutup tidak boleh disentuh sama sekali, termasuk dipindahkan
keluar — kalau boleh, saldo periode yang sudah ditandatangani berubah lewat
pintu belakang.

**Angka `book_closings` disimpan, satu-satunya di aplikasi ini yang begitu.**
Seluruh angka lain selalu dihitung ulang. Snapshot dibekukan karena laporan
serah terima yang sudah ditandatangani harus tetap mencetak angka yang
ditandatangani waktu itu, bukan angka yang ikut bergerak tiap ada transaksi baru.
`saldo_awal` dihitung dari seluruh mutasi sebelum rentang, BUKAN diwarisi dari
closing sebelumnya — kalau diwarisi, satu closing keliru menular ke semua
closing sesudahnya.

**Hanya closing terakhir yang bisa dibuka kembali.** Membuka yang di tengah
membuat `saldo_awal` seluruh closing sesudahnya tidak lagi benar. Pembukaan
menghapus baris closing-nya, tapi jejaknya disalin lebih dulu ke `audit_logs`
dengan aksi `reopen_book` beserta pelakunya.

**Aksi audit memakai nama yang sudah dicadangkan `schema.sql`** —
`close_book`, `reopen_book`, `transfer_owner`. Kolom `audit_logs.aksi` adalah
ENUM, jadi nama karangan ditolak database. Tidak ada migrasi baru untuk ini;
nilainya memang sudah disiapkan sejak v1.

**Serah terima mencabut akses bendahara lama di transaksi yang sama.** Kalau dia
masih memegang kelas lain, dia diarahkan ke dashboard; kalau tidak, sesinya
diakhiri. Membiarkannya tetap bisa masuk "sebentar dulu" adalah cara paling umum
sebuah serah terima tidak pernah benar-benar selesai. Akun tidak pernah dipakai
bergantian — begitu satu akun dipakai berdua, audit log berhenti bisa menjawab
siapa yang mencatat apa.


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
