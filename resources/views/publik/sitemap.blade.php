<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($halaman as $h)
    <url>
        <loc>{{ $h['loc'] }}</loc>
        <changefreq>{{ $h['ubah'] }}</changefreq>
        <priority>{{ $h['prioritas'] }}</priority>
    </url>
@endforeach
</urlset>
