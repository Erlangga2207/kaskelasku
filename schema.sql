-- =====================================================================
-- KasKelas — Skema Referensi v3 (MySQL 8.0+ / MariaDB 10.4+)
-- ARSITEKTUR: multi-tenant, shared database, dibedakan kolom classroom_id
--
-- CATATAN: file ini REFERENSI DESAIN, bukan file yang dijalankan.
-- Implementasi memakai migration Laravel yang HARUS sama persis.
-- Kalau ada perbedaan, file ini yang benar.
--
-- ATURAN MUTLAK: setiap tabel domain punya classroom_id, dan SETIAP query
-- ke tabel itu difilter classroom_id lewat Global Scope. Tanpa itu, data
-- kelas lain bocor.
-- =====================================================================

-- ---------- v1: fondasi tenancy ----------

CREATE TABLE users (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama              VARCHAR(100)  NOT NULL,
  email             VARCHAR(150)  NOT NULL UNIQUE,
  password          VARCHAR(255)  NOT NULL,
  email_verified_at TIMESTAMP     NULL,
  role              ENUM('bendahara','admin_platform') NOT NULL DEFAULT 'bendahara',
  is_active         BOOLEAN       NOT NULL DEFAULT TRUE,
  remember_token    VARCHAR(100)  NULL,
  created_at        TIMESTAMP     NULL,
  updated_at        TIMESTAMP     NULL
);

-- Akar tenancy. Konfigurasi digabung di sini karena relasinya 1:1 dengan kelas.
CREATE TABLE classrooms (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_kelas         VARCHAR(100)  NOT NULL,
  sekolah            VARCHAR(150)  NOT NULL,
  owner_id           BIGINT UNSIGNED NOT NULL,
  tipe_periode       ENUM('mingguan','bulanan') NOT NULL,
  public_token       CHAR(40)      NOT NULL UNIQUE,   -- acak, unik lintas kelas
  token_rotated_at   TIMESTAMP     NULL,
  denda_aktif        BOOLEAN       NOT NULL DEFAULT FALSE,
  denda_mode         ENUM('tetap','harian') NOT NULL DEFAULT 'tetap',
  denda_nominal      DECIMAL(12,2) NOT NULL DEFAULT 0,
  grace_days         SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  denda_maks         DECIMAL(12,2) NULL,
  template_pengingat TEXT          NULL,              -- v1.1
  qris_path          VARCHAR(255)  NULL,              -- v1.1
  qris_nama_pemilik  VARCHAR(100)  NULL,              -- v1.1
  persetujuan_data_at TIMESTAMP    NULL,              -- bukti persetujuan UU PDP
  status             ENUM('aktif','nonaktif','dihapus') NOT NULL DEFAULT 'aktif',
  created_at         TIMESTAMP     NULL,
  updated_at         TIMESTAMP     NULL,
  CONSTRAINT fk_classrooms_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_classrooms_status (status)
);

-- Jembatan untuk serah terima bendahara dan akun multi-kelas.
CREATE TABLE classroom_user (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  classroom_id  BIGINT UNSIGNED NOT NULL,
  user_id       BIGINT UNSIGNED NOT NULL,
  peran         ENUM('bendahara') NOT NULL DEFAULT 'bendahara',
  created_at    TIMESTAMP NULL,
  CONSTRAINT fk_cu_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cu_user      FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE RESTRICT,
  UNIQUE KEY uq_cu (classroom_id, user_id)
);

-- ---------- v1: data domain ----------

CREATE TABLE students (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  classroom_id    BIGINT UNSIGNED NOT NULL,
  nama            VARCHAR(100)    NOT NULL,
  no_absen        SMALLINT UNSIGNED NULL,
  tgl_mulai_aktif DATE            NOT NULL,
  tgl_berhenti    DATE            NULL,
  is_active       BOOLEAN         NOT NULL DEFAULT TRUE,
  created_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NULL,
  deleted_at      TIMESTAMP       NULL,
  CONSTRAINT fk_students_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE RESTRICT,
  INDEX idx_students_kelas (classroom_id, is_active)
);

CREATE TABLE periods (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  classroom_id    BIGINT UNSIGNED NOT NULL,
  label           VARCHAR(50)     NOT NULL,
  tipe            ENUM('mingguan','bulanan') NOT NULL,
  tgl_mulai       DATE            NOT NULL,
  tgl_selesai     DATE            NOT NULL,
  jatuh_tempo     DATE            NOT NULL,
  nominal         DECIMAL(12,2)   NOT NULL,
  is_libur        BOOLEAN         NOT NULL DEFAULT FALSE,
  created_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NULL,
  CONSTRAINT fk_periods_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE RESTRICT,
  INDEX idx_periods_kelas (classroom_id, jatuh_tempo)
);

