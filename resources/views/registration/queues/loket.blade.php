@extends('layouts.app')

@section('title', 'Loket')

@section('content')
    <x-page-header title="Loket" :breadcrumb="['Antrean' => route('queue.today'), 'Loket' => null]" />
    <x-alert />
    @include('registration.queues.partials.nav')
    <div class="row">
        @forelse ($groups as $doctor => $queues)
            <div class="col-lg-6 mb-3">
                <x-card :title="$doctor">
                    <x-table>
                        <thead>
                            <tr>
                                <th>Nomor</th>
                                <th>Pasien</th>
                                <th>Poli</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($queues as $queue)
                                <tr>
                                    <td>{{ $queue->displayNumber() }}</td>
                                    <td>{{ $queue->visit?->patient?->name }}</td>
                                    <td>{{ $queue->visit?->room?->name ?: ($queue->schedule?->timeRange() ?: '-') }}</td>
                                    <td><span class="{{ $queue->status->badgeClass() }}">{{ $queue->status->label() }}</span></td>
                                    <td class="text-end">@include('registration.queues.partials.actions', ['queue' => $queue])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                </x-card>
            </div>
        @empty
            <div class="col-12"><x-card title="Hari ini"><p class="mb-0 text-muted">Belum ada antrean.</p></x-card></div>
        @endforelse
    </div>
@endsection
