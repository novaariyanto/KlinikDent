@extends('layouts.app')

@section('title', 'Dashboard SEO')

@section('content')
    <x-page-header title="Dashboard SEO & Content" :breadcrumb="['CMS' => route('cms.dashboard'), 'Dashboard' => null]" />
    <x-alert />

    <div class="row">
        @foreach ([
            ['Total artikel', $total],
            ['Published', $published],
            ['Draft', $draft],
            ['Scheduled', $scheduled],
            ['Perlu update (>6 bln)', $needsUpdate],
            ['Tanpa meta description', $missingMeta],
            ['Tanpa featured image', $missingImage],
            ['Views blog 30h', $blogViews],
            ['Klik CTA 30h', $ctaClicks],
        ] as [$label, $value])
            <div class="col-md-4 col-xl-3 mb-3">
                <div class="card"><div class="card-body">
                    <div class="text-muted">{{ $label }}</div>
                    <div class="fs-3 fw-semibold text-primary">{{ number_format($value) }}</div>
                </div></div>
            </div>
        @endforeach
        <div class="col-md-4 col-xl-3 mb-3">
            <div class="card"><div class="card-body">
                <div class="text-muted">Google Search Console</div>
                <div class="fw-semibold">Belum terhubung</div>
                <div class="small text-muted">Hubungkan lewat OAuth resmi sebelum menampilkan impressions/clicks.</div>
            </div></div>
        </div>
    </div>

    <x-card title="Artikel paling banyak dibaca">
        <x-table>
            <thead><tr><th>Judul</th><th>Status</th><th>Views</th></tr></thead>
            <tbody>
                @forelse ($topPosts as $post)
                    <tr>
                        <td><a href="{{ route('cms.posts.edit', $post) }}">{{ $post->title }}</a></td>
                        <td>{{ $post->status?->label() ?? $post->status }}</td>
                        <td>{{ number_format($post->view_count) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>
@endsection
