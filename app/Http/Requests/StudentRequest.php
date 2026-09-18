<?php

namespace App\Http\Requests;

use App\Support\CurrentClassroom;
use App\Support\Kapasitas;
use Illuminate\Contracts\Validation\Validator;
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

    /**
     * Batas siswa per kelas hanya berlaku saat MENAMBAH.
     *
     * Kelas yang sudah telanjur melewati batas (misalnya karena batasnya pernah
     * lebih tinggi) tetap harus bisa menyunting siswa yang sudah ada — mengunci
     * penyuntingan tidak mengurangi beban server sama sekali, hanya membuat data
     * lama tidak bisa diperbaiki.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->route('siswa') !== null || ! Kapasitas::kelasSudahPenuh()) {
                    return;
                }

                $validator->errors()->add('nama', sprintf(
                    'Kelas ini sudah mencapai batas %d siswa. Nonaktifkan siswa yang sudah keluar '
                        .'kalau memang ada, atau hubungi pengelola kalau kelasnya memang lebih besar dari itu.',
                    Kapasitas::batasSiswaPerKelas(),
                ));
            },
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
