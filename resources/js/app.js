import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

// Alpine dipakai seperlunya saja: menu dropdown, panel buka-tutup, dan dialog
// konfirmasi. Sisanya Blade biasa — tidak ada state aplikasi yang hidup di klien.
Alpine.plugin(collapse);
window.Alpine = Alpine;
Alpine.start();
