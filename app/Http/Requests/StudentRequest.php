<?php

namespace App\Http\Requests;

use App\Support\CurrentClassroom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CurrentClassroom::check();
    }

    public function rules(): array
    {
        $siswaId = $this->route('siswa');

        return [
            'nama' => ['required', 'string', 'max:100'],
            // Nomor absen unik DI DALAM kelas — constraint apa pun selalu
            // diawali classroom_id, tidak pernah unik lintas kelas.
            'no_absen' => [
                'nullable', 'integer', 'min:1', 'max:200',
                Rule::unique('students', 'no_absen')
                    ->where('classroom_id', CurrentClassroom::id())
                    ->whereNull('deleted_at')
                    ->ignore($siswaId),
            ],
            'tgl_mulai_aktif' => ['required', 'date'],
            'tgl_berhenti' => ['nullable', 'date', 'after_or_equal:tgl_mulai_aktif'],
        ];
    }

    public function messages(): array
    {
        return [
            'no_absen.unique' => 'Nomor absen itu sudah dipakai siswa lain di kelas ini.',
            'tgl_berhenti.after_or_equal' => 'Tanggal berhenti tidak boleh mendahului tanggal mulai aktif.',
        ];
    }
}
