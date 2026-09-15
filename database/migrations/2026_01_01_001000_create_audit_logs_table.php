<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Hanya bisa ditulis. classroom_id NULL untuk aksi tingkat akun (login, register). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('aksi', [
                'create', 'update', 'delete', 'restore', 'login', 'register',
                'rotate_token', 'close_book', 'reopen_book', 'transfer_owner',
            ]);
            $table->string('nama_tabel', 50);
            $table->unsignedBigInteger('record_id')->nullable();
            $table->json('data_lama')->nullable();
            $table->json('data_baru')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['classroom_id', 'created_at'], 'idx_audit_kelas');
            $table->index(['nama_tabel', 'record_id'], 'idx_audit_record');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
