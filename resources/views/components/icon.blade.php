@props(['name', 'class' => 'size-5'])

@php
    // Satu keluarga ikon (gaya garis Lucide, stroke 1.75) supaya konsisten.
    // Emoji tidak dipakai sebagai ikon: renderingnya beda-beda tiap ponsel.
    $paths = [
        'dashboard' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/><path d="M9.5 20v-5.5h5V20"/>',
        'siswa' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 19.5c0-3 2.7-4.8 6-4.8s6 1.8 6 4.8"/><path d="M16.5 6.2a3 3 0 0 1 0 5.6"/><path d="M18 19.5c0-2-.7-3.4-1.9-4.3"/>',
        'periode' => '<rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M8 14.5h3M8 18h6"/>',
        'bayar' => '<rect x="2.5" y="6" width="19" height="12.5" rx="2.5"/><circle cx="12" cy="12.2" r="2.6"/><path d="M6 12.2h.01M18 12.2h.01"/>',
        'keluar-kas' => '<path d="M6 3h9l4 4v14H6z"/><path d="M14.5 3v4.5H19"/><path d="M9.5 12.5h5M9.5 16h3"/>',
        'laporan' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'audit' => '<path d="M12 3 4 6v5.5c0 4.6 3.2 8.2 8 9.5 4.8-1.3 8-4.9 8-9.5V6z"/><path d="m9.2 12.2 2 2 3.6-3.8"/>',
        'pengaturan' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 14.6a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5v.2a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1h.2a2 2 0 1 1 0 4H21a1.6 1.6 0 0 0-1.5 1z"/>',
        'logout' => '<path d="M15 17l5-5-5-5"/><path d="M20 12H9"/><path d="M12 20H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h6"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'tutup' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'tambah' => '<path d="M12 5v14M5 12h14"/>',
        'ubah' => '<path d="M4 20h4L19 9a2.1 2.1 0 0 0-3-3L5 17z"/><path d="m14.5 5.5 3 3"/>',
        'hapus' => '<path d="M4 7h16"/><path d="M9 7V5h6v2"/><path d="M6.5 7 7.5 20h9l1-13"/><path d="M10.5 11v5M13.5 11v5"/>',
        'cek' => '<path d="m5 12.5 4.5 4.5L19 7"/>',
        'peringatan' => '<path d="M12 4 2.8 19.5h18.4z"/><path d="M12 10v4M12 17h.01"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'cari' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'mata' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12"/><circle cx="12" cy="12" r="3"/>',
        'mata-tutup' => '<path d="M4 4l16 16"/><path d="M9.9 5.9A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3.2 4"/><path d="M6.4 8A17 17 0 0 0 2.5 12S6 18.5 12 18.5c1.3 0 2.5-.3 3.5-.8"/><path d="M9.9 10.1a3 3 0 0 0 4.1 4.2"/>',
        'kunci' => '<rect x="4.5" y="10" width="15" height="10.5" rx="2.5"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/>',
        'surat' => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="m3.5 7 8.5 6 8.5-6"/>',
        'masuk-arah' => '<path d="M12 20V5"/><path d="m5.5 11.5 6.5-6.5 6.5 6.5"/>',
        'keluar-arah' => '<path d="M12 4v15"/><path d="m5.5 12.5 6.5 6.5 6.5-6.5"/>',
        'dompet' => '<path d="M3.5 7.5A2.5 2.5 0 0 1 6 5h11.5a2 2 0 0 1 2 2v1"/><path d="M3.5 7.5V17a2.5 2.5 0 0 0 2.5 2.5h12.5a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2H6a2.5 2.5 0 0 1-2.5-2.5"/><path d="M16.5 13h.01"/>',
        'kanan' => '<path d="m9 5 7 7-7 7"/>',
        'kiri' => '<path d="m15 5-7 7 7 7"/>',
        'salin' => '<rect x="9" y="9" width="12" height="12" rx="2.5"/><path d="M5.5 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v.5"/>',
        'putar' => '<path d="M20.5 12a8.5 8.5 0 1 1-2.6-6.1"/><path d="M20.5 4v5h-5"/>',
        'unduh' => '<path d="M12 4v11"/><path d="m7.5 11 4.5 4.5 4.5-4.5"/><path d="M4.5 19.5h15"/>',
        'kelas' => '<path d="M3 9.5 12 5l9 4.5-9 4.5z"/><path d="M6.5 11.8V16c0 1.4 2.5 2.6 5.5 2.6s5.5-1.2 5.5-2.6v-4.2"/><path d="M21 9.5V15"/>',
    ];
@endphp

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">
    {!! $paths[$name] ?? $paths['info'] !!}
</svg>
