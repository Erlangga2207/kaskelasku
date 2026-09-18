@props(['judul' => null])

@php
    use Illuminate\Support\Facades\Route;

    $kelas = \App\Support\CurrentClassroom::get();

    // Menu dibangun dari daftar tunggal lalu disaring: route yang belum dibuat
    // pada fase berjalan otomatis tidak muncul, tanpa mengubah layout.
    $menu = collect([
        ['route' => 'dashboard', 'label' => 'Beranda', 'ikon' => 'dashboard', 'utama' => true],
        ['route' => 'siswa.index', 'label' => 'Siswa', 'ikon' => 'siswa', 'utama' => true],
        ['route' => 'pembayaran.index', 'label' => 'Bayar', 'ikon' => 'bayar', 'utama' => true],
        ['route' => 'pengeluaran.index', 'label' => 'Keluar', 'ikon' => 'keluar-kas', 'utama' => true],
        ['route' => 'laporan.index', 'label' => 'Laporan', 'ikon' => 'laporan', 'utama' => true],
        ['route' => 'periode.index', 'label' => 'Periode', 'ikon' => 'periode', 'utama' => false],
        ['route' => 'campaign.index', 'label' => 'Iuran insidental', 'ikon' => 'dompet', 'utama' => false],
        ['route' => 'pengingat.index', 'label' => 'Pengingat', 'ikon' => 'surat', 'utama' => false],
        ['route' => 'tutup-buku.index', 'label' => 'Tutup buku', 'ikon' => 'kunci', 'utama' => false],
        ['route' => 'audit.index', 'label' => 'Audit', 'ikon' => 'audit', 'utama' => false],
        ['route' => 'pengaturan.edit', 'label' => 'Pengaturan', 'ikon' => 'pengaturan', 'utama' => false],
    ])->filter(fn ($m) => Route::has($m['route']));

    $aktif = fn ($route) => request()->routeIs(str_replace(['index', 'edit'], '*', $route))
        || request()->routeIs($route);

    $menuUtama = $menu->where('utama', true);

    // Kelas grid ditulis utuh, bukan dirangkai string — Tailwind memindai berkas
    // sumber apa adanya, jadi nama kelas hasil interpolasi tidak akan dibuatkan CSS.
    $kolomNav = [1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-3', 4 => 'grid-cols-4', 5 => 'grid-cols-5'][$menuUtama->count()] ?? 'grid-cols-5';
@endphp

<x-layouts.base :judul="$judul">
    <div class="min-h-dvh lg:grid lg:grid-cols-[16rem_1fr]">

        {{-- Sidebar: navigasi utama di layar lebar (Material Adaptive: >=1024px pakai sidebar) --}}
        <aside class="hidden border-r border-line bg-elevated lg:flex lg:flex-col">
            <div class="flex items-center gap-2.5 px-5 py-5">
                <span class="flex size-9 items-center justify-center rounded-xl bg-brand text-brand-ink">
                    <x-icon name="kelas" class="size-5" />
                </span>
                <span class="text-lg font-extrabold tracking-tight">KasKelas</span>
            </div>

            <nav aria-label="Navigasi utama" class="flex-1 space-y-1 px-3 pb-4">
                @foreach ($menu as $item)
                    <a href="{{ route($item['route']) }}"
                       @if ($aktif($item['route'])) aria-current="page" @endif
                       class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-[0.9375rem] font-medium transition-colors
                              {{ $aktif($item['route'])
                                    ? 'bg-brand-soft text-brand-soft-ink font-semibold'
                                    : 'text-ink-soft hover:bg-surface hover:text-ink' }}">
                        <x-icon :name="$item['ikon']" class="size-5 shrink-0" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            @if ($kelas)
                <div class="border-t border-line px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Kelas aktif</p>
                    <p class="mt-1 truncate font-semibold text-ink">{{ $kelas->nama_kelas }}</p>
                    <p class="truncate text-sm text-ink-faint">{{ $kelas->sekolah }}</p>
                </div>
            @endif
        </aside>

        <div class="flex min-h-dvh flex-col">
            {{-- Top app bar: pola Android untuk struktur utama --}}
            <header class="sticky top-0 z-20 border-b border-line bg-elevated/95 backdrop-blur">
                <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3 sm:px-6">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-brand text-brand-ink lg:hidden">
                        <x-icon name="kelas" class="size-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <h1 class="truncate text-lg font-bold leading-tight">{{ $judul ?? 'KasKelas' }}</h1>
                        @if ($kelas)
                            <p class="truncate text-xs text-ink-faint lg:hidden">
                                {{ $kelas->nama_kelas }} · {{ $kelas->sekolah }}
                            </p>
                        @endif
                    </div>

                    <div x-data="{ buka: false }" class="relative shrink-0">
                        <button type="button" @click="buka = !buka" :aria-expanded="buka"
                                aria-haspopup="menu"
                                class="flex size-11 items-center justify-center rounded-xl text-ink-soft transition-colors hover:bg-surface hover:text-ink cursor-pointer">
                            <span class="sr-only">Menu akun</span>
                            <x-icon name="menu" class="size-5" />
                        </button>

                        <div x-cloak x-show="buka" @click.outside="buka = false" @keydown.escape.window="buka = false"
                             x-transition.opacity.duration.150ms
                             role="menu"
                             class="absolute right-0 z-30 mt-1 w-60 overflow-hidden rounded-xl border border-line bg-elevated shadow-lg">
                            <div class="border-b border-line px-4 py-3">
                                <p class="truncate font-semibold">{{ auth()->user()->nama }}</p>
                                <p class="truncate text-sm text-ink-faint">{{ auth()->user()->email }}</p>
                            </div>

                            @foreach ($menu->where('utama', false) as $item)
                                <a href="{{ route($item['route']) }}" role="menuitem"
                                   class="flex min-h-11 items-center gap-3 px-4 text-[0.9375rem] text-ink-soft transition-colors hover:bg-surface hover:text-ink lg:hidden">
                                    <x-icon :name="$item['ikon']" class="size-5" />{{ $item['label'] }}
                                </a>
                            @endforeach

                            {{-- Aksi keluar dipisahkan garis dari menu navigasi biasa. --}}
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-line">
                                @csrf
                                <button type="submit" role="menuitem"
                                        class="flex min-h-11 w-full items-center gap-3 px-4 text-left text-[0.9375rem] font-medium text-keluar transition-colors hover:bg-keluar-soft cursor-pointer">
                                    <x-icon name="logout" class="size-5" />Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            {{-- pb-24 menyediakan ruang supaya isi tidak tertutup bottom nav di ponsel --}}
            <main id="konten" class="mx-auto w-full max-w-5xl flex-1 space-y-4 px-4 pb-24 pt-4 sm:px-6 lg:pb-8">
                <x-ui.flash />
                {{ $slot }}
            </main>

            {{-- Bottom navigation: maksimal 5 item, ikon + label (Material) --}}
            <nav aria-label="Navigasi cepat"
                 class="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-elevated/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden">
                <ul class="mx-auto grid max-w-md {{ $kolomNav }}">
                    @foreach ($menuUtama as $item)
                        <li>
                            <a href="{{ route($item['route']) }}"
                               @if ($aktif($item['route'])) aria-current="page" @endif
                               class="flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 py-1.5 text-[0.6875rem] font-medium transition-colors
                                      {{ $aktif($item['route']) ? 'text-brand' : 'text-ink-faint' }}">
                                <x-icon :name="$item['ikon']" class="size-5" />
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </div>
</x-layouts.base>
