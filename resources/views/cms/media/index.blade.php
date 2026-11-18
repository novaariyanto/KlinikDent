@extends('layouts.app')
@section('title', 'Media Library')
@section('content')
<x-page-header title="Media Library" :breadcrumb="['CMS' => route('cms.dashboard'), 'Media' => null]" />
<x-alert />
<div class="row">
    <div class="col-md-4">
        <x-card title="Upload">
            <form method="POST" action="{{ route('cms.media.store') }}" enctype="multipart/form-data">@csrf
                <div class="mb-2"><input class="form-control" type="file" name="file" accept="image/*" required></div>
                <div class="mb-2"><input class="form-control" name="alt" placeholder="ALT text"></div>
                <div class="mb-2"><input class="form-control" name="caption" placeholder="Caption"></div>
                <button class="btn btn-primary">Unggah</button>
            </form>
        </x-card>
    </div>
    <div class="col-md-8">
        <x-card title="Galeri">
            <div class="row">
                @foreach ($media as $item)
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-2">
                            <img src="{{ $item->url() }}" alt="{{ $item->alt }}" class="img-fluid mb-2">
                            <div class="small">ID {{ $item->id }} · {{ $item->alt ?: 'tanpa ALT' }}</div>
                            <form method="POST" action="{{ route('cms.media.destroy', $item) }}" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-soft-danger mt-1">Hapus</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
            {{ $media->links() }}
        </x-card>
    </div>
</div>
@endsection
