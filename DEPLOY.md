# Deploy KasKelas ke Hostinger Premium

Berkas ini menutup bagian Fase 6 yang bisa disiapkan dari kode. Langkah yang
menyentuh panel Hostinger tetap harus kamu jalankan sendiri — catat hasilnya di
`PROGRESS.md` supaya jelas mana yang sudah beres.

---

## 1. Verifikasi lingkungan (Fase 0 — lakukan sebelum unggah apa pun)

Cek di hPanel, lalu tulis hasilnya:

| Yang dicek | Syarat | Kalau tidak terpenuhi |
|---|---|---|
| Versi PHP | 8.2 atau lebih baru | Naikkan di hPanel → PHP Configuration |
| MySQL / MariaDB | MySQL 8.0.16+ atau MariaDB 10.2+ | `CHECK` constraint pada tabel `bills` akan diabaikan diam-diam — periksa manual |
| Akses SSH | ada / tidak | Kalau tidak ada, `vendor/` harus diunggah manual tiap deploy (lihat langkah 3b) |
| Composer di server | ada / tidak | Sama seperti di atas |
| Document root bisa diarahkan | ya / tidak | Kalau tidak bisa, lihat langkah 4b |
| SMTP | ada / tidak | Baru dibutuhkan di v2.0 (verifikasi email), bukan sekarang |

Ekstensi PHP yang dipakai aplikasi ini: `pdo_mysql`, `mbstring`, `openssl`,
`fileinfo` (validasi MIME bukti), `gd` atau `imagick` (dompdf). **`bcmath` tidak
dibutuhkan** — hitungan uang memakai integer sen lewat `App\Support\Uang`.

---

## 2. Siapkan database

1. hPanel → Databases → buat database + user, catat kredensialnya.
2. Jangan pakai user `root`; beri hak hanya pada database KasKelas.

---

## 3. Unggah kode

### 3a. Kalau SSH tersedia (disarankan)

```bash
git clone <repo> ~/kaskelas
cd ~/kaskelas
composer install --no-dev --optimize-autoloader
```

Aset front-end **di-build di komputermu**, bukan di server (Node tidak selalu ada
di shared hosting):

```bash
npm ci && npm run build      # hasilnya public/build/
```

Lalu unggah `public/build/` ikut bersama kode.

### 3b. Kalau SSH tidak ada

Jalankan di komputermu:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

Lalu unggah seluruh folder termasuk `vendor/` dan `public/build/` lewat File
Manager atau FTP. Ini artinya **setiap deploy berikutnya juga harus mengunggah
`vendor/`** — putuskan sejak awal apakah kamu siap dengan itu.

---

## 4. Arahkan document root

### 4a. Cara benar

hPanel → Websites → Advanced → ubah document root menjadi `.../kaskelas/public`.

Ini penting: `.env`, `storage/`, dan `vendor/` harus berada **di luar** folder yang
bisa diakses publik.

### 4b. Kalau document root tidak bisa diubah (kasus Hostinger)

Hostinger mengunci document root di `~/domains/<domain>/public_html`. Karena itu
**isi `public/` yang naik ke `public_html`**, sedangkan kode aplikasi duduk satu
tingkat di atasnya.

Struktur yang benar di server:

```
~/domains/kaskelasku.my.id/
├── app_kaskelas/          ← seluruh project (app, vendor, storage, .env, ...)
└── public_html/           ← HANYA isi folder public/ (index.php, build/, ikon/, ...)
```

Lewat SSH, dari kondisi "semua ditumpuk di public_html":

```bash
cd ~/domains/kaskelasku.my.id
mkdir -p app_kaskelas

# 1. Pindahkan semua kecuali folder public/ ke luar document root
cd public_html
shopt -s dotglob nullglob
for f in *; do [ "$f" = "public" ] || mv -- "$f" ../app_kaskelas/; done

# 2. Naikkan isi public/ menjadi isi public_html
mv -- public/* ./
rmdir public

# 3. Sambungkan kembali public_path() Laravel (dipakai storage:link)
ln -s ../public_html ../app_kaskelas/public

# 4. Arahkan front controller ke lokasi kode yang baru
sed -i "s#__DIR__\.'/\.\./#__DIR__.'/../app_kaskelas/#g" index.php

# 5. Bersihkan cache yang masih menyimpan path lama
cd ../app_kaskelas
php artisan optimize:clear
```

