@props(['judul' => null])

<x-layouts.base :judul="$judul">
    <div class="flex min-h-dvh flex-col justify-center px-4 py-10 sm:px-6">
        <main id="konten" class="mx-auto w-full max-w-md space-y-6">
            <div class="flex flex-col items-center gap-3 text-center">
                <span class="flex size-12 items-center justify-center rounded-2xl bg-brand text-brand-ink shadow-sm">
                    <x-icon name="kelas" class="size-7" />
                </span>
                <div>
                    <p class="text-2xl font-extrabold tracking-tight">KasKelas</p>
                    <p class="mt-0.5 text-sm text-ink-faint">Pencatatan kas kelas yang bisa dilihat semua anggota</p>
                </div>
            </div>

            {{ $slot }}
        </main>
    </div>
</x-layouts.base>
