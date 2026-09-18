<x-layouts.guest judul="Verifikasi email">
    <x-ui.card>
        <div class="space-y-5">
            <div>
                <h1 class="text-xl font-bold">Cek emailmu dulu</h1>
                <p class="mt-1 text-sm text-ink-faint">
                    Kami mengirim tautan verifikasi ke
                    <strong class="text-ink">{{ auth()->user()->email }}</strong>.
                    Buka tautan itu, lalu kelas pertamamu bisa dibuat.
                </p>
            </div>

            <x-ui.flash />

            <x-ui.alert tipe="info" judul="Tidak ketemu emailnya?">
                Cek folder <strong>Spam</strong> atau <strong>Promosi</strong>. Email dari alamat baru
                sering mendarat di sana pada kiriman pertama.
            </x-ui.alert>

            <form method="POST" action="{{ route('verifikasi.kirim-ulang') }}">
                @csrf
                <x-ui.button type="submit" variant="secondary" size="lg">Kirim ulang email verifikasi</x-ui.button>
            </form>

            <p class="text-sm text-ink-faint">
                Salah menulis alamat email? Keluar dulu, lalu daftar ulang dengan alamat yang benar —
                akun yang belum diverifikasi tidak menyimpan data apa pun.
            </p>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="text-sm font-semibold text-ink-soft underline-offset-2 hover:text-ink hover:underline">
                    Keluar
                </button>
            </form>
        </div>
    </x-ui.card>
</x-layouts.guest>
