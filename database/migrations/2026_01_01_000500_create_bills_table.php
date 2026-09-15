<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tagihan: sejumlah uang yang ditagihkan ke seorang siswa.
 * Sumbernya PERSIS SATU dari: periode rutin (v1) atau campaign insidental (v1.1).
 * FK campaign_id sengaja belum dipasang di sini — tabel campaigns baru ada di v1.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('period_id')->nullable()->constrained('periods')->restrictOnDelete();
            $table->unsignedBigInteger('campaign_id')->nullable(); // dipakai mulai v1.1
            $table->decimal('nominal', 12, 2);
            $table->boolean('is_bebas')->default(false);
            $table->string('alasan_bebas')->nullable();
            $table->timestamps();

            $table->unique(['classroom_id', 'student_id', 'period_id'], 'uq_bills_periode');
            $table->unique(['classroom_id', 'student_id', 'campaign_id'], 'uq_bills_campaign');
            $table->index(['classroom_id', 'student_id'], 'idx_bills_kelas');
        });

        // CHECK constraint butuh MySQL 8.0.16+ / MariaDB 10.2+ (diverifikasi di Fase 0).
        DB::statement('ALTER TABLE bills ADD CONSTRAINT chk_bills_sumber CHECK ((period_id IS NOT NULL AND campaign_id IS NULL) OR (period_id IS NULL AND campaign_id IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
