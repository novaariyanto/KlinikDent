@extends('layouts.app')
@section('title', 'Tag Blog')
@section('content')
<x-page-header title="Tag" :breadcrumb="['CMS' => route('cms.dashboard'), 'Tag' => null]" />
<x-alert />
<div class="row">
    <div class="col-md-5"><x-card title="Tambah tag"><form method="POST" action="{{ route('cms.tags.store') }}">@csrf
        <div class="mb-2"><input class="form-control" name="name" required></div>
        <div class="mb-2"><input class="form-control" name="slug" placeholder="Slug"></div>
        <button class="btn btn-primary">Simpan</button>
    </form></x-card></div>
    <div class="col-md-7"><x-card title="Daftar"><x-table><thead><tr><th>Nama</th><th>Slug</th></tr></thead>
        <tbody>@foreach($tags as $item)<tr><td>{{ $item->name }}</td><td>{{ $item->slug }}</td></tr>@endforeach</tbody></x-table></x-card></div>
</div>
@endsection
