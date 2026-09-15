<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akar tenancy. Konfigurasi kelas digabung di sini karena relasinya 1:1 dengan kelas
 * (lihat schema.sql) — tabel settings terpisah hanya akan menambah join tanpa manfaat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kelas', 100);
            $table->string('sekolah', 150);
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->enum('tipe_periode', ['mingguan', 'bulanan']);
            $table->char('public_token', 40)->unique();
            $table->timestamp('token_rotated_at')->nullable();
            $table->boolean('denda_aktif')->default(false);
            $table->enum('denda_mode', ['tetap', 'harian'])->default('tetap');
            $table->decimal('denda_nominal', 12, 2)->default(0);
            $table->unsignedSmallInteger('grace_days')->default(7);
            $table->decimal('denda_maks', 12, 2)->nullable();
            $table->text('template_pengingat')->nullable();      // v1.1
            $table->string('qris_path')->nullable();             // v1.1
            $table->string('qris_nama_pemilik', 100)->nullable(); // v1.1
            $table->timestamp('persetujuan_data_at')->nullable(); // bukti persetujuan UU PDP
            $table->enum('status', ['aktif', 'nonaktif', 'dihapus'])->default('aktif');
            $table->timestamps();

            $table->index('status', 'idx_classrooms_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
