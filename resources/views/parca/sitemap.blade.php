{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($adresler as [$adres, $tarih])
    <url>
        <loc>{{ $adres }}</loc>
@if ($tarih)
        <lastmod>{{ $tarih->toAtomString() }}</lastmod>
@endif
    </url>
@endforeach
</urlset>
