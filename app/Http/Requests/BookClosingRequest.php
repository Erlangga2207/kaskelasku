<?php

namespace App\Http\Requests;

use App\Support\CurrentClassroom;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rentang yang akan ditutup.
 *
 * Yang TIDAK divalidasi di sini: tumpang tindih dengan closing lain. Itu
 * diperiksa di TutupBukuService, karena pemeriksaannya butuh query ke tabel
 * closing dan hasilnya harus sama baik aksinya datang dari form maupun dari
 * tempat lain. Validasi form hanya mengurus bentuk masukannya.
 */
class BookClosingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CurrentClassroom::check();
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'tgl_mulai' => ['required', 'date'],
            'tgl_selesai' => ['required', 'date', 'after_or_equal:tgl_mulai'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'Beri nama periodenya, misalnya "Semester Ganjil 2025/2026" — '
                .'nama ini yang muncul di laporan serah terima.',
            'tgl_selesai.after_or_equal' => 'Tanggal akhir tidak boleh mendahului tanggal mulai.',
        ];
    }

    public function attributes(): array
    {
        return [
            'label' => 'nama periode',
            'tgl_mulai' => 'tanggal mulai',
            'tgl_selesai' => 'tanggal akhir',
        ];
    }
}
