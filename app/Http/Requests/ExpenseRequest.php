<?php

namespace App\Http\Requests;

use App\Models\Campaign;
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
            // Pengeluaran boleh ditandai milik satu campaign yang masih berjalan.
            // Campaign yang dibatalkan tidak bisa dipilih: dananya sudah kembali
            // menjadi deposit siswa, jadi tidak ada lagi yang bisa dibelanjakan.
            'campaign_id' => [
                'nullable',
                Rule::exists('campaigns', 'id')
                    ->where('classroom_id', CurrentClassroom::id())
                    ->whereIn('status', ['aktif', 'selesai']),
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
     * Uang kelas tidak boleh dibelanjakan melebihi haknya. Pengecekan ini WAJIB
     * di server — menyembunyikan tombol di Blade bukan pengamanan.
     *
     * Sejak v1.1 batasnya ada dua, bukan satu:
     *   - pengeluaran bertanda campaign  → sisa dana campaign itu sendiri
     *   - pengeluaran biasa              → saldo bebas (kas − dana campaign)
     *
     * Tanpa pemisahan ini, uang studi tour bisa habis untuk beli spidol tanpa
     * seorang pun sadar sampai hari keberangkatan.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('jumlah') || $validator->errors()->has('campaign_id')) {
                    return;
                }

                $kas = app(KasService::class);

                // Saat mengubah, nominal lama dikembalikan dulu ke potnya masing-masing
                // supaya Rp 50.000 yang jadi Rp 60.000 tidak dinilai sebagai Rp 110.000.
                $lama = $this->route('pengeluaran');
                $expense = $lama ? Expense::find($lama) : null;

                $campaign = $this->input('campaign_id')
                    ? Campaign::find($this->input('campaign_id'))
                    : null;

                // Kas fisik tetap jadi atap: sisa campaign di atas kertas tidak
                // pernah boleh menarik uang yang tidak ada di kas.
                $kasFisik = $kas->saldoKas() + ($expense ? Uang::keSen($expense->jumlah) : 0);

                if ($campaign) {
                    $sisaCampaign = $kas->sisaCampaign($campaign, $expense);
                    $tersedia = max(0, min($sisaCampaign, $kasFisik));
                    $pesan = $sisaCampaign <= $kasFisik
                        ? 'Jumlah melebihi sisa dana campaign "'.$campaign->nama.'" ('
                            .Uang::format(Uang::keDesimal(max(0, $sisaCampaign))).'). '
                            .'Yang bisa dibelanjakan hanya uang yang sudah benar-benar terkumpul untuk campaign ini.'
                        : 'Jumlah melebihi saldo kas yang tersedia ('.Uang::format(Uang::keDesimal($kasFisik)).').';
                } else {
                    $tersedia = max(0, $kas->saldoBebas($expense));
                    $pesan = 'Jumlah melebihi saldo bebas ('.Uang::format(Uang::keDesimal($tersedia)).'). '
                        .'Sisa saldo kas sudah menjadi milik campaign yang sedang berjalan — '
                        .'tandai pengeluaran ini sebagai milik campaign bila memang untuk keperluan itu.';
                }

                if (Uang::keSen($this->input('jumlah')) > $tersedia) {
                    $validator->errors()->add('jumlah', $pesan);
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'Kategori yang dipilih tidak tersedia untuk kelas ini.',
            'campaign_id.exists' => 'Campaign yang dipilih tidak tersedia untuk kelas ini.',
            'jumlah.gt' => 'Jumlah pengeluaran harus lebih dari nol.',
            'tanggal.before_or_equal' => 'Tanggal pengeluaran tidak boleh di masa depan.',
            'bukti.max' => 'Ukuran bukti maksimal 2 MB.',
        ];
    }
}
