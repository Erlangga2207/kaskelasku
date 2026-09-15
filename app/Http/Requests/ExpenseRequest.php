<?php

namespace App\Http\Requests;

use App\Models\Expense;
use App\Services\KasService;
use App\Support\CurrentClassroom;
use App\Support\Uang;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CurrentClassroom::check();
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            // Kategori milik kelas ini ATAU kategori bawaan sistem (classroom_id NULL).
            'category_id' => [
                'required',
                Rule::exists('expense_categories', 'id')->where(
                    fn ($q) => $q->where('classroom_id', CurrentClassroom::id())->orWhereNull('classroom_id')
                ),
            ],
            'jumlah' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'keterangan' => ['required', 'string', 'max:255'],
            'bukti' => [
                'nullable', 'file', 'max:2048',
                'mimes:jpg,jpeg,png,pdf',
                'mimetypes:image/jpeg,image/png,application/pdf',
            ],
        ];
    }

    /**
     * Kas kelas tidak boleh minus. Pengecekan ini WAJIB di server —
     * menyembunyikan tombol di Blade bukan pengamanan.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('jumlah')) {
                    return;
                }

                $kas = app(KasService::class);
                $tersedia = $kas->saldoKas();

                // Saat mengubah, nominal lama dikembalikan dulu ke saldo.
                $lama = $this->route('pengeluaran');

                if ($lama) {
                    $expense = Expense::find($lama);
                    $tersedia += $expense ? Uang::keSen($expense->jumlah) : 0;
                }

                if (Uang::keSen($this->input('jumlah')) > $tersedia) {
                    $validator->errors()->add(
                        'jumlah',
                        'Jumlah melebihi saldo kas yang tersedia ('.Uang::format(Uang::keDesimal($tersedia)).').'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'Kategori yang dipilih tidak tersedia untuk kelas ini.',
            'jumlah.gt' => 'Jumlah pengeluaran harus lebih dari nol.',
            'tanggal.before_or_equal' => 'Tanggal pengeluaran tidak boleh di masa depan.',
            'bukti.max' => 'Ukuran bukti maksimal 2 MB.',
        ];
    }
}
