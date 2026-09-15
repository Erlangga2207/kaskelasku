<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Halaman audit log: hanya baca. Tidak ada aksi ubah maupun hapus di sini. */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $tabel = $request->query('tabel');

        $log = AuditLog::query()
            ->with('user')
            ->when($tabel, fn ($q) => $q->where('nama_tabel', $tabel))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('audit.index', [
            'daftarLog' => $log,
            'tabel' => $tabel,
            'pilihanTabel' => [
                'students' => 'Siswa',
                'periods' => 'Periode',
                'bills' => 'Tagihan',
                'payments' => 'Pembayaran',
                'payment_allocations' => 'Alokasi pembayaran',
                'expenses' => 'Pengeluaran',
                'expense_categories' => 'Kategori',
            ],
        ]);
    }
}
