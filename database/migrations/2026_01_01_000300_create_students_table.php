<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data pribadi seminimal mungkin: nama, no absen, status.
 * DILARANG menambah NIS/NISN, nomor HP, alamat, atau foto — kewajiban UU PDP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->string('nama', 100);
            $table->unsignedSmallInteger('no_absen')->nullable();
            $table->date('tgl_mulai_aktif');
            $table->date('tgl_berhenti')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['classroom_id', 'is_active'], 'idx_students_kelas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
