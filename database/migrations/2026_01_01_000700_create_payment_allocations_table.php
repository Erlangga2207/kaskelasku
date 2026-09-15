<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inti fitur rapel & cicil: satu pembayaran bisa dipecah ke banyak tagihan,
 * satu tagihan bisa menerima dari banyak pembayaran.
 * Kelebihan bayar = payments.jumlah yang belum teralokasi (deposit siswa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained('bills')->restrictOnDelete();
            $table->decimal('jumlah', 12, 2);
            $table->timestamps();

            $table->index(['classroom_id', 'bill_id'], 'idx_alloc_bill');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
