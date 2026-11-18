@extends('layouts.app')
@section('title', 'Redirect Manager')
@section('content')
<x-page-header title="Redirect Manager" :breadcrumb="['CMS' => route('cms.dashboard'), 'Redirect' => null]" />
<x-alert />
<div class="row">
    <div class="col-md-4"><x-card title="Tambah redirect"><form method="POST" action="{{ route('cms.redirects.store') }}">@csrf
        <div class="mb-2"><input class="form-control" name="source_path" placeholder="/blog/slug-lama" required></div>
        <div class="mb-2"><input class="form-control" name="destination_url" placeholder="/blog/slug-baru" required></div>
        <div class="mb-2"><select class="form-select" name="status_code"><option value="301">301</option><option value="302">302</option></select></div>
        <button class="btn btn-primary">Simpan</button>
    </form></x-card></div>
    <div class="col-md-8"><x-card title="Daftar">
        <x-table><thead><tr><th>Source</th><th>Destination</th><th>Code</th><th>Hits</th><th></th></tr></thead>
        <tbody>@foreach($redirects as $item)
            <tr>
                <td>{{ $item->source_path }}</td>
                <td>{{ $item->destination_url }}</td>
                <td>{{ $item->status_code }}</td>
                <td>{{ $item->hit_count }}</td>
                <td><form method="POST" action="{{ route('cms.redirects.destroy', $item) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-soft-danger">Hapus</button></form></td>
            </tr>
        @endforeach</tbody></x-table>
        {{ $redirects->links() }}
    </x-card></div>
</div>
@endsection
