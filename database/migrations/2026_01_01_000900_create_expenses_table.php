<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pengeluaran kas. FK campaign_id menyusul di migrasi v1.1. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->date('tanggal');
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->unsignedBigInteger('campaign_id')->nullable(); // dipakai mulai v1.1
            $table->decimal('jumlah', 12, 2);
            $table->string('keterangan');
            $table->string('bukti_path')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['classroom_id', 'tanggal'], 'idx_expenses_kelas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