-- Tagihan: sejumlah uang yang ditagihkan ke seorang siswa.
-- Sumbernya PERSIS SATU dari: periode rutin atau campaign insidental.
CREATE TABLE bills (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  classroom_id    BIGINT UNSIGNED NOT NULL,
  student_id      BIGINT UNSIGNED NOT NULL,
  period_id       BIGINT UNSIGNED NULL,
  campaign_id     BIGINT UNSIGNED NULL,              -- dipakai mulai v1.1
  nominal         DECIMAL(12,2)   NOT NULL,
  is_bebas        BOOLEAN         NOT NULL DEFAULT FALSE,
  alasan_bebas    VARCHAR(255)    NULL,
  created_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NULL,
  CONSTRAINT fk_bills_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE RESTRICT,
  CONSTRAINT fk_bills_student   FOREIGN KEY (student_id)   REFERENCES students(id)   ON DELETE RESTRICT,
  CONSTRAINT fk_bills_period    FOREIGN KEY (period_id)    REFERENCES periods(id)    ON DELETE RESTRICT,
  CONSTRAINT chk_bills_sumber CHECK (
    (period_id IS NOT NULL AND campaign_id IS NULL) OR
    (period_id IS NULL AND campaign_id IS NOT NULL)
  ),
  UNIQUE KEY uq_bills_periode  (classroom_id, student_id, period_id),
  UNIQUE KEY uq_bills_campaign (classroom_id, student_id, campaign_id),
  INDEX idx_bills_kelas (classroom_id, student_id)
);

CREATE TABLE payments (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  classroom_id    BIGINT UNSIGNED NOT NULL,
  student_id      BIGINT UNSIGNED NOT NULL,
  tanggal         DATE            NOT NULL,
  jumlah          DECIMAL(12,2)   NOT NULL,
  metode          ENUM('tunai','transfer') NOT NULL DEFAULT 'tunai',
  catatan         VARCHAR(255)    NULL,
  bukti_path      VARCHAR(255)    NULL,              -- storage privat, per kelas
  created_by      BIGINT UNSIGNED NOT NULL,
  created_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NULL,
  deleted_at      TIMESTAMP       NULL,
  CONSTRAINT fk_payments_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE RESTRICT,
  CONSTRAINT fk_payments_student   FOREIGN KEY (student_id)   REFERENCES students(id)   ON DELETE RESTRICT,
  CONSTRAINT fk_payments_user      FOREIGN KEY (created_by)   REFERENCES users(id)      ON DELETE RESTRICT,
  INDEX idx_payments_kelas (classroom_id, tanggal),
  INDEX idx_payments_siswa (classroom_id, student_id, tanggal)
);

-- Inti fitur rapel & cicil: satu pembayaran bisa dipecah ke banyak tagihan,
-- satu tagihan bisa menerima dari banyak pembayaran.
-- Kelebihan bayar = payments.jumlah yang belum teralokasi (deposit siswa).
CREATE TABLE payment_allocations (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  classroom_id    BIGINT UNSIGNED NOT NULL,
  payment_id      BIGINT UNSIGNED NOT NULL,
  bill_id         BIGINT UNSIGNED NOT NULL,
  jumlah          DECIMAL(12,2)   NOT NULL,
  created_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NULL,
  CONSTRAINT fk_alloc_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE RESTRICT,
  CONSTRAINT fk_alloc_payment   FOREIGN KEY (payment_id)   REFERENCES payments(id)   ON DELETE CASCADE,
  CONSTRAINT fk_alloc_bill      FOREIGN KEY (bill_id)      REFERENCES bills(id)      ON DELETE RESTRICT,
  INDEX idx_alloc_bill (classroom_id, bill_id)
);

-- classroom_id NULL = kategori bawaan sistem, tersedia untuk semua kelas.
CREATE TABLE expense_categories (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  classroom_id    BIGINT UNSIGNED NULL,
  nama            VARCHAR(50)     NOT NULL,
  CONSTRAINT fk_cat_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_cat (classroom_id, nama)
);

