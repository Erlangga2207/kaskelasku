<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\CurrentClassroom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Serah terima kelas ke bendahara baru (PRD bagian 10).
 *
 * Dua jalan masuk, dibedakan otomatis dari email yang diketik:
 *   - email sudah terdaftar  → kelas dialihkan ke akun itu
 *   - email belum terdaftar  → akun baru dibuat, butuh nama + kata sandi awal
 *
 * Akun baru dibuat alih-alih menumpang akun lama karena satu akun = satu orang.
 * Kalau akun dipakai berdua, setiap baris audit log berhenti bisa menjawab
 * "siapa yang melakukan ini" — dan itu satu-satunya pertanyaan yang membuat
 * audit log berguna saat uang kelas dipersoalkan.
 */
class TransferOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CurrentClassroom::check();
    }

    public function rules(): array
    {
        $akunBaru = ! $this->calonSudahTerdaftar();

        return [
            'email' => ['required', 'email', 'max:150'],
            'nama' => [$akunBaru ? 'required' : 'nullable', 'string', 'max:100'],
            'password' => [$akunBaru ? 'required' : 'nullable', 'confirmed', Password::min(8)],
            // Bendahara lama kehilangan akses begitu tombolnya ditekan. Aksi
            // seperti itu tidak boleh bisa terjadi karena salah klik.
            'konfirmasi' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Email ini belum terdaftar, jadi akunnya akan dibuat sekarang — tulis nama bendahara barunya.',
            'password.required' => 'Email ini belum terdaftar, jadi akunnya akan dibuat sekarang — tentukan kata sandi awalnya.',
            'konfirmasi.accepted' => 'Centang dulu konfirmasinya: setelah dialihkan, kamu kehilangan akses ke kelas ini.',
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'email bendahara baru',
            'nama' => 'nama bendahara baru',
            'password' => 'kata sandi awal',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $calon = $this->calon();

            if ($calon && $calon->id === $this->user()?->id) {
                $validator->errors()->add('email', 'Kelas ini sudah jadi milikmu — tidak ada yang perlu dialihkan.');

                return;
            }

            if ($calon && ! $calon->is_active) {
                $validator->errors()->add('email', 'Akun itu sedang dinonaktifkan, jadi tidak bisa menerima kelas.');
            }
        });
    }

    /** Akun bendahara baru bila emailnya sudah terdaftar. */
    public function calon(): ?User
    {
        $email = $this->input('email');

        return $email ? User::where('email', $email)->first() : null;
    }

    public function calonSudahTerdaftar(): bool
    {
        return $this->calon() !== null;
    }
}
