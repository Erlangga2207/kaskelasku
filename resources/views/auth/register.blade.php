<x-layouts.guest judul="Daftar">
    <x-ui.card>
        <div class="space-y-5">
            <div>
                <h1 class="text-xl font-bold">Buat akun bendahara</h1>
                <p class="mt-1 text-sm text-ink-faint">
                    Gratis, tanpa iklan. Hanya bendahara yang butuh akun — anggota kelas cukup membuka tautan kelas.
                </p>
            </div>

            <x-ui.flash />

            @if ($errors->any() && ! $errors->hasAny(['nama', 'email', 'password', 'setuju_syarat']))
                <x-ui.alert tipe="galat">{{ $errors->first() }}</x-ui.alert>
            @endif

            @if ($sisaKuota <= 10)
                <x-ui.alert tipe="peringatan">
                    Tinggal {{ $sisaKuota }} tempat tersisa. Setelah penuh, pendaftaran ditutup sementara
                    sampai kapasitas servernya dinaikkan.
                </x-ui.alert>
            @endif

            <form method="POST" action="{{ route('daftar.store') }}" class="space-y-4" novalidate>
                @csrf

                <x-ui.field label="Nama kamu" name="nama" wajib autocomplete="name" autofocus
                            placeholder="Nama lengkap" />

                <x-ui.field label="Email" name="email" type="email" wajib autocomplete="username"
                            inputmode="email" placeholder="kamu@email.com"
                            bantuan="Alamat ini dipakai untuk verifikasi dan untuk memulihkan akses kalau kata sandi lupa. Pastikan benar." />

                <div x-data="{ lihat: false }">
                    <div class="relative">
                        <x-ui.field label="Kata sandi" name="password" type="password" wajib
                                    autocomplete="new-password"
                                    x-bind:type="lihat ? 'text' : 'password'"
                                    class="pr-12"
                                    bantuan="Minimal 8 karakter." />

                        <button type="button" @click="lihat = !lihat"
                                class="absolute right-2 top-8 inline-flex size-9 items-center justify-center rounded-lg text-ink-faint hover:bg-surface hover:text-ink"
                                x-bind:aria-label="lihat ? 'Sembunyikan kata sandi' : 'Lihat kata sandi'">
                            <x-icon name="mata" class="size-5" x-show="! lihat" />
                            <x-icon name="mata-tutup" class="size-5" x-cloak x-show="lihat" />
                        </button>
                    </div>
                </div>

                <x-ui.field label="Ulangi kata sandi" name="password_confirmation" type="password" wajib
                            autocomplete="new-password" />

                <label class="flex items-start gap-3 rounded-xl border border-line-strong bg-surface px-4 py-3">
                    <input type="checkbox" name="setuju_syarat" value="1" @checked(old('setuju_syarat'))
                           class="mt-0.5 size-5 shrink-0 rounded border-line-strong text-brand">
                    <span class="text-sm text-ink-soft">
                        Saya sudah membaca dan setuju dengan
                        <a href="{{ route('syarat') }}" target="_blank" rel="noopener"
                           class="font-semibold text-brand underline-offset-2 hover:underline">Syarat Layanan</a>
                        dan
                        <a href="{{ route('privasi') }}" target="_blank" rel="noopener"
                           class="font-semibold text-brand underline-offset-2 hover:underline">Kebijakan Privasi</a>.
                    </span>
                </label>
                @error('setuju_syarat')
                    <p role="alert" class="text-sm font-medium text-keluar">{{ $message }}</p>
                @enderror

                <x-ui.button type="submit" size="lg">Daftar</x-ui.button>
            </form>

            <p class="text-center text-sm text-ink-faint">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="font-semibold text-brand underline-offset-2 hover:underline">Masuk di sini</a>
            </p>
        </div>
    </x-ui.card>
</x-layouts.guest>
