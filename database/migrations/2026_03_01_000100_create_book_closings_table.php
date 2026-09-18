<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1.2 — tutup buku & serah terima.
 *
 * Migrasi TERPISAH, tidak menyentuh migrasi v1/v1.1: kelas nyata sudah memakai
 * aplikasi ini, jadi tabel lama hanya boleh ditambahi, tidak pernah ditulis ulang.
 *
 * Angka ringkasan di tabel ini SENGAJA disimpan, berbeda dari seluruh angka lain
 * di aplikasi yang selalu dihitung ulang dari transaksi. Alasannya: ini catatan
 * historis serah terima, bukan saldo berjalan. Kalau suatu saat transaksi lama
 * dikoreksi lewat penyesuaian, laporan serah terima yang sudah ditandatangani
 * harus tetap menunjukkan angka yang ditandatangani waktu itu — bukan angka yang
 * ikut berubah diam-diam di belakang tanda tangan orang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->string('label', 100);
            $table->date('tgl_mulai');
            $table->date('tgl_selesai');
            $table->decimal('saldo_awal', 12, 2);
            $table->decimal('total_masuk', 12, 2);
            $table->decimal('total_keluar', 12, 2);
            $table->decimal('saldo_akhir', 12, 2);
            $table->text('catatan')->nullable();
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at');

            // Dipakai untuk pertanyaan yang paling sering diajukan tabel ini:
            // "apakah tanggal X terkunci di kelas ini?" — dijalankan pada setiap
            // simpan/ubah/hapus transaksi, jadi harus murah.
            $table->index(['classroom_id', 'tgl_mulai', 'tgl_selesai'], 'idx_closing_kelas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_closings');
    }
};
