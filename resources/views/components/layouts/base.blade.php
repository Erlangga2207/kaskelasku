@props(['judul' => null])

<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    {{-- initial-scale=1 tanpa maximum-scale: pengguna harus tetap bisa memperbesar halaman. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1d4ed8" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0b1220" media="(prefers-color-scheme: dark)">
    <title>{{ $judul ? $judul.' · KasKelas' : 'KasKelas' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none !important}</style>
</head>
<body class="min-h-full antialiased">
    {{-- Lompat ke konten: wajib supaya pengguna keyboard tidak menelusuri seluruh menu. --}}
    <a href="#konten"
       class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-xl
              focus:bg-brand focus:px-4 focus:py-2.5 focus:font-semibold focus:text-brand-ink">
        Lompat ke konten
    </a>

    {{ $slot }}
</body>
</html>
