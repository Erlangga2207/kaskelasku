# KasKelas — Rencana Pengerjaan (v3)

Kerjakan berurutan. Satu fase = satu branch. Jangan lanjut sebelum checklist
"Selesai bila" terpenuhi.

---

## Fase 0 — Verifikasi Lingkungan (sebelum coding)

- [ ] Cek versi PHP di hPanel Hostinger (butuh 8.2+)
- [ ] Cek ketersediaan SSH / Composer
- [ ] Cek apakah document root bisa diarahkan ke folder `public/`
- [ ] Cek versi MySQL/MariaDB (CHECK constraint butuh MySQL 8.0.16+ / MariaDB 10.2+)
- [ ] Cek ketersediaan SMTP untuk verifikasi email (dibutuhkan di v2.0)
- [ ] Kunci pilihan domain
- [ ] Siapkan repo Git + `.gitignore` Laravel standar

**Selesai bila:** kamu tahu pasti alur deploy yang akan dipakai. Kalau SSH tidak ada,
folder `vendor/` harus diunggah manual tiap deploy — putuskan sekarang.

---

## Fase 1 — Fondasi & Tenancy

- [ ] Install Laravel 12 + Tailwind
- [ ] Migration seluruh tabel v1 sesuai `schema.sql`, **termasuk `classroom_id`**
- [ ] Model + relasi Eloquent
- [ ] Trait `BelongsToClassroom` dengan Global Scope, dipasang di semua model tenant
- [ ] `CurrentClassroom` — resolusi kelas aktif dari session (dan dari token untuk route publik)
- [ ] Auth bendahara (login, logout, rate limit 5x/menit)
- [ ] Middleware auth + middleware kelas aktif pada seluruh route bendahara
- [ ] Observer pencatat `audit_logs` otomatis (termasuk `classroom_id`)
- [ ] Seeder: 1 bendahara, 1 kelas, kategori bawaan, 5 siswa contoh
- [ ] **Test isolasi pertama**: akses ID milik kelas lain → 404

**Selesai bila:** global scope terbukti bekerja. Buat dua kelas di seeder, lalu
pastikan query dari kelas A tidak pernah mengembalikan baris kelas B.

---

## Fase 2 — Data Master

- [ ] CRUD siswa (nonaktifkan, bukan hapus, bila sudah ada transaksi)
- [ ] Input massal siswa dengan tempel daftar nama
- [ ] Setup kelas: nama kelas, sekolah, tipe periode, tanggal mulai, nominal
- [ ] Generate periode otomatis sampai akhir tahun ajaran
- [ ] Generate `bills` untuk siswa aktif per periode
- [ ] Tandai periode libur → tagihannya tidak dibuat
- [ ] Ubah nominal periode tertentu (tidak memengaruhi periode lain)
- [ ] Test isolasi modul siswa & periode

**Selesai bila:** siswa yang `tgl_berhenti`-nya di tengah tahun tidak punya tagihan
untuk periode setelah tanggal itu.

---

## Fase 3 — Transaksi (fase paling rawan)

- [ ] `KasService`: alokasi otomatis ke tagihan terlama yang belum lunas
- [ ] Form pembayaran: pilih siswa → tampil tagihan belum lunas → input jumlah →
      alokasi otomatis, bisa diubah manual
- [ ] Kelebihan bayar → deposit siswa, otomatis terpakai di tagihan berikutnya
- [ ] Upload bukti opsional (maks 2MB, jpg/png/pdf, validasi MIME asli, nama diacak,
      simpan di `storage/app/private/kelas-{id}/`, akses lewat controller yang
      mengecek kepemilikan kelas)
- [ ] Hapus pembayaran (soft delete, alokasi ikut terhapus, status tagihan pulih)
- [ ] CRUD pengeluaran + kategori, validasi jumlah tidak melebihi saldo kas
- [ ] Pembebasan tagihan dengan alasan wajib
- [ ] Perhitungan denda (mode tetap & harian, grace period, batas maksimum)
- [ ] **Test logika uang lengkap** (PRD bagian 14.2)
- [ ] **Test isolasi** modul pembayaran & pengeluaran

**Selesai bila:** semua test hijau. Jangan lanjut ke fase 4 kalau ada satu saja yang
merah — semua angka di fase berikutnya bergantung pada fase ini.

---

## Fase 4 — Pelaporan

- [ ] Dashboard: saldo kas, total masuk, total keluar, jumlah penunggak
- [ ] Daftar tunggakan (urut dari terbesar)
- [ ] Rekap per periode
- [ ] Riwayat transaksi dengan filter tanggal
- [ ] Ekspor laporan PDF
- [ ] Halaman audit log (read-only)

**Selesai bila:** saldo di dashboard sama persis dengan hasil query manual
`SUM(payments) - SUM(expenses)` untuk kelas tersebut.

