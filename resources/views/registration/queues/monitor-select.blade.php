@extends('layouts.app')

@section('title', 'Monitor Antrean')

@section('content')
    <x-page-header title="Monitor Antrean" :breadcrumb="['Antrean' => route('queue.today'), 'Monitor' => null]" />
    <x-card title="Pilih Cabang">
        <form method="GET" action="{{ route('queue.monitor') }}">
            <div class="row">
                <div class="col-md-6">
                    <x-select
                        name="branch_id"
                        label="Cabang"
                        :options="collect($branches)->mapWithKeys(fn ($branch) => [$branch->id => $branch->name])->all()"
                        required
                    />
                </div>
            </div>
            <x-button type="submit" icon="bx bx-tv">Buka Monitor</x-button>
        </form>
    </x-card>
@endsection
