@php
    $labelAksi = [
        'create' => ['Dibuat', 'lunas'],
        'update' => ['Diubah', 'info'],
        'delete' => ['Dihapus', 'belum'],
        'restore' => ['Dipulihkan', 'lunas'],
        'login' => ['Masuk', 'netral'],
        'register' => ['Daftar', 'netral'],
        'rotate_token' => ['Token dirotasi', 'kurang'],
        'close_book' => ['Tutup buku', 'info'],
        'reopen_book' => ['Buka buku', 'kurang'],
        'transfer_owner' => ['Alih kepemilikan', 'kurang'],
    ];
@endphp

<x-layouts.app judul="Audit log">
    <x-ui.alert tipe="info" judul="Halaman ini hanya bisa dibaca">
        Setiap perubahan data tercatat di sini dan tidak bisa dihapus atau diubah, termasuk olehmu sendiri.
        Inilah yang membuat catatan kas bisa dipertanggungjawabkan saat ada yang bertanya.
    </x-ui.alert>

    <x-ui.card padat>
        <form method="GET" action="{{ route('audit.index') }}"
              class="flex flex-wrap items-end gap-3 border-b border-line px-4 py-3.5 sm:px-5">
            <div class="w-56">
                <label for="tabel" class="block text-sm font-semibold">Saring jenis data</label>
                <select id="tabel" name="tabel" onchange="this.form.submit()"
                        class="mt-1.5 block w-full min-h-11 rounded-xl border border-line-strong bg-card px-3 py-2.5 text-base">
                    <option value="">Semua</option>
                    @foreach ($pilihanTabel as $nilai => $teks)
                        <option value="{{ $nilai }}" @selected($tabel === $nilai)>{{ $teks }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui.button type="submit" variant="secondary">Terapkan</x-ui.button>
        </form>

        @if ($daftarLog->isEmpty())
            <x-ui.empty ikon="audit" judul="Belum ada catatan">
                Catatan muncul otomatis begitu ada data yang dibuat, diubah, atau dihapus.
            </x-ui.empty>
        @else
            <ul class="divide-y divide-line">
                @foreach ($daftarLog as $log)
                    @php [$teksAksi, $nadaAksi] = $labelAksi[$log->aksi] ?? [$log->aksi, 'netral']; @endphp
                    <li x-data="{ rinci: false }" class="px-4 py-3 sm:px-5">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <x-ui.badge :tipe="$nadaAksi">{{ $teksAksi }}</x-ui.badge>

                            <span class="font-semibold">
                                {{ $pilihanTabel[$log->nama_tabel] ?? $log->nama_tabel }}
                                @if ($log->record_id)
                                    <span class="font-normal text-ink-faint">#{{ $log->record_id }}</span>
                                @endif
                            </span>

                            <span class="text-sm text-ink-faint">
                                {{ $log->user?->nama ?? 'sistem' }} ·
                                {{ $log->created_at?->translatedFormat('j M Y, H:i') }}
                            </span>

                            @if ($log->data_lama || $log->data_baru)
                                <button type="button" @click="rinci = !rinci" :aria-expanded="rinci"
                                        class="ml-auto inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-brand transition-colors hover:bg-brand-soft">
                                    <x-icon name="mata" class="size-4" />
                                    <span x-text="rinci ? 'Sembunyikan' : 'Rincian'">Rincian</span>
                                </button>
                            @endif
                        </div>

                        <div x-cloak x-show="rinci" x-collapse class="mt-2 grid gap-2 sm:grid-cols-2">
                            @if ($log->data_lama)
                                <div class="rounded-xl bg-surface p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Sebelum</p>
                                    <dl class="mt-1 space-y-0.5 text-sm">
                                        @foreach ($log->data_lama as $kolom => $nilai)
                                            <div class="flex gap-2">
                                                <dt class="shrink-0 text-ink-faint">{{ $kolom }}:</dt>
                                                <dd class="min-w-0 break-words">{{ is_scalar($nilai) ? $nilai : json_encode($nilai) }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </div>
                            @endif

                            @if ($log->data_baru)
                                <div class="rounded-xl bg-surface p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Sesudah</p>
                                    <dl class="mt-1 space-y-0.5 text-sm">
                                        @foreach ($log->data_baru as $kolom => $nilai)
                                            <div class="flex gap-2">
                                                <dt class="shrink-0 text-ink-faint">{{ $kolom }}:</dt>
                                                <dd class="min-w-0 break-words">{{ is_scalar($nilai) ? $nilai : json_encode($nilai) }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($daftarLog->hasPages())
                <div class="border-t border-line px-4 py-3 sm:px-5">{{ $daftarLog->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</x-layouts.app>
