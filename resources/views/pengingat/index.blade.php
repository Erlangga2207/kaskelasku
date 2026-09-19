@php use App\Support\Uang; @endphp

<x-layouts.app judul="Pengingat tunggakan">
    @php $idSiswa = $daftar->pluck('siswa.id')->map(fn ($id) => (int) $id)->all(); @endphp

    <x-ui.card judul="Batas pembayaran"
               keterangan="Tanggal ini yang muncul sebagai {batas} di dalam teks pengingat.">
        <form method="GET" action="{{ route('pengingat.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-44 flex-1">
                <x-ui.field label="Mohon dilunasi sebelum" name="batas" type="date" :value="$batas" />
            </div>
            <x-ui.button type="submit" variant="secondary">Terapkan</x-ui.button>
        </form>

        <p class="mt-3 text-sm text-ink-faint">
            Kata-katanya bisa diubah di
            <a href="{{ route('pengaturan.edit') }}#template-pengingat"
               class="font-semibold text-brand underline-offset-2 hover:underline">Pengaturan → Template pengingat</a>.
        </p>
    </x-ui.card>

    @if ($daftar->isEmpty())
        <x-ui.card>
            <x-ui.empty ikon="cek" judul="Tidak ada yang perlu ditagih">
                Seluruh tagihan yang sudah jatuh tempo sudah lunas. Pengingat hanya dibuat untuk
                tagihan yang benar-benar sudah lewat jatuh tempo — bukan untuk periode yang masih berjalan.
            </x-ui.empty>
        </x-ui.card>
    @else
        <div x-data="{
                 pilih: {},
                 kabar: '',
                 idSiswa: @js($idSiswa),

                 get terpilih() { return this.idSiswa.filter((id) => this.pilih[id]); },
                 get semuaTerpilih() { return this.terpilih.length === this.idSiswa.length; },

                 pilihSemua(nyala) { for (const id of this.idSiswa) this.pilih[id] = nyala; },

                 teksSiswa(id) { return document.getElementById('teks-' + id).value; },

                 salinSatu(id, nama) { this.salin(this.teksSiswa(id), 'Pengingat untuk ' + nama + ' tersalin.'); },

                 salinTerpilih() {
                     const bagian = this.terpilih.map((id) => this.teksSiswa(id));
                     if (bagian.length === 0) return;

                     this.salin(
                         bagian.join('\n\n———\n\n'),
                         bagian.length + ' pengingat tersalin, dipisah garis.',
                     );
                 },

                 salin(teks, kabar) {
                     const sukses = () => {
                         this.kabar = kabar;
                         setTimeout(() => { if (this.kabar === kabar) this.kabar = ''; }, 3500);
                     };

                     /* clipboard API hanya tersedia di HTTPS/localhost, sedangkan
                        server lokal bendahara sering http:// biasa — jadi selalu
                        ada jalur cadangan, bukan tombol yang diam-diam tidak jalan. */
                     if (navigator.clipboard && window.isSecureContext) {
                         navigator.clipboard.writeText(teks).then(sukses, () => this.salinCadangan(teks, sukses));
                     } else {
                         this.salinCadangan(teks, sukses);
                     }
                 },

                 salinCadangan(teks, sukses) {
                     const kotak = document.createElement('textarea');
                     kotak.value = teks;
                     kotak.setAttribute('readonly', '');
                     kotak.style.position = 'fixed';
                     kotak.style.top = '-2000px';
                     document.body.appendChild(kotak);
                     kotak.select();

                     try {
                         document.execCommand('copy');
                         sukses();
                     } catch (e) {
                         this.kabar = 'Gagal menyalin otomatis. Buka teksnya, pilih semua, lalu salin manual.';
                     } finally {
                         kotak.remove();
                     }
                 },
             }"
             class="space-y-4">

            <x-ui.card :judul="$daftar->count().' siswa menunggak'"
                       keterangan="Diurutkan dari tunggakan terbesar. Ketuk teksnya kalau mau memeriksa dulu sebelum menyalin."
                       padat>
                <x-slot:aksi>
                    <label class="flex min-h-11 cursor-pointer items-center gap-2 text-sm font-semibold">
                        <input type="checkbox" class="size-4"
                               :checked="semuaTerpilih" @change="pilihSemua($event.target.checked)">
                        Pilih semua
                    </label>
                </x-slot:aksi>

                <ul class="divide-y divide-line">
                    @foreach ($daftar as $baris)
                        @php $siswa = $baris['siswa']; @endphp
                        <li class="px-4 py-3 sm:px-5">
                            <div class="flex items-start gap-3">
                                <label class="flex min-h-11 flex-1 cursor-pointer items-start gap-3">
                                    <input type="checkbox" class="mt-1 size-4 shrink-0"
                                           :checked="pilih[{{ $siswa->id }}]"
                                           @change="pilih[{{ $siswa->id }}] = $event.target.checked"
                                           aria-label="Pilih pengingat untuk {{ $siswa->nama }}">
                                    <span class="min-w-0">
                                        <span class="block font-semibold">
                                            {{ $siswa->no_absen ? $siswa->no_absen.'. ' : '' }}{{ $siswa->nama }}
                                            @unless ($siswa->is_active)
                                                <x-ui.badge tipe="netral">Nonaktif</x-ui.badge>
                                            @endunless
                                        </span>
                                        <span class="block text-sm text-keluar tabular">
                                            {{ Uang::format(Uang::keDesimal($baris['total'])) }}
                                            <span class="text-ink-faint">
                                                · {{ $baris['rincian']->count() }} tagihan lewat jatuh tempo
                                            </span>
                                        </span>
                                    </span>
                                </label>

                                <x-ui.button type="button" variant="secondary" size="sm" icon="salin"
                                             class="shrink-0"
                                             @click="salinSatu({{ $siswa->id }}, @js($siswa->nama))">
                                    Salin
                                </x-ui.button>
                            </div>

                            {{-- Teksnya tetap ada di DOM dalam textarea sungguhan: itu yang
                                 dibaca tombol salin, dan juga jalan keluar kalau clipboard
                                 diblokir browser — bisa dipilih dan disalin manual. --}}
                            <details class="mt-2">
                                <summary class="inline-flex min-h-11 cursor-pointer items-center gap-1.5 text-sm font-semibold text-ink-soft">
                                    <x-icon name="mata" class="size-4" />
                                    Lihat teksnya
                                </summary>

                                <label for="teks-{{ $siswa->id }}" class="sr-only">
                                    Teks pengingat untuk {{ $siswa->nama }}
                                </label>
                                <textarea id="teks-{{ $siswa->id }}" readonly rows="10"
                                          onfocus="this.select()"
                                          class="mt-1.5 block w-full rounded-xl border border-line-strong bg-surface p-3
                                                 font-mono text-sm leading-relaxed text-ink-soft">{{ $baris['teks'] }}</textarea>
                            </details>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            {{-- Bar menempel di bawah: menagih dilakukan sekali untuk banyak orang,
                 jadi tombol salin-banyak harus terjangkau tanpa menggulir balik ke atas.
                 bottom-20 memberi ruang untuk bottom nav ponsel. --}}
            <div class="sticky bottom-20 z-10 rounded-card border border-line bg-elevated/95 px-4 py-3 shadow-lg backdrop-blur lg:bottom-4 sm:px-5">
                <div aria-live="polite" class="min-h-5 text-sm font-medium text-masuk" x-text="kabar"></div>

                <div class="mt-1 flex flex-col gap-2 sm:flex-row-reverse sm:items-center sm:justify-start">
                    <x-ui.button type="button" size="lg" icon="salin"
                                 ::disabled="terpilih.length === 0"
                                 @click="salinTerpilih()">
                        <span x-text="terpilih.length > 1
                            ? 'Salin ' + terpilih.length + ' pengingat sekaligus'
                            : 'Salin pengingat terpilih'">Salin pengingat terpilih</span>
                    </x-ui.button>

                    <p class="text-sm text-ink-faint sm:mr-auto">
                        <span x-text="terpilih.length"></span> dari {{ $daftar->count() }} dipilih.
                        Beberapa pengingat disalin sekaligus, dipisah garis.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <x-ui.alert tipe="info" judul="Aplikasi ini tidak mengirim apa pun sendiri">
        Tidak ada pesan otomatis ke siapa pun. Teks di atas hanya disiapkan; kamu sendiri yang
        memilih mau mengirimkannya ke siapa dan lewat mana. Aplikasi juga tidak menyimpan nomor HP —
        dan tidak akan pernah.
    </x-ui.alert>
</x-layouts.app>
