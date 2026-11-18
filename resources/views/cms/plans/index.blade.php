@extends('layouts.app')
@section('title', 'Content Planning')
@section('content')
<x-page-header title="Content Planning" :breadcrumb="['CMS' => route('cms.dashboard'), 'Planning' => null]" />
<x-alert />
<div class="row">
    <div class="col-md-4"><x-card title="Tambah rencana"><form method="POST" action="{{ route('cms.plans.store') }}">@csrf
        <div class="mb-2"><input class="form-control" name="title" required placeholder="Judul rencana"></div>
        <div class="mb-2"><input class="form-control" name="target_keyword" placeholder="Target keyword"></div>
        <div class="mb-2"><input class="form-control" name="search_intent" placeholder="Intent (informational/commercial)"></div>
        <div class="mb-2"><select class="form-select" name="category_id"><option value="">Kategori</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
        <div class="mb-2"><input class="form-control" type="date" name="target_publish_date"></div>
        <div class="mb-2"><select class="form-select" name="status"><option value="idea">Idea</option><option value="research">Research</option><option value="draft">Draft</option><option value="review">Review</option><option value="scheduled">Scheduled</option></select></div>
        <div class="mb-2"><textarea class="form-control" name="outline" rows="3" placeholder="Outline"></textarea></div>
        <button class="btn btn-primary">Simpan</button>
    </form></x-card></div>
    <div class="col-md-8"><x-card title="Kalender / daftar">
        <x-table><thead><tr><th>Judul</th><th>Keyword</th><th>Status</th><th>Target</th></tr></thead>
        <tbody>@foreach($plans as $plan)
            <tr><td>{{ $plan->title }}</td><td>{{ $plan->target_keyword }}</td><td>{{ $plan->status }}</td><td>{{ optional($plan->target_publish_date)->format('d M Y') }}</td></tr>
        @endforeach</tbody></x-table>
        {{ $plans->links() }}
    </x-card></div>
</div>
@endsection
