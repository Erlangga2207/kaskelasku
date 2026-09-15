@props(['judul' => null, 'kelas' => null, 'token' => null])

<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    {{-- Halaman kelas tidak boleh masuk hasil pencarian, apa pun keadaannya. --}}
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#1d4ed8" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0b1220" media="(prefers-color-scheme: dark)">
    <title>{{ $judul ?? 'Kas Kelas' }}</title>

    @if ($token)
        <link rel="manifest" href="{{ route('publik.manifest', $token) }}">
    @endif
    <link rel="apple-touch-icon" href="/ikon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/ikon/favicon-32.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none !important}</style>
</head>
<body class="min-h-full antialiased">
    <a href="#konten"
       class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-xl
              focus:bg-brand focus:px-4 focus:py-2.5 focus:font-semibold focus:text-brand-ink">
        Lompat ke konten
    </a>

    <header class="border-b border-line bg-elevated">
        <div class="mx-auto flex max-w-3xl items-center gap-3 px-4 py-4 sm:px-6">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand text-brand-ink">
                <x-icon name="kelas" class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="truncate text-lg font-bold leading-tight">{{ $kelas?->nama_kelas }}</p>
                <p class="truncate text-sm text-ink-faint">{{ $kelas?->sekolah }}</p>
            </div>
        </div>
    </header>

    <main id="konten" class="mx-auto w-full max-w-3xl space-y-4 px-4 py-5 sm:px-6">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-3xl px-4 pb-10 pt-2 text-center text-xs text-ink-faint sm:px-6">
        <p>Halaman ini hanya menampilkan data — tidak ada tombol yang bisa mengubah catatan kas.</p>
        <p class="mt-1">Dibuat dengan KasKelas.</p>
    </footer>

    {{-- Service worker hanya untuk aset statis; angka saldo selalu diambil dari jaringan. --}}
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
        }
    </script>
</body>
</html>
