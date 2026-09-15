/**
 * Service worker KasKelas.
 *
 * Aturannya satu kalimat: aset statis boleh di-cache, HALAMAN DATA TIDAK PERNAH
 * disajikan dari cache selama jaringan masih bisa dihubungi.
 *
 * Alasannya penting. Saldo kas yang tampil basi jauh lebih berbahaya daripada
 * halaman yang gagal dimuat: bendahara bisa mengira uang masih ada padahal sudah
 * terpakai. Karena itu halaman memakai network-first, dan cache-nya hanya dipakai
 * sebagai jaring pengaman saat benar-benar offline — dengan penanda yang jelas.
 */

const VERSI = 'kaskelas-v1';
const CACHE_ASET = `${VERSI}-aset`;
const CACHE_HALAMAN = `${VERSI}-halaman`;

const ASET_AWAL = [
    '/offline',
    '/ikon/ikon-192.png',
    '/ikon/ikon-512.png',
    '/ikon/ikon-maskable-512.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_ASET)
            .then((cache) => cache.addAll(ASET_AWAL))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((kunci) =>
                Promise.all(
                    kunci
                        .filter((nama) => !nama.startsWith(VERSI))
                        .map((nama) => caches.delete(nama)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

/** Berkas build Vite dan font: namanya ber-hash / stabil, jadi aman di-cache. */
function asetStatis(url) {
    return (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/ikon/') ||
        url.hostname === 'fonts.googleapis.com' ||
        url.hostname === 'fonts.gstatic.com'
    );
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Hanya GET yang disentuh. Permintaan tulis tidak pernah di-cache maupun diulang.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin && !asetStatis(url)) {
        return;
    }

    if (asetStatis(url)) {
        event.respondWith(cacheDulu(request));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(jaringanDulu(request));
    }
});

async function cacheDulu(request) {
    const tersimpan = await caches.match(request);

    if (tersimpan) {
        return tersimpan;
    }

    const respons = await fetch(request);

    if (respons.ok) {
        const cache = await caches.open(CACHE_ASET);
        cache.put(request, respons.clone());
    }

    return respons;
}

async function jaringanDulu(request) {
    try {
        const respons = await fetch(request);

        // Halaman bertoken publik ikut disimpan supaya anggota kelas masih bisa
        // membukanya saat sinyal hilang — tetapi hanya sebagai cadangan terakhir.
        if (respons.ok) {
            const cache = await caches.open(CACHE_HALAMAN);
            cache.put(request, respons.clone());
        }

        return respons;
    } catch (galat) {
        const cadangan = await caches.match(request);

        return cadangan || caches.match('/offline');
    }
}
