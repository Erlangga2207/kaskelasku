<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Support\CurrentClassroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Kategori tambahan milik kelas sendiri. Kategori bawaan sistem tidak bisa diutak-atik. */
class ExpenseCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => [
                'required', 'string', 'max:50',
                Rule::unique('expense_categories', 'nama')->where('classroom_id', CurrentClassroom::id()),
            ],
        ], [
            'nama.unique' => 'Kategori dengan nama itu sudah ada di kelas ini.',
        ], ['nama' => 'nama kategori']);

        ExpenseCategory::create($data);

        return back()->with('sukses', "Kategori {$data['nama']} ditambahkan.");
    }

    public function destroy(string $kategori): RedirectResponse
    {
        // Lewat relasi kelas: kategori bawaan sistem & milik kelas lain tidak ikut.
        $kategori = $this->kelas()->expenseCategories()->findOrFail($kategori);

        if ($kategori->expenses()->withTrashed()->exists()) {
            return back()->with(
                'galat',
                "Kategori {$kategori->nama} masih dipakai pengeluaran, jadi tidak bisa dihapus."
            );
        }

        $nama = $kategori->nama;
        $kategori->delete();

        return back()->with('sukses', "Kategori {$nama} dihapus.");
    }
}