Setelah langkah 4, tiga baris di `public_html/index.php` harus berbunyi:

```php
if (file_exists($maintenance = __DIR__.'/../app_kaskelas/storage/framework/maintenance.php')) {
require __DIR__.'/../app_kaskelas/vendor/autoload.php';
$app = require_once __DIR__.'/../app_kaskelas/bootstrap/app.php';
```

Tiga baris itu **milik server, bukan repo**. Setiap kali kamu mengunggah ulang
`public/index.php`, patch-nya hilang dan situs mati — ulangi langkah 4.

Verifikasi (dari komputermu):

```bash
curl -sI https://kaskelasku.my.id/build/assets/app-<hash>.css   # harus 200
curl -sI https://kaskelasku.my.id/composer.json                 # harus 403/404
curl -sI https://kaskelasku.my.id/storage/logs/laravel.log      # harus 403/404
```

Yang **tidak boleh** dilakukan: memindahkan `index.php` dan `.htaccess` ke akar
project lalu mengunggah seluruh project ke `public_html`. Aset gagal dimuat
(`/build/...` tidak ada karena file sesungguhnya di `/public/build/...`), dan
`vendor/`, `storage/logs/`, `composer.json`, sampai dokumen PRD ikut terbaca publik.

---

## 5. Konfigurasi `.env` produksi

Salin `.env.example` menjadi `.env`, lalu ubah:

```env
APP_NAME=KasKelas
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domainmu.com

APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=<nama_database>
DB_USERNAME=<user>
DB_PASSWORD=<sandi>

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

`APP_DEBUG=false` bukan saran — halaman error Laravel menampilkan isi `.env`
termasuk kata sandi database.

Lalu:

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force      # HANYA sekali, untuk membuat kelas & bendahara pertama
```

Setelah seeder jalan, **ganti kata sandi bendahara** dari bawaan seeder.

Terakhir, cache konfigurasi:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Ulangi ketiganya setiap kali kode atau `.env` berubah.

---

## 6. Paksa HTTPS

Aplikasi sudah memaksa skema `https` pada URL yang dihasilkannya saat
`APP_ENV=production` (lihat `app/Providers/AppServiceProvider.php`). Pengalihan
permintaan HTTP ke HTTPS dilakukan di level web server — aktifkan "Force HTTPS" di
hPanel, atau tambahkan di `public/.htaccess` **milik server** (jangan di repo,
supaya pengembangan lokal tidak ikut dipaksa):

```apache
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## 7. Perizinan folder

```bash
chmod -R 775 storage bootstrap/cache
```

Folder `storage/app/private/kelas-*` berisi bukti transfer. Pastikan ia **tidak**
bisa dijangkau lewat URL — kalau document root sudah benar di `public/`, ia
otomatis aman.

---

## 8. Backup pertama (wajib sebelum dipakai nyata)

1. hPanel → phpMyAdmin → pilih database → Export → format SQL → simpan.
2. Simpan salinannya di luar server (Drive/hard disk), **jangan di dalam repo Git**.
3. Uji restore-nya ke database kosong. Backup yang belum pernah diuji bukan backup.

---

## Sisa Fase 6 yang harus kamu kerjakan sendiri

- [ ] Uji seluruh alur utama langsung di HP Android: masuk, tambah siswa, buat
      periode, catat pembayaran, catat pengeluaran, buka halaman kelas, pasang ke
      home screen.
- [ ] Bagikan tautan kelas ke grup kelasmu.
- [ ] Pakai untuk kelas sendiri minimal satu bulan penuh tanpa kembali ke catatan
      manual.

Baru setelah itu Fase 7 (iuran insidental) boleh dimulai.
