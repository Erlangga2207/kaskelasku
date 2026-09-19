@props([
    'action',
    'method' => 'DELETE',
    'judul' => 'Yakin?',
    'pesan' => '',
    'tombol' => 'Hapus',
    'variant' => 'danger',
    // Nilai tambahan yang ikut terkirim, mis. ['status' => 'dibatalkan'].
    'input' => [],
])

{{--
    Konfirmasi memakai dialog di dalam halaman, bukan confirm() bawaan browser:
    dialog bawaan tidak bisa diberi konteks, dan tampilannya berbeda-beda di tiap ponsel.
--}}
<div x-data="{ buka: false }" class="inline-flex">
    <button type="button" @click="buka = true"
            {{ $attributes->merge(['class' => 'inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-keluar transition-colors hover:bg-keluar-soft']) }}>
        {{ $slot }}
    </button>

    <template x-teleport="body">
        <div x-cloak x-show="buka" @keydown.escape.window="buka = false"
             class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            {{-- Scrim cukup pekat supaya isi latar tidak bersaing dengan dialog --}}
            <div x-show="buka" x-transition.opacity.duration.150ms @click="buka = false"
                 class="absolute inset-0 bg-black/55"></div>

            <div x-show="buka"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                 role="alertdialog" aria-modal="true" aria-labelledby="judul-konfirmasi"
                 class="relative w-full max-w-sm rounded-2xl border border-line bg-elevated p-5 shadow-xl">
                <h2 id="judul-konfirmasi" class="text-base font-bold">{{ $judul }}</h2>
                <p class="mt-1.5 text-sm text-ink-soft">{{ $pesan }}</p>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-ui.button type="button" variant="secondary" @click="buka = false">Batal</x-ui.button>

                    <form method="POST" action="{{ $action }}">
                        @csrf
                        @method($method)
                        @foreach ($input as $nama => $nilai)
                            <input type="hidden" name="{{ $nama }}" value="{{ $nilai }}">
                        @endforeach
                        <x-ui.button type="submit" :variant="$variant" class="w-full sm:w-auto">
                            {{ $tombol }}
                        </x-ui.button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
