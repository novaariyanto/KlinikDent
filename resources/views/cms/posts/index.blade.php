@extends('layouts.app')

@section('title', 'Semua Artikel')

@section('content')
    <x-page-header title="Semua Artikel" :breadcrumb="['CMS' => route('cms.dashboard'), 'Artikel' => null]" />
    <x-alert />

    <x-card title="Artikel blog">
        <x-slot:header-actions>
            @can('cms.manage')
                <x-button href="{{ route('cms.posts.create') }}" icon="bx bx-plus">Tambah Artikel</x-button>
            @endcan
        </x-slot:header-actions>

        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Cari judul/slug"></div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary" type="submit">Filter</button></div>
        </form>

        <x-table>
            <thead><tr><th>Judul</th><th>Status</th><th>Kategori</th><th>Publish</th><th></th></tr></thead>
            <tbody>
                @foreach ($posts as $post)
                    <tr>
                        <td>
                            <a href="{{ route('cms.posts.edit', $post) }}">{{ $post->title }}</a>
                            <div class="small text-muted">/blog/{{ $post->slug }}</div>
                        </td>
                        <td>{{ $post->status->label() }}</td>
                        <td>{{ $post->category?->name ?: '—' }}</td>
                        <td>{{ optional($post->published_at)->format('d M Y H:i') ?: '—' }}</td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-soft-secondary" href="{{ route('cms.posts.preview', $post) }}" target="_blank">Preview</a>
                            <form class="d-inline" method="POST" action="{{ route('cms.posts.duplicate', $post) }}">@csrf<button class="btn btn-sm btn-soft-primary">Duplikat</button></form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        {{ $posts->links() }}
    </x-card>
@endsection
