@php
    use App\Support\Uang;

    $baru = ! $pengeluaran->exists;
    $batas = $saldoKas + ($baru ? 0 : Uang::keSen($pengeluaran->jumlah));
    $batasBebas = max(0, $saldoBebas + ($baru ? 0 : Uang::keSen($pengeluaran->jumlah)));
@endphp

<x-layouts.app :judul="$baru ? 'Catat pengeluaran' : 'Ubah pengeluaran'">
    <x-ui.button :href="route('pengeluaran.index')" variant="ghost" icon="kiri" size="sm">Kembali ke daftar pengeluaran</x-ui.button>

    <x-ui.card :judul="$baru ? 'Pengeluaran baru' : 'Ubah pengeluaran'"
               :keterangan="$campaign === []
                    ? 'Maksimal '.Uang::format(Uang::keDesimal($batas)).' — kas kelas tidak boleh minus.'
                    : 'Maksimal '.Uang::format(Uang::keDesimal($batasBebas)).' dari saldo bebas. Pengeluaran yang ditandai campaign memakai sisa dana campaign itu.'">

        <form method="POST"
              action="{{ $baru ? route('pengeluaran.store') : route('pengeluaran.update', $pengeluaran) }}"
              enctype="multipart/form-data" class="space-y-5">
            @csrf
            @unless ($baru) @method('PUT') @endunless

            <x-ui.field label="Keterangan" name="keterangan" :value="$pengeluaran->keterangan" wajib autofocus
                        placeholder="mis. beli spidol dan penghapus papan" />

            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Jumlah" name="jumlah" type="number" inputmode="numeric" min="0" step="500" wajib
                            prefix="Rp"
                            :value="$pengeluaran->exists ? (int) $pengeluaran->jumlah : null"
                            :max="intdiv($batas, 100)" />

                <x-ui.field label="Tanggal" name="tanggal" type="date" wajib
                            :value="$pengeluaran->tanggal?->toDateString() ?? now()->toDateString()"
                            :max="now()->toDateString()" />
            </div>

            <x-ui.select label="Kategori" name="category_id" wajib kosong="— pilih kategori —"
                         :value="$pengeluaran->category_id" :opsi="$kategori" />

            @if ($campaign !== [])
                <x-ui.select label="Dibiayai campaign (opsional)" name="campaign_id"
                             kosong="— pakai saldo bebas kelas —"
                             :value="$pengeluaran->campaign_id" :opsi="$campaign"
                             bantuan="Pilih campaign bila uang ini berasal dari iuran insidental. Pengeluarannya lalu dibatasi sisa dana campaign tersebut, dan ikut muncul di laporan campaign." />
            @endif

            <div class="space-y-1.5">
                <label for="bukti" class="block text-sm font-semibold text-ink">Bukti / nota (opsional)</label>
                <input type="file" id="bukti" name="bukti" accept="image/jpeg,image/png,application/pdf"
                       aria-describedby="bukti-bantuan"
                       class="block w-full cursor-pointer rounded-xl border border-line-strong bg-card p-2.5 text-sm
                              file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-brand-soft
                              file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-soft-ink">
                <p id="bukti-bantuan" class="text-sm text-ink-faint">
                    JPG, PNG, atau PDF, maksimal 2 MB.
                    @unless ($baru)
                        {{ $pengeluaran->bukti_path ? 'Mengunggah berkas baru akan menggantikan bukti yang lama.' : '' }}
                    @endunless
                </p>
                @error('bukti')
                    <p role="alert" class="text-sm font-medium text-keluar">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-2 border-t border-line pt-5 sm:flex-row-reverse sm:justify-start">
                <x-ui.button type="submit" size="lg">{{ $baru ? 'Simpan pengeluaran' : 'Simpan perubahan' }}</x-ui.button>
                <x-ui.button :href="route('pengeluaran.index')" variant="secondary" size="lg">Batal</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card judul="Kategori pengeluaran"
               keterangan="Kategori bawaan tersedia untuk semua kelas. Kategori yang kamu buat hanya milik kelas ini.">
        <form method="POST" action="{{ route('kategori.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-48 flex-1">
                <x-ui.field label="Nama kategori baru" name="nama" placeholder="mis. Study Tour" />
            </div>
            <x-ui.button type="submit" variant="secondary" icon="tambah">Tambah</x-ui.button>
        </form>
    </x-ui.card>
</x-layouts.app>