CREATE TABLE expenses (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  classroom_id    BIGINT UNSIGNED NOT NULL,
  tanggal         DATE            NOT NULL,
  category_id     BIGINT UNSIGNED NOT NULL,
  campaign_id     BIGINT UNSIGNED NULL,              -- dipakai mulai v1.1
  jumlah          DECIMAL(12,2)   NOT NULL,
  keterangan      VARCHAR(255)    NOT NULL,
  bukti_path      VARCHAR(255)    NULL,
  created_by      BIGINT UNSIGNED NOT NULL,
  created_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NULL,
  deleted_at      TIMESTAMP       NULL,
  CONSTRAINT fk_expenses_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id)         ON DELETE RESTRICT,
  CONSTRAINT fk_expenses_cat       FOREIGN KEY (category_id)  REFERENCES expense_categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_expenses_user      FOREIGN KEY (created_by)   REFERENCES users(id)              ON DELETE RESTRICT,
  INDEX idx_expenses_kelas (classroom_id, tanggal)
);

CREATE TABLE audit_logs (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  classroom_id    BIGINT UNSIGNED NULL,              -- NULL untuk aksi tingkat akun
  user_id         BIGINT UNSIGNED NULL,
  aksi            ENUM('create','update','delete','restore','login','register',
                       'rotate_token','close_book','reopen_book','transfer_owner') NOT NULL,
  nama_tabel      VARCHAR(50)     NOT NULL,
  record_id       BIGINT UNSIGNED NULL,
  data_lama       JSON            NULL,
  data_baru       JSON            NULL,
  ip              VARCHAR(45)     NULL,
  created_at      TIMESTAMP       NULL,
  CONSTRAINT fk_audit_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE RESTRICT,
  CONSTRAINT fk_audit_user      FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE RESTRICT,
  INDEX idx_audit_kelas (classroom_id, created_at),
  INDEX idx_audit_record (nama_tabel, record_id)
);

-- ---------- v1.1 (migrasi terpisah, JANGAN dibuat di fase v1) ----------

CREATE TABLE campaigns (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  classroom_id       BIGINT UNSIGNED NOT NULL,
  nama               VARCHAR(100)  NOT NULL,
  deskripsi          VARCHAR(255)  NULL,
  nominal_per_siswa  DECIMAL(12,2) NOT NULL,
  deadline           DATE          NULL,
  status             ENUM('aktif','selesai','dibatalkan') NOT NULL DEFAULT 'aktif',
  created_by         BIGINT UNSIGNED NOT NULL,
  created_at         TIMESTAMP     NULL,
  updated_at         TIMESTAMP     NULL,
  CONSTRAINT fk_campaigns_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaigns_user      FOREIGN KEY (created_by)   REFERENCES users(id)      ON DELETE RESTRICT,
  INDEX idx_campaigns_kelas (classroom_id, status)
);

ALTER TABLE bills
  ADD CONSTRAINT fk_bills_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE RESTRICT;

ALTER TABLE expenses
  ADD CONSTRAINT fk_expenses_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE RESTRICT,
  ADD INDEX idx_expenses_campaign (classroom_id, campaign_id);

-- ---------- v1.2 (migrasi terpisah) ----------

CREATE TABLE book_closings (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  classroom_id    BIGINT UNSIGNED NOT NULL,
  label           VARCHAR(100)    NOT NULL,
  tgl_mulai       DATE            NOT NULL,
  tgl_selesai     DATE            NOT NULL,
  saldo_awal      DECIMAL(12,2)   NOT NULL,
  total_masuk     DECIMAL(12,2)   NOT NULL,
  total_keluar    DECIMAL(12,2)   NOT NULL,
  saldo_akhir     DECIMAL(12,2)   NOT NULL,
  catatan         TEXT            NULL,
  closed_by       BIGINT UNSIGNED NOT NULL,
  closed_at       TIMESTAMP       NOT NULL,
  CONSTRAINT fk_closing_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE RESTRICT,
  CONSTRAINT fk_closing_user      FOREIGN KEY (closed_by)    REFERENCES users(id)      ON DELETE RESTRICT,
  INDEX idx_closing_kelas (classroom_id, tgl_mulai, tgl_selesai)
);

-- Angka ringkasan di book_closings SENGAJA disimpan (snapshot historis),
-- berbeda dari saldo berjalan yang selalu dihitung ulang.
-- Transaksi dalam rentang closing ditolak untuk diubah/dihapus —
-- validasi WAJIB di server, bukan hanya di UI.
