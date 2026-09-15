<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nominal melekat pada periode, supaya kenaikan iuran tidak mengubah tagihan lampau. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->string('label', 50);
            $table->enum('tipe', ['mingguan', 'bulanan']);
            $table->date('tgl_mulai');
            $table->date('tgl_selesai');
            $table->date('jatuh_tempo');
            $table->decimal('nominal', 12, 2);
            $table->boolean('is_libur')->default(false);
            $table->timestamps();

            $table->index(['classroom_id', 'jatuh_tempo'], 'idx_periods_kelas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
