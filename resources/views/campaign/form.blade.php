@php
    use App\Support\Uang;

    $baru = ! $campaign->exists;
    $sudahBayar = $sudahBayar ?? [];
    $terpilih = collect(old('peserta', $pesertaTerpilih))->map(fn ($id) => (int) $id)->all();
@endphp

<x-layouts.app :judul="$baru ? 'Campaign baru' : 'Ubah campaign'">
    <x-ui.button :href="$baru ? route('campaign.index') : route('campaign.show', $campaign)"
                 variant="ghost" icon="kiri" size="sm">Kembali</x-ui.button>

    <form method="POST"
          action="{{ $baru ? route('campaign.store') : route('campaign.update', $campaign) }}"
          class="space-y-4">
        @csrf
        @unless ($baru) @method('PATCH') @endunless

        <x-ui.card :judul="$baru ? 'Iuran insidental baru' : 'Ubah iuran insidental'"
                   keterangan="Tiap peserta mendapat satu tagihan dengan nominal yang sama. Pembayarannya dicatat lewat menu Bayar, sama seperti iuran rutin.">
            <div class="space-y-5">
                <x-ui.field label="Nama campaign" name="nama" :value="$campaign->nama" wajib autofocus
                            placeholder="mis. Studi Tour Bandung" />

                <x-ui.field label="Deskripsi (opsional)" name="deskripsi" :value="$campaign->deskripsi"
                            placeholder="mis. transport + tiket masuk, dibayar paling lambat sebelum keberangkatan" />

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Nominal per siswa" name="nominal_per_siswa" type="number"
                                inputmode="numeric" min="0" step="500" wajib prefix="Rp"
                                :value="$campaign->exists ? (int) $campaign->nominal_per_siswa : null"
                                :bantuan="$baru
                                    ? 'Melekat pada tagihan yang terbentuk.'
                                    : 'Tidak bisa diubah lagi setelah ada pembayaran yang masuk.'" />

                    <x-ui.field label="Batas waktu (opsional)" name="deadline" type="date"
                                :value="$campaign->deadline?->toDateString()"
                                bantuan="Dipakai sebagai jatuh tempo. Tanpa batas waktu, tagihannya tidak pernah dihitung sebagai tunggakan." />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card judul="Peserta"
                   keterangan="Bawaannya seluruh siswa aktif ikut. Hilangkan centang untuk siswa yang tidak ikut."
                   padat>
            <div class="px-4 pt-3 sm:px-5" x-data="{
                pilih(nilai) {
                    this.$root.querySelectorAll('input[type=checkbox]:not(:disabled)')
                        .forEach(c => c.checked = nilai);
                },
            }">
                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="button" variant="secondary" size="sm" @click="pilih(true)">Pilih semua</x-ui.button>
                    <x-ui.button type="button" variant="secondary" size="sm" @click="pilih(false)">Kosongkan</x-ui.button>
                </div>

                @error('peserta')
                    <p role="alert" class="mt-3 text-sm font-medium text-keluar">{{ $message }}</p>
                @enderror

                <ul class="mt-3 grid gap-1 sm:grid-cols-2">
                    @foreach ($daftarSiswa as $siswa)
                        @php $terkunci = in_array($siswa->id, $sudahBayar, true); @endphp
                        <li>
                            <label class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2 hover:bg-surface
                                          {{ $terkunci ? 'opacity-70' : '' }}">
                                <input type="checkbox" name="peserta[]" value="{{ $siswa->id }}"
                                       @checked(in_array($siswa->id, $terpilih, true))
                                       @disabled($terkunci)
                                       class="size-5 rounded border-line-strong text-brand focus:outline-2 focus:outline-offset-2 focus:outline-ring-brand">

                                {{-- Peserta yang sudah membayar tetap terkirim lewat hidden input:
                                     checkbox yang disabled tidak ikut dikirim browser. --}}
                                @if ($terkunci)
                                    <input type="hidden" name="peserta[]" value="{{ $siswa->id }}">
                                @endif

                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">
                                        @if ($siswa->no_absen){{ $siswa->no_absen }}.@endif {{ $siswa->nama }}
                                    </span>
                                    @if ($terkunci)
                                        <span class="block text-xs text-ink-faint">Sudah membayar — tidak bisa dikeluarkan</span>
                                    @elseif (! $siswa->is_active)
                                        <span class="block text-xs text-ink-faint">Sudah tidak aktif</span>
                                    @endif
                                </span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="flex flex-col gap-2 border-t border-line px-4 py-4 sm:flex-row-reverse sm:justify-start sm:px-5">
                <x-ui.button type="submit" size="lg">
                    {{ $baru ? 'Buat campaign & tagihannya' : 'Simpan perubahan' }}
                </x-ui.button>
                <x-ui.button :href="$baru ? route('campaign.index') : route('campaign.show', $campaign)"
                             variant="secondary" size="lg">Batal</x-ui.button>
            </div>
        </x-ui.card>
    </form>
</x-layouts.app>
