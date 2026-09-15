<?php

namespace App\Http\Requests;

use App\Support\CurrentClassroom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CurrentClassroom::check();
    }

    public function rules(): array
    {
        return [
            // exists dibatasi classroom_id: ID siswa kelas lain langsung ditolak validasi.
            'student_id' => [
                'required',
                Rule::exists('students', 'id')
                    ->where('classroom_id', CurrentClassroom::id())
                    ->whereNull('deleted_at'),
            ],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'jumlah' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'metode' => ['required', Rule::in(['tunai', 'transfer'])],
            'catatan' => ['nullable', 'string', 'max:255'],

            'mode_alokasi' => ['nullable', Rule::in(['otomatis', 'manual'])],
            'alokasi' => ['nullable', 'array'],
            'alokasi.*' => ['nullable', 'numeric', 'min:0'],

            // Validasi MIME asli, bukan sekadar ekstensi nama berkas.
            'bukti' => [
                'nullable', 'file', 'max:2048',
                'mimes:jpg,jpeg,png,pdf',
                'mimetypes:image/jpeg,image/png,application/pdf',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.exists' => 'Siswa yang dipilih tidak ada di kelas ini.',
            'jumlah.gt' => 'Jumlah pembayaran harus lebih dari nol.',
            'tanggal.before_or_equal' => 'Tanggal pembayaran tidak boleh di masa depan.',
            'bukti.max' => 'Ukuran bukti maksimal 2 MB.',
            'bukti.mimetypes' => 'Bukti harus berupa foto JPG/PNG atau berkas PDF.',
        ];
    }
}
