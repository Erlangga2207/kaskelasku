# KasKelas — Panduan Kerja untuk AI Assistant

Baca file ini sebelum menulis kode apa pun. Spesifikasi lengkap ada di PRD v3.0.

## Konteks

Aplikasi pencatatan kas kelas **multi-tenant**: satu aplikasi melayani banyak kelas
dari banyak sekolah. Tiap kelas punya satu bendahara; anggota kelas memantau lewat
halaman read-only bertoken tanpa login.

Pemilik project adalah mahasiswa yang sedang belajar. Jelaskan alasan di balik
keputusan teknis, jangan hanya menyerahkan kode.

## Stack

- Laravel 12 (PHP 8.2+), MySQL, Blade, Tailwind
- Tanpa Livewire, tanpa Inertia, tanpa Vue/React — Blade + sedikit Alpine.js bila perlu
- Deploy: Hostinger Premium (shared hosting)

## ATURAN NOMOR SATU: ISOLASI TENANT

Satu kebocoran data antar kelas lebih fatal daripada seluruh bug lain di project ini.

1. Semua model tenant memakai trait `BelongsToClassroom` dengan Global Scope.
   Tidak ada pengecualian.
2. **DILARANG** `Model::find($request->id)`. Selalu lewat relasi kelas aktif,
   supaya ID milik kelas lain menghasilkan 404, bukan data orang lain.
3. `classroom_id` **tidak pernah** diambil dari request — tidak dari form, tidak
   dari query string, tidak dari hidden input. Selalu dari session bendahara atau
   dari token pada route publik.
4. Semua UNIQUE constraint diawali `classroom_id`.
5. Setiap modul baru wajib punya test: bendahara kelas A akses ID kelas B → 404.

## Aturan Lain yang Tidak Boleh Dilanggar

- Uang selalu `DECIMAL(12,2)`. Tidak ada FLOAT di migrasi mana pun.
- Saldo dan status lunas **tidak pernah** disimpan sebagai kolom. Selalu dihitung
  dari `payments`, `payment_allocations`, dan `expenses`.
- Transaksi tidak dihapus permanen. Soft delete + catat ke `audit_logs`.
- Otorisasi dicek di server pada setiap aksi tulis. Menyembunyikan tombol di Blade
  bukan pengamanan.
- Route publik **hanya GET**, grup route terpisah.
- Jangan pakai `{!! !!}` untuk data dari pengguna.
- Data siswa hanya: nama, no absen, status. Jangan pernah menambah NIS, nomor HP,
  alamat, atau foto — ini kewajiban hukum (UU PDP), bukan preferensi.
- Jangan menambah abstraksi tanpa alasan. Controller + Form Request + Eloquent
  sudah cukup. Tidak perlu repository pattern atau event/listener untuk CRUD biasa.
- Jangan mengarang API Laravel. Kalau tidak yakin sebuah method ada, cek dokumentasi
  resmi dulu.

## Struktur

Struktur Laravel standar. Logika yang dipakai berulang ditaruh di:

- `app/Services/KasService.php` — alokasi pembayaran, denda, saldo
- `app/Models/Concerns/BelongsToClassroom.php` — trait global scope
- `app/Support/CurrentClassroom.php` — resolusi kelas aktif dari session/token

Itu saja. Jangan menambah layer lain tanpa alasan konkret.

## Konvensi

- Tabel: jamak, snake_case, kolom domain bahasa Indonesia (`nominal`, `jatuh_tempo`)
- Model: tunggal, PascalCase, bahasa Inggris (`Student`, `Payment`, `Bill`)
- Validasi lewat Form Request, bukan di controller
- Operasi tulis lintas tabel dibungkus `DB::transaction()`

## Urutan Pengerjaan

Ikuti `TASKS.md` berurutan. Jangan lompat fase. Jangan mengerjakan fitur v1.1/v1.2/v2.0
selama v1 belum selesai dan dipakai nyata.

## Git

- Satu branch per fase: `feat/fase-2-data-master`
- Format commit: `feat(siswa): tambah CRUD data siswa`
- Pesan commit menjelaskan maksud, bukan daftar file
- Jangan `git add . && git commit -m "update"`

## Kalau Ada yang Ambigu

Jangan mengarang keputusan desain. Sebutkan bagian yang ambigu, ajukan asumsi yang
masuk akal, tunggu konfirmasi.
