<?php

namespace App\Http\Requests;

use App\Support\CurrentClassroom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CurrentClassroom::check();
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'nominal_per_siswa' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'deadline' => ['nullable', 'date'],

            'peserta' => ['required', 'array', 'min:1'],
            // exists dibatasi classroom_id: ID siswa kelas lain ditolak validasi,
            // jauh sebelum sempat menjadi tagihan.
            'peserta.*' => [
                Rule::exists('students', 'id')
                    ->where('classroom_id', CurrentClassroom::id())
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'peserta.required' => 'Pilih minimal satu peserta — campaign tanpa peserta tidak menghasilkan tagihan apa pun.',
            'peserta.min' => 'Pilih minimal satu peserta — campaign tanpa peserta tidak menghasilkan tagihan apa pun.',
            'peserta.*.exists' => 'Ada peserta yang tidak terdaftar di kelas ini.',
            'nominal_per_siswa.gt' => 'Nominal per siswa harus lebih dari nol.',
        ];
    }

    /** @return array<int, int> */
    public function peserta(): array
    {
        return array_map('intval', $this->input('peserta', []));
    }
}
