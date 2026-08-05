{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach ($urls as $entry)
    <url>
        <loc>{{ $entry['url'] }}</loc>
        <lastmod>{{ $entry['lastmod']->toAtomString() }}</lastmod>
        @if (isset($entry['changefreq']))
        <changefreq>{{ $entry['changefreq'] }}</changefreq>
        @endif
        @if (isset($entry['priority']))
        <priority>{{ $entry['priority'] }}</priority>
        @endif
        @foreach ($entry['images'] ?? [] as $image)
        <image:image><image:loc>{{ $image }}</image:loc></image:image>
        @endforeach
    </url>
@endforeach
</urlset>
