# Deploy KasKelas ke Hostinger Premium

Dokumen ini menggambarkan susunan yang **benar-benar dipakai di produksi**, bukan
susunan ideal Laravel. Baca bagian 1 sampai habis sebelum menyentuh apa pun —
susunannya tidak standar, dan sebagian besar masalah deploy di project ini berasal
dari lupa bahwa ia tidak standar.

---

## 1. Susunan folder: kenapa tidak standar, dan apa konsekuensinya

Hostinger mengunci document root di `~/domains/<domain>/public_html` dan tidak
menyediakan cara mengubahnya di paket Premium. Susunan Laravel normal
(document root = `project/public`) karena itu tidak bisa dipakai.

Keputusan yang diambil: **seluruh project diratakan ke dalam `public_html`, dan
folder `public/` dihapus.** Isi `public/` naik satu tingkat menjadi tetangga
`index.php`.

```
~/domains/kaskelasku.my.id/public_html/     <- INI document root
├── index.php            <- front controller, path relatif ke ./ (bukan ../)
├── .htaccess            <- rewrite Laravel + proteksi file sensitif
├── .env                 <- ADA DI DALAM document root. Dilindungi .htaccess.
│
├── build/               <- hasil `npm run build` (di-build di lokal, diunggah)
├── ikon/                <- ikon PWA
├── manifest.webmanifest
├── sw.js
├── robots.txt
├── favicon.ico
├── uploads/             <- symlink ke storage/app/public (lihat bagian 5)
│
├── app/  bootstrap/  config/  database/  lang/  resources/  routes/
├── storage/  vendor/  tests/
├── artisan  composer.json  composer.lock
└── ...
```

Konsekuensi yang harus selalu diingat:

| Konsekuensi | Kenapa | Ditangani oleh |
|---|---|---|
| URL aset **tidak** berawalan `/public/` | document root sudah = root project | `build/` diletakkan di root, bukan di `public/build/` |
| `public_path()` bawaan Laravel salah arah | defaultnya `base_path('public')`, folder itu sudah tidak ada | `->usePublicPath(dirname(__DIR__))` di `bootstrap/app.php` |
| Vite tidak menemukan `manifest.json` | Vite membacanya lewat `public_path()` | sama seperti di atas + `publicDirectory: '.'` di `vite.config.js` |
| Symlink `storage` menabrak `storage/` milik Laravel | keduanya sama-sama di root | symlink dinamai `uploads/`, lihat bagian 5 |
| `.env`, `vendor/`, `storage/` terekspos ke HTTP | semuanya di dalam document root | blok proteksi di `.htaccess`, lihat bagian 6 |

**Yang mengikat semuanya adalah `public_path()`.** Di project ini
`public_path() === base_path()`. Kalau suatu saat aset kembali 404, periksa baris
`usePublicPath` di `bootstrap/app.php` lebih dulu.

### Jangan lakukan ini

- Mengembalikan `index.php` ke dalam folder `public/`.
- Mengunggah hasil build ke `public_html/public/build/`. URL `/build/...` akan
  404 — persis bug yang menghabiskan waktu pada deploy pertama.
- Menghapus blok proteksi di `.htaccess`. Tanpa itu `https://domain/.env`
  menyajikan kata sandi database sebagai teks biasa.
- `chmod 777` apa pun. Lihat bagian 7 untuk angka yang benar.

---

## 2. Verifikasi lingkungan (sekali, sebelum deploy pertama)

| Yang dicek | Syarat | Kalau tidak terpenuhi |
|---|---|---|
| Versi PHP | 8.2+ | hPanel -> PHP Configuration |
| MySQL / MariaDB | MySQL 8.0.16+ / MariaDB 10.2+ | `CHECK` constraint pada `bills` diabaikan diam-diam — periksa manual |
| Akses SSH | ada / tidak | Tanpa SSH, `vendor/` harus diunggah manual tiap deploy |
| Composer di server | ada / tidak | Sama seperti di atas |

