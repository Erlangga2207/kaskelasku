<x-layouts.pemasaran
    judul="Daftar Tunggu — KasKelas"
    deskripsi="Pendaftaran KasKelas sedang ditutup sementara karena kapasitas server penuh. Tinggalkan email untuk dikabari begitu ada tempat kosong.">

    <section class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight">Pendaftaran sedang penuh</h1>

        <p class="mt-4 text-ink-soft">
            Saat ini ada <strong class="text-ink">{{ $jumlahKelas }}</strong> kelas terdaftar, dan
            kapasitas servernya {{ $batas }} kelas. Pendaftaran baru ditutup sementara sampai
            kapasitasnya dinaikkan.
        </p>

        {{--
            Alasannya dijelaskan apa adanya. "Penuh" tanpa penjelasan terdengar
            seperti taktik supaya orang buru-buru; yang sebenarnya terjadi jauh
            lebih membosankan dari itu.
        --}}
        <x-ui.alert tipe="info" class="mt-6" judul="Kenapa dibatasi?">
            Aplikasi ini berjalan di satu paket hosting sederhana dan tidak dipungut biaya.
            Kalau jumlah kelas melewati kemampuan servernya, yang rusak bukan cuma kelas baru —
            kelas yang sudah memakainya untuk uang sungguhan ikut melambat. Membatasi jumlahnya
            lebih jujur daripada menerima semua orang lalu membuat semuanya lambat.
        </x-ui.alert>

        <x-ui.card class="mt-8">
            <div class="space-y-4">
                <div>
                    <h2 class="text-lg font-bold">Tinggalkan emailmu</h2>
                    <p class="mt-1 text-sm text-ink-faint">
                        Kami kabari begitu ada tempat kosong. Hanya untuk itu — tanpa email lain, tanpa iklan.
                    </p>
                </div>

                <x-ui.flash />

                <form method="POST" action="{{ route('daftar-tunggu.store') }}" class="space-y-4" novalidate>
                    @csrf
                    <x-ui.field label="Email" name="email" type="email" wajib inputmode="email"
                                placeholder="kamu@email.com" />
                    <x-ui.button type="submit" size="lg">Kabari saya</x-ui.button>
                </form>
            </div>
        </x-ui.card>

        <p class="mt-8 text-sm text-ink-faint">
            Sementara menunggu, kamu bisa
            <a class="font-semibold text-brand underline-offset-2 hover:underline" href="{{ route('demo') }}">melihat kelas demo</a>
            atau
            <a class="font-semibold text-brand underline-offset-2 hover:underline" href="{{ route('panduan') }}">membaca panduannya</a>.
        </p>
    </section>
</x-layouts.pemasaran>
