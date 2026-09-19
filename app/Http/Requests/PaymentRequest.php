<?php

namespace App\Http\Requests;

use App\Models\BookClosing;
use App\Support\CurrentClassroom;
use App\Support\Uang;
use Illuminate\Contracts\Validation\Validator;
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
            'alokasi.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],

            // Validasi MIME asli, bukan sekadar ekstensi nama berkas.
            'bukti' => [
                'nullable', 'file', 'max:2048',
                'mimes:jpg,jpeg,png,pdf',
                'mimetypes:image/jpeg,image/png,application/pdf',
            ],
        ];
    }

    /**
     * Total alokasi manual tidak boleh melebihi uang yang benar-benar diterima.
     *
     * KasService juga menolaknya, tapi di sana penolakannya baru terjadi saat
     * menulis ke database dan muncul sebagai pesan galat umum. Dicek di sini
     * supaya bendahara dapat pesan yang menempel di bagian alokasinya, lengkap
     * dengan angka selisihnya.
     */
    public function after(): array
    {
        return [
            // Penguncian tutup buku. Penolakan yang sebenarnya terjadi di model
            // (trait TerkunciTutupBuku) dan tidak bisa dilewati lewat jalur mana
            // pun; yang dikerjakan di sini hanya memindahkan pesannya ke bawah
            // kolom tanggal, tempat bendahara bisa langsung memperbaikinya.
            function (Validator $validator) {
                if ($closing = BookClosing::penguncian($this->input('tanggal'))) {
                    $validator->errors()->add('tanggal', $closing->pesanPenolakan('mencatat pembayaran ini'));
                }
            },
            function (Validator $validator) {
                if ($this->input('mode_alokasi') !== 'manual') {
                    return;
                }

                $total = $this->totalAlokasiSen();
                $dibayar = Uang::keSen($this->input('jumlah'));

                if ($total <= $dibayar) {
                    return;
                }

                $validator->errors()->add('alokasi', sprintf(
                    'Total alokasi %s melebihi jumlah pembayaran %s (kelebihan %s). '
                        .'Kurangi alokasinya, atau naikkan jumlah yang dibayar.',
                    Uang::format(Uang::keDesimal($total)),
                    Uang::format(Uang::keDesimal($dibayar)),
                    Uang::format(Uang::keDesimal($total - $dibayar)),
                ));
            },
        ];
    }

    /** Nilai non-angka diabaikan di sini; rule 'alokasi.*' yang menolaknya. */
    protected function totalAlokasiSen(): int
    {
        $rincian = $this->input('alokasi');

        if (! is_array($rincian)) {
            return 0;
        }

        $total = 0;

        foreach ($rincian as $nilai) {
            if (! is_scalar($nilai) || ! is_numeric($nilai)) {
                continue;
            }

            $total += Uang::keSen($nilai);
        }

        return $total;
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
