@extends('layouts.app')
@section('title', 'Keyword Strategy')
@section('content')
<x-page-header title="Keyword & Topic Cluster" :breadcrumb="['CMS' => route('cms.dashboard'), 'Keyword' => null]" />
<x-alert />
<div class="row">
    <div class="col-md-4"><x-card title="Tambah keyword"><form method="POST" action="{{ route('cms.keywords.store') }}">@csrf
        <div class="mb-2"><input class="form-control" name="keyword" required></div>
        <div class="mb-2"><select class="form-select" name="pillar"><option value="software-klinik-gigi">Software Klinik Gigi</option><option value="odontogram-digital">Odontogram Digital</option><option value="rekam-medis-elektronik">Rekam Medis Elektronik</option><option value="operasional-klinik">Operasional Klinik</option></select></div>
        <div class="mb-2"><input class="form-control" name="intent" placeholder="informational/commercial"></div>
        <div class="mb-2"><input class="form-control" name="target_url" placeholder="/odontogram-digital atau /blog/..."></div>
        <div class="mb-2"><textarea class="form-control" name="notes" rows="2"></textarea></div>
        <button class="btn btn-primary">Simpan</button>
    </form></x-card></div>
    <div class="col-md-8"><x-card title="Daftar">
        <x-table><thead><tr><th>Keyword</th><th>Pillar</th><th>Status</th><th>Target</th></tr></thead>
        <tbody>@foreach($keywords as $item)
            <tr><td>{{ $item->keyword }}</td><td>{{ $item->pillar }}</td><td>{{ $item->status }}</td><td>{{ $item->target_url }}</td></tr>
        @endforeach</tbody></x-table>
        {{ $keywords->links() }}
    </x-card></div>
</div>
@endsection
