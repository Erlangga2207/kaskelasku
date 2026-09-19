@props([
    'judul',
    'deskripsi',
    'kanonik' => null,
    'jsonLd' => null,
    'ogGambar' => null,
])

@php
    // Canonical selalu absolut dan tanpa query string: dua URL yang menampilkan
    // halaman sama harus menunjuk ke satu alamat, kalau tidak nilai halamannya
    // terbelah di mata mesin pencari.
    $kanonik = $kanonik ?? url()->current();
    $ogGambar = $ogGambar ?? asset('ikon/og-kaskelas.png');
@endphp

<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>{{ $judul }}</title>
    <meta name="description" content="{{ $deskripsi }}">
    <link rel="canonical" href="{{ $kanonik }}">

    {{--
        Halaman ini BOLEH diindeks — berbeda dari layouts/base yang selalu
        noindex. Hanya halaman pemasaran dan dokumen wajib yang memakai layout
        ini; dashboard dan halaman kelas tidak pernah.
    --}}
    <meta name="robots" content="index, follow, max-image-preview:large">

    <meta name="theme-color" content="#1d4ed8" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0b1220" media="(prefers-color-scheme: dark)">

    {{-- Open Graph: tautan ini akan disebar lewat WhatsApp, dan pratinjau yang
         kosong membuat orang ragu mengekliknya. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="KasKelas">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="{{ $judul }}">
    <meta property="og:description" content="{{ $deskripsi }}">
    <meta property="og:url" content="{{ $kanonik }}">
    <meta property="og:image" content="{{ $ogGambar }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="KasKelas — aplikasi pencatatan uang kas kelas">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $judul }}">
    <meta name="twitter:description" content="{{ $deskripsi }}">
    <meta name="twitter:image" content="{{ $ogGambar }}">

    <link rel="apple-touch-icon" href="/ikon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/ikon/favicon-32.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">

    @vite(['resources/css/app.css'])

    @if ($jsonLd)
        {{-- @json meng-escape </script> di dalam nilainya, jadi aman. --}}
        <script type="application/ld+json">@json($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    @endif
</head>
<body class="min-h-full bg-surface antialiased">
    <a href="#konten"
       class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-xl
              focus:bg-brand focus:px-4 focus:py-2.5 focus:font-semibold focus:text-brand-ink">
        Lompat ke konten
    </a>

    <header class="border-b border-line bg-card">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6">
            <a href="{{ route('beranda') }}" class="flex items-center gap-2.5">
                <span class="flex size-9 items-center justify-center rounded-xl bg-brand text-brand-ink">
                    <x-icon name="kelas" class="size-5" />
                </span>
                <span class="text-lg font-extrabold tracking-tight">KasKelas</span>
            </a>

            <nav aria-label="Navigasi utama" class="flex items-center gap-1 text-sm font-semibold sm:gap-3">
                <a href="{{ route('panduan') }}" class="hidden rounded-lg px-2.5 py-2 text-ink-soft hover:bg-surface hover:text-ink sm:block">Panduan</a>
                <a href="{{ route('demo') }}" class="rounded-lg px-2.5 py-2 text-ink-soft hover:bg-surface hover:text-ink">Demo</a>
                <a href="{{ route('login') }}" class="rounded-lg px-2.5 py-2 text-ink-soft hover:bg-surface hover:text-ink">Masuk</a>
                <a href="{{ route('daftar') }}"
                   class="rounded-xl bg-brand px-3.5 py-2 text-brand-ink shadow-sm hover:brightness-110">Daftar</a>
            </nav>
        </div>
    </header>

    <main id="konten">
        {{ $slot }}
    </main>

    <footer class="mt-16 border-t border-line bg-card">
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
            <div class="grid gap-8 sm:grid-cols-3">
                <div>
                    <p class="text-base font-extrabold">KasKelas</p>
                    <p class="mt-1.5 text-sm text-ink-faint">
                        Pencatatan uang kas kelas yang rapi, gratis, dan bisa dipantau seluruh anggota kelas.
                    </p>
                </div>

                <nav aria-label="Halaman" class="text-sm">
                    <p class="font-semibold text-ink">Halaman</p>
                    <ul class="mt-2 space-y-1.5 text-ink-faint">
                        <li><a class="hover:text-ink hover:underline" href="{{ route('panduan') }}">Panduan penggunaan</a></li>
                        <li><a class="hover:text-ink hover:underline" href="{{ route('demo') }}">Lihat kelas demo</a></li>
                        <li><a class="hover:text-ink hover:underline" href="{{ route('beranda') }}#faq">Pertanyaan umum</a></li>
                    </ul>
                </nav>

                <nav aria-label="Dokumen" class="text-sm">
                    <p class="font-semibold text-ink">Dokumen</p>
                    <ul class="mt-2 space-y-1.5 text-ink-faint">
                        <li><a class="hover:text-ink hover:underline" href="{{ route('privasi') }}">Kebijakan Privasi</a></li>
                        <li><a class="hover:text-ink hover:underline" href="{{ route('syarat') }}">Syarat Layanan</a></li>
                    </ul>
                </nav>
            </div>

            <p class="mt-8 border-t border-line pt-6 text-xs text-ink-faint">
                &copy; {{ date('Y') }} KasKelas. Dibuat untuk bendahara kelas di Indonesia.
            </p>
        </div>
    </footer>
</body>
</html>
