<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Uang yang diterima bendahara. Tidak pernah dihapus permanen — soft delete + audit log. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->date('tanggal');
            $table->decimal('jumlah', 12, 2);
            $table->enum('metode', ['tunai', 'transfer'])->default('tunai');
            $table->string('catatan')->nullable();
            $table->string('bukti_path')->nullable(); // storage privat, per kelas
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['classroom_id', 'tanggal'], 'idx_payments_kelas');
            $table->index(['classroom_id', 'student_id', 'tanggal'], 'idx_payments_siswa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
