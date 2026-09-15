import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Document root = root project (lihat DEPLOY.md), bukan public/.
            // Hasil build karena itu langsung ditulis ke ./build, bukan ./public/build,
            // supaya URL /build/assets/... cocok dengan lokasi file di server.
            publicDirectory: '.',
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
