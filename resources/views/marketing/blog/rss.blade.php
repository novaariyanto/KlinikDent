<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
<channel>
    <title>{{ $appName }} Blog</title>
    <link>{{ route('blog.index') }}</link>
    <description>Artikel software klinik gigi, odontogram digital, dan rekam medis elektronik.</description>
    <language>id</language>
@foreach ($posts as $post)
    <item>
        <title>{{ $post->title }}</title>
        <link>{{ route('blog.show', $post->slug) }}</link>
        <guid>{{ route('blog.show', $post->slug) }}</guid>
        <pubDate>{{ optional($post->published_at)->toRssString() }}</pubDate>
        <description><![CDATA[{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $post->body), 280) }}]]></description>
    </item>
@endforeach
</channel>
</rss>
