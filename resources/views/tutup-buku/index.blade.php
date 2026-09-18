@php use App\Support\Uang; @endphp

<x-layouts.app judul="Tutup buku & serah terima">

    {{--
        Penjelasan ditaruh paling atas, bukan di bawah form. Tutup buku adalah
        satu-satunya aksi di aplikasi ini yang membuat data lama tidak bisa
        diperbaiki lagi — bendahara harus tahu itu SEBELUM mengisi tanggalnya,
        bukan setelah menekan tombol.
    --}}
    <x-ui.alert tipe="peringatan" judul="Buku yang sudah ditutup tidak bisa diubah lagi">
        Setelah sebuah rentang ditutup, pembayaran dan pengeluaran bertanggal di dalamnya
        tidak bisa ditambah, diubah, atau dihapus — termasuk lewat halaman lain.
        Kalau nanti ada yang keliru, <strong>catat transaksi penyesuaian bertanggal hari ini</strong>,
        jangan mengutak-atik data lama. Begitulah cara pembukuan menjaga laporan yang
        sudah ditandatangani tetap cocok dengan bukunya.
    </x-ui.alert>

    {{-- ================= Tutup buku baru ================= --}}
    <x-ui.card judul="Tutup buku periode baru" class="mt-4"
               keterangan="Pilih rentangnya dulu, lihat angkanya, baru kunci.">

        {{-- Pratinjau memakai GET supaya bendahara bisa bolak-balik mencoba
             rentang tanpa sekali pun menyentuh tombol yang mengunci. --}}
        <form method="GET" action="{{ route('tutup-buku.index') }}"
              class="flex flex-wrap items-end gap-3">
            <div class="min-w-40 flex-1">
                <x-ui.field label="Dari tanggal" name="dari" type="date" :value="$dari" />
            </div>
            <div class="min-w-40 flex-1">
                <x-ui.field label="Sampai tanggal" name="sampai" type="date" :value="$sampai" />
            </div>
            <x-ui.button type="submit" variant="secondary" icon="cari">Hitung</x-ui.button>
        </form>

        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-ui.stat label="Saldo awal" :nilai="Uang::format(Uang::keDesimal($pratinjau['saldo_awal']))"
                       keterangan="Sebelum rentang ini" />
            <x-ui.stat label="Total masuk" nada="masuk" ikon="masuk-arah"
                       :nilai="Uang::format(Uang::keDesimal($pratinjau['total_masuk']))" />
            <x-ui.stat label="Total keluar" nada="keluar" ikon="keluar-arah"
                       :nilai="Uang::format(Uang::keDesimal($pratinjau['total_keluar']))" />
            <x-ui.stat label="Saldo akhir" nada="brand" ikon="dompet"
                       :nilai="Uang::format(Uang::keDesimal($pratinjau['saldo_akhir']))"
                       keterangan="Yang diserahterimakan" />
        </div>

        {{--
            Dialog konfirmasinya ditulis di sini, tidak memakai <x-ui.confirm>:
            komponen itu membawa form-nya sendiri, sedangkan di sini nilai yang
            dikirim berasal dari isian di layar. Tombol "Ya" menunjuk balik ke
            form ini lewat atribut form=, karena x-teleport memindahkan dialognya
            keluar dari form.
        --}}
        <form method="POST" action="{{ route('tutup-buku.store') }}" id="form-tutup-buku"
              x-data="{ konfirmasi: false }" class="mt-5 space-y-4">
            @csrf
            <input type="hidden" name="tgl_mulai" value="{{ $dari }}">
            <input type="hidden" name="tgl_selesai" value="{{ $sampai }}">

            <x-ui.field label="Nama periode" name="label" wajib :value="old('label')"
                        bantuan="Misalnya: Semester Ganjil 2025/2026. Nama ini yang tercetak di laporan serah terima." />

            <div class="space-y-1.5">
                <label for="catatan" class="block text-sm font-semibold text-ink">Catatan (opsional)</label>
                <textarea id="catatan" name="catatan" rows="3"
                          aria-describedby="catatan-bantuan"
                          class="block w-full rounded-xl border bg-card px-3.5 py-2.5 text-base text-ink transition-colors focus:outline-2 focus:outline-offset-2 focus:outline-ring-brand {{ $errors->has('catatan') ? 'border-keluar' : 'border-line-strong' }}">{{ old('catatan') }}</textarea>
                <p id="catatan-bantuan" class="text-sm text-ink-faint">
                    Hal yang perlu diketahui bendahara berikutnya — misalnya sisa uang tunai yang dipegang.
                </p>
                @error('catatan')
                    <p role="alert" class="text-sm font-medium text-keluar">{{ $message }}</p>
                @enderror
            </div>

            <x-ui.button type="button" variant="primary" size="lg" icon="kunci"
                         x-on:click="konfirmasi = true">
                Tutup buku rentang ini
            </x-ui.button>

            <template x-teleport="body">
                <div x-cloak x-show="konfirmasi" x-on:keydown.escape.window="konfirmasi = false"
                     class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
                    <div x-show="konfirmasi" x-transition.opacity.duration.150ms
                         x-on:click="konfirmasi = false" class="absolute inset-0 bg-black/55"></div>

                    <div x-show="konfirmasi"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                         role="alertdialog" aria-modal="true" aria-labelledby="judul-tutup-buku"
                         class="relative w-full max-w-sm rounded-2xl border border-line bg-elevated p-5 shadow-xl">
                        <h2 id="judul-tutup-buku" class="text-base font-bold">Tutup buku rentang ini?</h2>
                        <p class="mt-1.5 text-sm text-ink-soft">
                            Transaksi bertanggal {{ $dari }} sampai {{ $sampai }} tidak bisa diubah
                            atau dihapus lagi setelah ini. Koreksi hanya lewat transaksi penyesuaian
                            bertanggal baru.
                        </p>

                        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-ui.button type="button" variant="secondary"
                                         x-on:click="konfirmasi = false">Batal</x-ui.button>
                            <x-ui.button type="submit" form="form-tutup-buku" variant="primary"
                                         class="w-full sm:w-auto">Ya, tutup buku</x-ui.button>
                        </div>
                    </div>
                </div>
            </template>
        </form>
    </x-ui.card>

    {{-- ================= Riwayat tutup buku ================= --}}
    <x-ui.card judul="Riwayat tutup buku" class="mt-4" padat
               keterangan="Hanya yang paling akhir yang bisa dibuka kembali.">
        @if ($riwayat->isEmpty())
            <x-ui.empty ikon="kunci" judul="Belum ada periode yang ditutup">
                Tutup buku biasanya dilakukan di akhir semester, atau saat bendahara berganti.
                Sebelum itu, semua transaksi masih bebas diperbaiki.
            </x-ui.empty>
        @else
            <ul class="divide-y divide-line">
                @foreach ($riwayat as $closing)
                    <li class="px-4 py-4 sm:px-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 font-semibold text-ink">
                                    {{ $closing->label }}
                                    @if ($terakhir && $closing->id === $terakhir->id)
                                        <x-ui.badge tipe="info">Terakhir</x-ui.badge>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-sm text-ink-faint">{{ $closing->rentangTeks() }}</p>
                                <p class="mt-1 text-sm text-ink-soft">
                                    Saldo akhir
                                    <strong class="text-ink">{{ Uang::format($closing->saldo_akhir) }}</strong>
                                    &middot; masuk {{ Uang::format($closing->total_masuk) }}
                                    &middot; keluar {{ Uang::format($closing->total_keluar) }}
                                </p>
                                <p class="mt-1 text-xs text-ink-faint">
                                    Ditutup {{ $closing->closed_at?->translatedFormat('j M Y H:i') }}
                                    oleh {{ $closing->closedBy?->nama ?? 'akun yang sudah dihapus' }}
                                </p>
                                @if ($closing->catatan)
                                    <p class="mt-2 whitespace-pre-line rounded-lg bg-surface px-3 py-2 text-sm text-ink-soft">{{ $closing->catatan }}</p>
                                @endif
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <x-ui.button :href="route('tutup-buku.serah-terima', $closing->id)"
                                             variant="secondary" size="sm" icon="unduh">
                                    Laporan serah terima
                                </x-ui.button>

                                {{-- Tombolnya hanya muncul untuk closing terakhir, TAPI yang
                                     benar-benar menolak adalah TutupBukuService — kalau tombol
                                     ini disembunyikan saja, request DELETE langsung tetap lolos. --}}
                                @if ($terakhir && $closing->id === $terakhir->id)
                                    <x-ui.confirm :action="route('tutup-buku.destroy', $closing->id)"
                                                  judul="Buka kembali tutup buku ini?"
                                                  tombol="Ya, buka kembali"
                                                  pesan="Transaksi di rentang itu bisa diubah lagi. Pembukaan ini tercatat di audit log beserta namamu. Kalau laporan serah terimanya sudah ditandatangani, angkanya bisa berubah dari yang ditandatangani.">
                                        Buka kembali
                                    </x-ui.confirm>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    {{-- ================= Alih kepemilikan ================= --}}
    <x-ui.card judul="Alih kepemilikan kelas" class="mt-4" id="alih-kepemilikan"
               keterangan="Serahkan kelas ini ke bendahara berikutnya.">

        <x-ui.alert tipe="peringatan" judul="Jangan berbagi akun">
            Bendahara baru harus masuk dengan akunnya sendiri. Kalau satu akun dipakai berdua,
            audit log berhenti bisa menjawab siapa yang mencatat apa — dan itu satu-satunya
            gunanya audit log saat uang kelas dipersoalkan.
        </x-ui.alert>

        <form method="POST" action="{{ route('tutup-buku.transfer') }}" class="mt-4 space-y-4">
            @csrf

            <x-ui.field label="Email bendahara baru" name="email" type="email" wajib
                        :value="old('email')"
                        bantuan="Kalau emailnya sudah terdaftar, kelas langsung dialihkan ke akun itu. Kalau belum, akunnya dibuat sekarang — isi juga nama dan kata sandi awal di bawah." />

            <x-ui.field label="Nama bendahara baru" name="nama" :value="old('nama')"
                        bantuan="Hanya perlu diisi kalau emailnya belum terdaftar." />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Kata sandi awal" name="password" type="password"
                            bantuan="Minimal 8 karakter. Minta bendahara baru menggantinya setelah masuk." />
                <x-ui.field label="Ulangi kata sandi" name="password_confirmation" type="password" />
            </div>

            <label class="flex items-start gap-3 rounded-xl border border-line-strong bg-surface px-4 py-3">
                <input type="checkbox" name="konfirmasi" value="1"
                       class="mt-0.5 size-5 shrink-0 rounded border-line-strong text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring-brand">
                <span class="text-sm text-ink-soft">
                    Saya paham bahwa setelah dialihkan, <strong class="text-ink">saya kehilangan akses ke kelas ini</strong>
                    dan tidak bisa membatalkannya sendiri.
                </span>
            </label>
            @error('konfirmasi')
                <p role="alert" class="text-sm font-medium text-keluar">{{ $message }}</p>
            @enderror

            <x-ui.button type="submit" variant="danger" size="lg">
                Alihkan kelas ke bendahara baru
            </x-ui.button>
        </form>
    </x-ui.card>
</x-layouts.app>
