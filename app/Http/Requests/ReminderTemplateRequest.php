<?php

namespace App\Http\Requests;

use App\Services\PengingatService;
use App\Support\CurrentClassroom;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Template pengingat adalah data PER KELAS, bukan setelan global aplikasi.
 * classroom_id-nya karena itu tidak pernah ikut di request — kelas aktif
 * diambil dari session bendahara.
 */
class ReminderTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CurrentClassroom::check();
    }

    public function rules(): array
    {
        return [
            'template_pengingat' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Template tanpa {rincian} maupun {total} akan menghasilkan pesan yang
     * tidak menyebut satu angka pun — bendahara hampir pasti tidak
     * memaksudkan itu, jadi ditahan di depan dengan pesan yang menjelaskan.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $template = (string) $this->input('template_pengingat');

                if (str_contains($template, '{rincian}') || str_contains($template, '{total}')) {
                    return;
                }

                $validator->errors()->add('template_pengingat', sprintf(
                    'Template harus memuat setidaknya {rincian} atau {total}, kalau tidak pesannya '
                        .'tidak akan menyebut nominal apa pun. Placeholder yang tersedia: %s.',
                    implode(' ', PengingatService::PLACEHOLDER),
                ));
            },
        ];
    }

    public function messages(): array
    {
        return [
            'template_pengingat.required' => 'Template pengingat tidak boleh kosong.',
            'template_pengingat.max' => 'Template pengingat maksimal 2000 karakter.',
        ];
    }
}
