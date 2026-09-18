<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.0 — peluncuran publik.
 *
 * Migrasi TERPISAH, tidak menyentuh migrasi v1/v1.1/v1.2: produksi sudah berisi
 * data kelas nyata, jadi tabel lama hanya boleh ditambahi.
 *
 * Kolom `status` pada classrooms sudah punya nilai 'dihapus' sejak v1, tapi
 * belum ada catatan KAPAN dihapusnya — tanpa itu tenggang 30 hari tidak bisa
 * dihitung. Itu yang ditambahkan di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            // Penanda tenggang hapus. NULL = kelas tidak sedang dijadwalkan hilang.
            $table->timestamp('dihapus_pada')->nullable()->after('status');

            // Kelas demo: read-only, boleh dilihat siapa saja, direset berkala.
            // Dipisah dari status supaya demo tetap bisa 'aktif' seperti kelas biasa.
            $table->boolean('is_demo')->default(false)->after('dihapus_pada');

            $table->index(['status', 'dihapus_pada'], 'idx_classrooms_daur');
        });

        /*
         * Waiting list dipakai saat kuota kelas se-sistem penuh.
         *
         * Sengaja HANYA menyimpan email. Bukan nama, bukan sekolah, bukan nomor
         * HP: data yang tidak dikumpulkan adalah data yang tidak bisa bocor, dan
         * untuk mengabari "tempatnya sudah ada" satu email sudah cukup.
         */
        Schema::create('waiting_list_entries', function (Blueprint $table) {
            $table->id();
            $table->string('email', 150)->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiting_list_entries');

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropIndex('idx_classrooms_daur');
            $table->dropColumn(['dihapus_pada', 'is_demo']);
        });
    }
};
