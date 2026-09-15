<x-layouts.app judul="Siswa">
    <div class="flex flex-wrap items-center gap-2">
        <x-ui.button :href="route('siswa.create')" icon="tambah">Tambah siswa</x-ui.button>
        <x-ui.button :href="route('siswa.massal')" variant="secondary" icon="salin">Tempel daftar nama</x-ui.button>
    </div>

    <x-ui.card padat>
        <form method="GET" action="{{ route('siswa.index') }}"
              class="flex flex-wrap items-end gap-3 border-b border-line px-4 py-3.5 sm:px-5">
            <div class="min-w-48 flex-1">
                <label for="cari" class="sr-only">Cari nama siswa</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-ink-faint">
                        <x-icon name="cari" class="size-5" />
                    </span>
                    <input type="search" id="cari" name="cari" value="{{ $cari }}"
                           placeholder="Cari nama siswa"
                           class="block w-full min-h-11 rounded-xl border border-line-strong bg-card py-2.5 pl-11 pr-3 text-base
                                  placeholder:text-ink-faint focus:outline-2 focus:outline-offset-2 focus:outline-ring-brand">
                </div>
            </div>

            <div class="w-40">
                <label for="status" class="sr-only">Status siswa</label>
                <select id="status" name="status" onchange="this.form.submit()"
                        class="block w-full min-h-11 rounded-xl border border-line-strong bg-card px-3 py-2.5 text-base">
                    <option value="aktif" @selected($status === 'aktif')>Aktif ({{ $jumlahAktif }})</option>
                    <option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif ({{ $jumlahNonaktif }})</option>
                    <option value="semua" @selected($status === 'semua')>Semua</option>
                </select>
            </div>

            <x-ui.button type="submit" variant="secondary">Terapkan</x-ui.button>
        </form>

        @if ($daftarSiswa->isEmpty())
            <x-ui.empty ikon="siswa" judul="Belum ada siswa di daftar ini">
                Tambahkan siswa satu per satu, atau tempel seluruh daftar absen sekaligus.
                <x-slot:aksi>
                    <x-ui.button :href="route('siswa.massal')" icon="salin">Tempel daftar nama</x-ui.button>
                </x-slot:aksi>
            </x-ui.empty>
        @else
            {{-- Header kolom hanya muncul di layar lebar; di ponsel barisnya jadi kartu. --}}
            <div class="hidden border-b border-line px-5 py-2 text-xs font-semibold uppercase tracking-wide text-ink-faint sm:grid sm:grid-cols-[3rem_1fr_9rem_9rem]">
                <span>Absen</span>
                <span>Nama</span>
                <span>Status</span>
                <span class="text-right">Aksi</span>
            </div>

            <ul class="divide-y divide-line">
                @foreach ($daftarSiswa as $siswa)
                    <li class="grid gap-x-3 gap-y-1.5 px-4 py-3 sm:grid-cols-[3rem_1fr_9rem_9rem] sm:items-center sm:px-5">
                        <span class="text-sm font-semibold tabular text-ink-faint">
                            {{ $siswa->no_absen ? str_pad($siswa->no_absen, 2, '0', STR_PAD_LEFT) : '—' }}
                        </span>

                        <span class="font-semibold text-ink">{{ $siswa->nama }}</span>

                        <span class="flex flex-wrap items-center gap-1.5">
                            @if ($siswa->is_active)
                                <x-ui.badge tipe="lunas" ikon="cek">Aktif</x-ui.badge>
                            @else
                                <x-ui.badge tipe="netral">Nonaktif</x-ui.badge>
                            @endif

                            @if ($siswa->tgl_berhenti)
                                <span class="text-xs text-ink-faint">
                                    berhenti {{ $siswa->tgl_berhenti->translatedFormat('j M Y') }}
                                </span>
                            @endif
                        </span>

                        <span class="flex items-center gap-1 sm:justify-end">
                            <a href="{{ route('siswa.edit', $siswa) }}"
                               class="inline-flex min-h-11 items-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-brand transition-colors hover:bg-brand-soft">
                                <x-icon name="ubah" class="size-4" />
                                <span>Ubah</span>
                            </a>

                            @if ($siswa->is_active)
                                <x-ui.confirm
                                    :action="route('siswa.destroy', $siswa)"
                                    judul="Hapus atau nonaktifkan siswa?"
                                    :pesan="'Bila '.$siswa->nama.' sudah punya transaksi, datanya hanya dinonaktifkan supaya riwayat pembayaran tetap utuh.'"
                                    tombol="Lanjutkan">
                                    <x-icon name="hapus" class="size-4" />
                                    <span class="sr-only sm:not-sr-only">Hapus</span>
                                </x-ui.confirm>
                            @else
                                <form method="POST" action="{{ route('siswa.restore', $siswa) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                            class="inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-masuk transition-colors hover:bg-masuk-soft">
                                        <x-icon name="putar" class="size-4" />
                                        <span>Aktifkan</span>
                                    </button>
                                </form>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>

            @if ($daftarSiswa->hasPages())
                <div class="border-t border-line px-4 py-3 sm:px-5">{{ $daftarSiswa->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</x-layouts.app>