Ekstensi PHP yang dipakai: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`,
`gd` atau `imagick` (dompdf). **`bcmath` tidak dibutuhkan.**

---

## 3. Build aset di lokal

Node tidak tersedia di shared hosting, jadi aset **selalu** di-build di komputer
sendiri lalu diunggah.

```bash
npm ci
npm run build
```

Hasilnya masuk ke `build/` di root project — **bukan** `public/build/`. Itu diatur
oleh `publicDirectory: '.'` di `vite.config.js`. Jangan kembalikan ke `'public'`
selama susunan deploy masih seperti ini.

Periksa sebelum mengunggah:

```bash
ls build/manifest.json build/assets/
```

Nama berkas di `build/assets/` mengandung hash isi (`app-D48oy-ND.css`). Hash
berubah setiap kali CSS/JS berubah, jadi **`build/` harus diunggah ulang setiap
kali `resources/css` atau `resources/js` berubah** — kalau tidak, Blade menunjuk
hash baru sementara di server masih hash lama, dan hasilnya 404 lagi.

Untuk pengembangan lokal (Laragon, document root di root project) susunan ini
bekerja apa adanya; `npm run dev` dan `php artisan serve` juga tetap jalan.

---

## 4. Yang diunggah setiap deploy

| Berubah | Yang harus diunggah ulang |
|---|---|
| Kode PHP (`app/`, `routes/`, `config/`, `database/`) | folder yang bersangkutan |
| Blade (`resources/views/`) | `resources/views/` + `php artisan view:cache` |
| CSS/JS (`resources/css`, `resources/js`) | **`build/` seluruhnya** (hapus dulu `build/` lama di server supaya aset basi tidak menumpuk) |
| `composer.json` / `composer.lock` | `vendor/` (atau `composer install --no-dev -o` lewat SSH) |
| Ikon / PWA | `ikon/`, `manifest.webmanifest`, `sw.js` |

**Tidak pernah diunggah:** `.env` (milik server, beda dari lokal), `node_modules/`,
`storage/` (isinya data hidup — log, sesi, bukti transfer), `uploads/` (symlink),
`.git/`, `*.md`, `PRD-*.docx`.

Setelah setiap unggah kode atau perubahan `.env`:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Urutannya penting: `optimize:clear` dulu, baru cache ulang. Cache lama menyimpan
path absolut; kalau tidak dibersihkan, perubahan `usePublicPath` atau `.env` tidak
terbaca.

---

## 5. Symlink `uploads/` (pengganti `public/storage`)

Bawaan Laravel membuat symlink `public/storage` -> `storage/app/public`. Di susunan
ini `public_path('storage')` menunjuk ke `storage/` milik Laravel sendiri —
menabrak. Karena itu `config/filesystems.php` diubah:

```php
'links' => [
    public_path('uploads') => storage_path('app/public'),
],
```

dan URL disk publik menjadi `APP_URL/uploads`. Buat symlink-nya di server:

```bash
cd ~/domains/kaskelasku.my.id/public_html
rm -f uploads                       # buang symlink lama yang salah arah, kalau ada
php artisan storage:link
ls -l uploads                       # harus: uploads -> .../storage/app/public
```

Kalau SSH tidak tersedia, buat symlink lewat skrip PHP sekali pakai, lalu **hapus
skripnya**:

```php
<?php symlink(__DIR__.'/storage/app/public', __DIR__.'/uploads');
```

Catatan: saat ini aplikasi belum memakai disk `public` sama sekali. Bukti transfer
disimpan di `storage/app/private/kelas-*` dan disajikan lewat controller
ber-otorisasi, bukan lewat URL langsung — itu disengaja, jangan dipindah ke
`uploads/`.

---

## 6. Proteksi file sensitif di `.htaccess`

Karena `.env` dan `vendor/` berada di dalam document root, `public_html/.htaccess`
memblokir secara eksplisit:

- semua dotfile/dotfolder (`.env`, `.env.*`, `.git/`, `.htaccess`), kecuali
  `/.well-known/` supaya perpanjangan sertifikat SSL tidak ikut mati;
- folder `app/ bootstrap/ config/ database/ lang/ node_modules/ resources/
  routes/ storage/ tests/ vendor/`;
- berkas `artisan`, `composer.json`, `composer.lock`, `package.json`,
  `package-lock.json`, `phpunit.xml`, `vite.config.js`, `schema.sql`, serta
  `*.md`, `*.docx`, `*.sql`, `*.log`, `*.bak`.

Blok itu harus tetap berada **di atas** aturan front controller. File-file tersebut
benar-benar ada di disk, jadi Apache melayaninya langsung dan tidak pernah sampai
ke `index.php` — aturan `RewriteCond %{REQUEST_FILENAME} !-f` tidak menolong.

`uploads/` sengaja tidak diblokir; itu satu-satunya bagian `storage/` yang boleh
publik.

Verifikasi setiap kali `.htaccess` disentuh:

```bash
curl -sI https://kaskelasku.my.id/.env                       # 403
curl -sI https://kaskelasku.my.id/composer.json              # 403
curl -sI https://kaskelasku.my.id/vendor/autoload.php        # 403
curl -sI https://kaskelasku.my.id/storage/logs/laravel.log   # 403
curl -sI https://kaskelasku.my.id/DEPLOY.md                  # 403
```

---

## 7. Perizinan

```bash
cd ~/domains/kaskelasku.my.id/public_html
find . -type d -not -path './storage/*' -not -path './bootstrap/cache/*' -exec chmod 755 {} +
find . -type f -not -path './storage/*' -not -path './bootstrap/cache/*' -exec chmod 644 {} +
chmod -R 775 storage bootstrap/cache
chmod 755 artisan
chmod 600 .env
```

Aturannya: folder 755, berkas 644, kecuali `storage/` dan `bootstrap/cache/` yang
775 karena ditulis oleh proses web server, dan `.env` yang 600 — tidak ada proses
lain yang perlu membacanya. **Jangan pernah 777** — di shared hosting itu berarti
akun lain di mesin yang sama bisa menulis ke folder aplikasi.

---

## 8. `.env` produksi

`.env` di server berbeda dari `.env` lokal dan tidak pernah ikut diunggah.

```env
APP_NAME=KasKelas
APP_ENV=production
APP_DEBUG=false
APP_URL=https://kaskelasku.my.id

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

