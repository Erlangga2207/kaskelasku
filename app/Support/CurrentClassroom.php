<?php

namespace App\Support;

use App\Models\Classroom;
use RuntimeException;

/**
 * Satu-satunya sumber "kelas aktif" untuk seluruh request.
 *
 * Nilainya HANYA boleh diisi dari dua tempat:
 *   1. Middleware bendahara — dibaca dari session milik user yang login.
 *   2. Middleware halaman publik — dibaca dari public_token pada URL.
 *
 * Tidak pernah dari form, query string, atau hidden input. Kalau nilainya kosong,
 * Global Scope memilih gagal-tertutup (mengembalikan nol baris) daripada
 * membocorkan baris milik kelas lain.
 */
class CurrentClassroom
{
    protected static ?Classroom $classroom = null;

    /** Penanda bahwa kita sedang sengaja bekerja lintas kelas (seeder, migrasi, resolusi token). */
    protected static bool $bypass = false;

    public static function set(Classroom $classroom): void
    {
        static::$classroom = $classroom;
    }

    public static function get(): ?Classroom
    {
        return static::$classroom;
    }

    /** Kelas aktif yang dijamin ada — dipakai controller yang memang butuh objeknya. */
    public static function getOrFail(): Classroom
    {
        return static::$classroom
            ?? throw new RuntimeException('Belum ada kelas aktif pada request ini.');
    }

    public static function id(): ?int
    {
        return static::$classroom?->id;
    }

    public static function check(): bool
    {
        return static::$classroom !== null;
    }

    public static function forget(): void
    {
        static::$classroom = null;
    }

    public static function bypassed(): bool
    {
        return static::$bypass;
    }

    /**
     * Menjalankan sepotong kode tanpa Global Scope kelas.
     *
     * Dipakai TERBATAS untuk: seeder, resolusi kelas dari token, dan query
     * lintas kelas milik admin platform. Jangan dipakai di controller bendahara.
     */
    public static function withoutTenancy(callable $callback): mixed
    {
        $previous = static::$bypass;
        static::$bypass = true;

        try {
            return $callback();
        } finally {
            static::$bypass = $previous;
        }
    }

    /** Dipakai seeder & test untuk berpindah konteks kelas secara eksplisit. */
    public static function runFor(Classroom $classroom, callable $callback): mixed
    {
        $previous = static::$classroom;
        static::$classroom = $classroom;

        try {
            return $callback();
        } finally {
            static::$classroom = $previous;
        }
    }
}
