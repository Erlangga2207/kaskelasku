<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],

            // Persetujuan dicatat sejak pendaftaran, bukan dianggap otomatis.
            'setuju_syarat' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah terdaftar. Coba masuk, atau pakai email lain.',
            'setuju_syarat.accepted' => 'Centang dulu persetujuan Syarat Layanan dan Kebijakan Privasi.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama' => 'nama',
            'email' => 'email',
            'password' => 'kata sandi',
        ];
    }
}