`APP_DEBUG=false` bukan saran: halaman error Laravel menampilkan isi `.env`,
termasuk kata sandi database, kepada siapa pun yang memicu error.
`APP_ENV=production` juga yang mengaktifkan `URL::forceScheme('https')` di
`AppServiceProvider`.

Setelah mengubah `.env`, **selalu**:

```bash
php artisan config:clear && php artisan config:cache
```

Deploy pertama saja:

```bash
php artisan key:generate      # kalau APP_KEY masih kosong
php artisan migrate --force
php artisan db:seed --force   # HANYA sekali; lalu ganti sandi bendahara bawaan
```

---

## 9. Paksa HTTPS

Aktifkan "Force HTTPS" di hPanel. Kalau tidak tersedia, tambahkan di
`public_html/.htaccess` **milik server** (jangan di repo, supaya dev lokal tidak
ikut dipaksa), tepat setelah `RewriteEngine On`:

```apache
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## 10. Checklist verifikasi setelah deploy

```bash
H=https://kaskelasku.my.id
curl -sI $H/                                      # 200 atau 302 ke /login
curl -sI $H/build/assets/app-<hash>.css           # 200, content-type text/css
curl -sI $H/build/assets/app-<hash>.js            # 200, content-type javascript
curl -sI $H/manifest.webmanifest                  # 200
curl -sI $H/sw.js                                 # 200
curl -sI $H/ikon/ikon-192.png                     # 200
curl -sI $H/.env                                  # 403
curl -sI $H/vendor/autoload.php                   # 403
```

Ambil `<hash>` dari `build/manifest.json` di lokal — kalau hash di situ tidak sama
dengan yang diminta browser, artinya `build/` di server belum diunggah ulang.

---

## 11. Backup pertama (wajib sebelum dipakai nyata)

1. hPanel -> phpMyAdmin -> pilih database -> Export -> format SQL -> simpan.
2. Simpan salinannya di luar server, **jangan di dalam repo Git**.
3. Uji restore ke database kosong. Backup yang belum pernah diuji bukan backup.

---

## Sisa Fase 6 yang harus dikerjakan sendiri

- [ ] Uji seluruh alur utama langsung di HP Android: masuk, tambah siswa, buat
      periode, catat pembayaran, catat pengeluaran, buka halaman kelas, pasang ke
      home screen.
- [ ] Bagikan tautan kelas ke grup kelas.
- [ ] Pakai untuk kelas sendiri minimal satu bulan penuh tanpa kembali ke catatan
      manual.

Baru setelah itu Fase 7 (iuran insidental) boleh dimulai.
