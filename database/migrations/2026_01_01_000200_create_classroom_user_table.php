<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jembatan untuk serah terima bendahara (v1.2) dan akun multi-kelas (v2.0). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('peran', ['bendahara'])->default('bendahara');
            $table->timestamp('created_at')->nullable();

            $table->unique(['classroom_id', 'user_id'], 'uq_cu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_user');
    }
};
