@extends('layouts.app')
@section('title', 'Penulis')
@section('content')
<x-page-header title="Penulis" :breadcrumb="['CMS' => route('cms.dashboard'), 'Penulis' => null]" />
<x-alert />
<div class="row">
    <div class="col-md-5"><x-card title="Tambah penulis"><form method="POST" action="{{ route('cms.authors.store') }}">@csrf
        <div class="mb-2"><input class="form-control" name="name" placeholder="Nama" required></div>
        <div class="mb-2"><input class="form-control" name="slug" placeholder="Slug"></div>
        <div class="mb-2"><input class="form-control" name="role_title" placeholder="Jabatan"></div>
        <div class="mb-2"><textarea class="form-control" name="bio" rows="3"></textarea></div>
        <button class="btn btn-primary">Simpan</button>
    </form></x-card></div>
    <div class="col-md-7"><x-card title="Daftar"><x-table><thead><tr><th>Nama</th><th>Slug</th><th>Jabatan</th></tr></thead>
        <tbody>@foreach($authors as $item)<tr><td>{{ $item->name }}</td><td>{{ $item->slug }}</td><td>{{ $item->role_title }}</td></tr>@endforeach</tbody></x-table></x-card></div>
</div>
@endsection
