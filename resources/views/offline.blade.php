<x-layouts.base judul="Tidak ada koneksi">
    <div class="flex min-h-dvh flex-col justify-center px-4 py-10">
        <main id="konten" class="mx-auto w-full max-w-md space-y-5 text-center">
            <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-tunggak-soft text-tunggak-soft-ink">
                <x-icon name="peringatan" class="size-7" />
            </span>

            <div class="space-y-1.5">
                <h1 class="text-xl font-bold">Perangkat sedang tanpa koneksi</h1>
                <p class="text-ink-soft">
                    Angka saldo sengaja tidak ditampilkan dari simpanan lama. Saldo yang basi lebih
                    berbahaya daripada halaman yang gagal dimuat — jadi halaman ini menunggu sampai
                    jaringan tersambung kembali.
                </p>
            </div>

            <x-ui.button href="/" size="lg" icon="putar">Coba muat ulang</x-ui.button>
        </main>
    </div>
</x-layouts.base>