---

## Fase 5 — Halaman Kelas & PWA

- [ ] Route publik terpisah, **hanya GET**, tanpa middleware auth
- [ ] Token 40 karakter acak per kelas, tombol rotasi token
- [ ] Token tidak dikenal → 404, bukan pesan error yang membocorkan informasi
- [ ] Header `noindex` + `robots.txt`
- [ ] Halaman kelas: saldo, rekap, tabel status bayar
- [ ] Pastikan bukti transfer dan data akun TIDAK bocor ke halaman kelas
- [ ] `manifest.json` + ikon (192px, 512px, maskable)
- [ ] Service worker: cache **aset statis saja**, halaman data network-first
- [ ] Uji pasang ke home screen Android
- [ ] Test isolasi: token kelas A hanya menampilkan data kelas A

**Selesai bila:** halaman kelas tidak punya satu pun form atau route tulis, dan data
saldo tidak pernah tampil basi setelah ada transaksi baru.

---

## Fase 6 — Rilis v1 (pemakaian sendiri)

- [ ] Paksa HTTPS, `APP_DEBUG=false`, `.env` di luar document root
- [ ] Backup database manual pertama lewat phpMyAdmin
- [ ] Deploy ke Hostinger, document root → `public/`
- [ ] Uji seluruh alur utama di HP asli
- [ ] Pakai untuk kelasmu sendiri, minimal satu bulan penuh

**Selesai bila:** kamu sendiri memakainya tanpa kembali ke catatan manual.

---

## Fase 7 — v1.1: Iuran Insidental

- [x] Migration: `campaigns`, FK `bills.campaign_id`, FK `expenses.campaign_id`
- [x] CRUD campaign + pilih peserta (default semua siswa aktif)
- [x] Generate `bills` campaign (`period_id` NULL)
- [x] Pastikan mesin alokasi pembayaran **tidak diubah sama sekali** — kalau butuh
      cabang kode baru, berarti desainnya salah
- [x] Dashboard memisahkan saldo bebas vs saldo campaign yang belum terpakai
- [x] Laporan campaign: terkumpul / terpakai / sisa
- [x] Campaign dibatalkan → pembayaran jadi deposit siswa, bukan terhapus
- [x] Progres campaign tampil di halaman kelas

**Belum dikerjakan:** deploy. Semua masih di branch `feat/fase-7-iuran-insidental`.

## Fase 8 — v1.1: Pengingat & QRIS

- [x] Generator teks pengingat, placeholder `{nama} {rincian} {total} {batas}`
- [x] Template bisa diubah per kelas
- [x] Tombol salin teks — per siswa dan beberapa siswa sekaligus dari daftar tunggakan
- [x] Upload QRIS statis + nama pemilik per kelas, tampil di halaman kelas

**Belum dikerjakan:** deploy. Semua masih di branch `feat/fase-8-pengingat-qris`.

## Fase 9 — v1.2: Tutup Buku & Serah Terima

- [ ] Migration `book_closings`
- [ ] Proses tutup buku: hitung ringkasan, simpan snapshot
- [ ] Validasi server: transaksi dalam rentang tertutup ditolak saat edit/hapus
- [ ] Buka kembali tutup buku terakhir (tercatat di audit log)
- [ ] PDF laporan serah terima + kolom tanda tangan
- [ ] Undang bendahara baru & alihkan kepemilikan kelas

---

## Fase 10 — v2.0: Peluncuran Publik

**Jangan mulai sebelum v1 dipakai nyata minimal satu semester.**

- [ ] Putuskan model biaya SEBELUM membuka pendaftaran
- [ ] Landing page (struktur di PRD bagian 5.1)
- [ ] Kebijakan Privasi + Syarat Layanan berbahasa Indonesia yang mudah dipahami
- [ ] Pendaftaran mandiri + verifikasi email + rate limit
- [ ] Wizard buat kelas setelah verifikasi
- [ ] Pemilih kelas aktif (akun boleh punya beberapa kelas, disimpan di session)
- [ ] Batas: 5 kelas per akun, 60 siswa per kelas
- [ ] Checkbox persetujuan data siswa, waktunya dicatat
- [ ] Kelas demo read-only dengan data contoh, direset otomatis
- [ ] Ekspor data kelas ke CSV
- [ ] Hapus kelas dengan tenggang 30 hari
- [ ] Dashboard admin platform: **agregat saja**, tanpa detail transaksi
- [ ] Penandaan kelas tidak aktif setelah 12 bulan + email pemberitahuan
- [ ] **Audit isolasi menyeluruh**: ulangi seluruh test isolasi di semua modul
- [ ] Backup terjadwal + uji restore (backup yang belum pernah diuji bukan backup)

**Selesai bila:** ada kelas lain di luar kelasmu yang memakainya selama sebulan
tanpa kamu bantu secara manual.
