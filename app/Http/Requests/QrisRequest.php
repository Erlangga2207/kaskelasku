<?php

namespace App\Http\Requests;

use App\Support\CurrentClassroom;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Unggahan QRIS — sama ketatnya dengan bukti transfer.
 *
 * Yang dicek: ukuran, ekstensi, DAN mimetype asli berkasnya. Ekstensi saja
 * tidak cukup; berkas apa pun bisa dinamai .png. PDF sengaja tidak diterima
 * di sini (beda dari bukti transfer) karena gambar ini akan dipasang sebagai
 * <img> di halaman kelas.
 */
class QrisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CurrentClassroom::check();
    }

    public function rules(): array
    {
        // Mengganti nama pemilik tanpa mengunggah ulang gambarnya harus boleh,
        // jadi berkasnya wajib hanya saat kelas belum punya QRIS.
        $sudahAda = CurrentClassroom::getOrFail()->punyaQris();

        return [
            'qris' => [
                $sudahAda ? 'nullable' : 'required',
                'file', 'max:2048',
                'mimes:jpg,jpeg,png',
                'mimetypes:image/jpeg,image/png',
            ],
            'qris_nama_pemilik' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'qris.required' => 'Pilih gambar QRIS-nya dulu.',
            'qris.max' => 'Ukuran gambar QRIS maksimal 2 MB.',
            'qris.mimes' => 'Gambar QRIS harus JPG atau PNG.',
            'qris.mimetypes' => 'Berkas itu bukan gambar JPG/PNG yang sah.',
            'qris_nama_pemilik.required' => 'Tulis nama pemilik rekening/QRIS-nya, '
                .'supaya anggota kelas yakin uangnya tidak salah tujuan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'qris' => 'gambar QRIS',
            'qris_nama_pemilik' => 'nama pemilik QRIS',
        ];
    }
}
