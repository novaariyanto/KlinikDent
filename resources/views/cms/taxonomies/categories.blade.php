@extends('layouts.app')
@section('title', 'Kategori Blog')
@section('content')
<x-page-header title="Kategori" :breadcrumb="['CMS' => route('cms.dashboard'), 'Kategori' => null]" />
<x-alert />
<div class="row">
    <div class="col-md-5">
        <x-card title="Tambah kategori">
            <form method="POST" action="{{ route('cms.categories.store') }}">@csrf
                <div class="mb-2"><input class="form-control" name="name" placeholder="Nama" required></div>
                <div class="mb-2"><input class="form-control" name="slug" placeholder="Slug (opsional)"></div>
                <div class="mb-2"><textarea class="form-control" name="description" rows="2" placeholder="Deskripsi"></textarea></div>
                <button class="btn btn-primary" type="submit">Simpan</button>
            </form>
        </x-card>
    </div>
    <div class="col-md-7">
        <x-card title="Daftar">
            <x-table><thead><tr><th>Nama</th><th>Slug</th></tr></thead>
            <tbody>@foreach($categories as $item)<tr><td>{{ $item->name }}</td><td>{{ $item->slug }}</td></tr>@endforeach</tbody></x-table>
        </x-card>
    </div>
</div>
@endsection
