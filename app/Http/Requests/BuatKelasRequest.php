<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Langkah pertama wizard: membuat kelas.
 *
 * Persetujuan data siswa diminta DI SINI, bukan di pendaftaran akun, karena di
 * sinilah data orang lain mulai masuk. Saat mendaftar, bendahara baru
 * menyerahkan datanya sendiri; saat membuat kelas, dia mulai memegang data
 * tiga puluh anak yang tidak pernah ditanya pendapatnya.
 */
class BuatKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasVerifiedEmail() === true;
    }

    public function rules(): array
    {
        return [
            'nama_kelas' => ['required', 'string', 'max:100'],
            'sekolah' => ['required', 'string', 'max:150'],
            'tipe_periode' => ['required', Rule::in(['mingguan', 'bulanan'])],
            'persetujuan_data' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'persetujuan_data.accepted' => 'Centang dulu pernyataan tanggung jawab data siswa. '
                .'Tanpa itu kelas tidak bisa dibuat.',
            'nama_kelas.required' => 'Tulis nama kelasnya, misalnya "XII TRPL 1".',
            'sekolah.required' => 'Tulis nama sekolahnya.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_kelas' => 'nama kelas',
            'sekolah' => 'nama sekolah',
            'tipe_periode' => 'tipe periode',
        ];
    }
}
