<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kategori bawaan sistem (classroom_id NULL) dibuat lewat migrasi, bukan seeder.
 *
 * Alasannya: tanpa kategori, pengeluaran pertama tidak bisa dicatat sama sekali.
 * Seeder tidak selalu dijalankan di produksi, sedangkan migrasi selalu.
 */
return new class extends Migration
{
    private array $kategori = [
        'Konsumsi', 'Alat Tulis', 'Kebersihan', 'Dekorasi Kelas', 'Kegiatan Kelas', 'Lain-lain',
    ];

    public function up(): void
    {
        foreach ($this->kategori as $nama) {
            $ada = DB::table('expense_categories')->whereNull('classroom_id')->where('nama', $nama)->exists();

            if (! $ada) {
                DB::table('expense_categories')->insert(['classroom_id' => null, 'nama' => $nama]);
            }
        }
    }

    public function down(): void
    {
        // Hanya kategori bawaan yang belum pernah dipakai pengeluaran mana pun.
        DB::table('expense_categories')
            ->whereNull('classroom_id')
            ->whereIn('nama', $this->kategori)
            ->whereNotIn('id', DB::table('expenses')->select('category_id'))
            ->delete();
    }
};
