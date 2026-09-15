<x-layouts.guest judul="Masuk">
    <x-ui.card>
        <div class="space-y-5">
            <div>
                <h1 class="text-xl font-bold">Masuk sebagai bendahara</h1>
                <p class="mt-1 text-sm text-ink-faint">
                    Halaman untuk anggota kelas tidak butuh login — cukup buka tautan kelas.
                </p>
            </div>

            @if ($errors->any() && ! $errors->has('email') && ! $errors->has('password'))
                <x-ui.alert tipe="galat">{{ $errors->first() }}</x-ui.alert>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4" novalidate>
                @csrf

                <x-ui.field
                    label="Email"
                    name="email"
                    type="email"
                    wajib
                    autocomplete="username"
                    inputmode="email"
                    autofocus
                    placeholder="bendahara@sekolah.sch.id" />

                {{-- Tombol lihat/sembunyikan: mengetik sandi di ponsel mudah salah. --}}
                <div x-data="{ lihat: false }">
                    <div class="relative">
                        <x-ui.field
                            label="Kata sandi"
                            name="password"
                            type="password"
                            wajib
                            autocomplete="current-password"
                            x-bind:type="lihat ? 'text' : 'password'"
                            class="pr-12" />

                        <button type="button" @click="lihat = !lihat"
                                {{-- 1.625rem = tinggi label + jarak, supaya tombol pas di dalam kotak input --}}
                                class="absolute right-0 top-[1.625rem] flex size-11 items-center justify-center rounded-xl text-ink-faint transition-colors hover:text-ink cursor-pointer"
                                :aria-label="lihat ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                            <x-icon name="mata" class="size-5" x-show="!lihat" />
                            <x-icon name="mata-tutup" class="size-5" x-cloak x-show="lihat" />
                        </button>
                    </div>
                </div>

                <label class="flex min-h-11 w-fit cursor-pointer items-center gap-2.5 text-sm text-ink-soft">
                    <input type="checkbox" name="remember" value="1"
                           class="size-5 rounded border-line-strong text-brand focus:outline-2 focus:outline-offset-2 focus:outline-ring-brand">
                    Ingat saya di perangkat ini
                </label>

                <x-ui.button type="submit" size="lg" class="w-full">Masuk</x-ui.button>
            </form>
        </div>
    </x-ui.card>

    <p class="text-center text-xs text-ink-faint">
        Demi keamanan, percobaan masuk dibatasi 5 kali per menit.
    </p>
</x-layouts.guest>
