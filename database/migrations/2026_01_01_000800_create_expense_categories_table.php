<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** classroom_id NULL = kategori bawaan sistem, tersedia untuk semua kelas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->restrictOnDelete();
            $table->string('nama', 50);

            $table->unique(['classroom_id', 'nama'], 'uq_cat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
