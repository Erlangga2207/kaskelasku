<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1.1 — iuran insidental.
 *
 * Migrasi TERPISAH, tidak menyentuh migrasi v1: kelas nyata sudah memakai
 * aplikasi ini, jadi tabel lama hanya boleh ditambahi, tidak pernah ditulis ulang.
 *
 * Kolom bills.campaign_id dan expenses.campaign_id sudah dibuat sejak v1
 * (lihat migrasi 000500 & 000900). Yang ditambahkan di sini hanya tabel
 * campaigns beserta foreign key-nya — supaya urutan migrasi tetap masuk akal
 * pada database yang sudah berisi data.
 *
 * CHECK constraint chk_bills_sumber (tepat satu dari period_id/campaign_id)
 * dibuat di migrasi v1 dan sengaja TIDAK disentuh di sini. Menambah foreign key
 * tidak menghapus CHECK — diuji langsung di IuranInsidentalTest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->string('nama', 100);
            $table->string('deskripsi')->nullable();
            $table->decimal('nominal_per_siswa', 12, 2);
            $table->date('deadline')->nullable();
            $table->enum('status', ['aktif', 'selesai', 'dibatalkan'])->default('aktif');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['classroom_id', 'status'], 'idx_campaigns_kelas');
        });

        // Tagihan campaign menunjuk ke sini. RESTRICT: campaign yang masih punya
        // tagihan tidak boleh lenyap dan meninggalkan tagihan tanpa induk.
        DB::statement('ALTER TABLE bills ADD CONSTRAINT fk_bills_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE RESTRICT');

        DB::statement('ALTER TABLE expenses ADD CONSTRAINT fk_expenses_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE expenses ADD INDEX idx_expenses_campaign (classroom_id, campaign_id)');
    }

    public function down(): void
    {
        // Foreign key dilepas lebih dulu, kalau tidak tabelnya menolak di-drop.
        DB::statement('ALTER TABLE expenses DROP FOREIGN KEY fk_expenses_campaign');
        DB::statement('ALTER TABLE expenses DROP INDEX idx_expenses_campaign');
        DB::statement('ALTER TABLE bills DROP FOREIGN KEY fk_bills_campaign');

        Schema::dropIfExists('campaigns');
    }
};
