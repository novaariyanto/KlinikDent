@extends('layouts.app')

@section('title', 'Riwayat Pesan')

@section('content')
    <x-page-header title="Riwayat Pesan" :breadcrumb="['WhatsApp Otomatis' => route('whatsapp.dashboard'), 'Riwayat Pesan' => null]" />
    <x-alert />
    @include('whatsapp.partials.nav')

    <x-card title="Riwayat Pesan">
        <x-table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Pasien</th>
                    <th>Nomor</th>
                    <th>Cabang</th>
                    <th>Kategori</th>
                    <th>Template</th>
                    <th>Event</th>
                    <th>Status</th>
                    <th>Message ID</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td><a href="{{ route('whatsapp.logs.show', $log) }}">{{ $log->created_at?->format('d M Y H:i') }}</a></td>
                        <td>{{ $log->patient?->name ?: $log->to_name ?: '-' }}</td>
                        <td>{{ $log->phone_number ?: '-' }}</td>
                        <td>{{ $log->branch?->name ?: '-' }}</td>
                        <td>{{ $log->category?->label() }}</td>
                        <td>{{ $log->template_name }}</td>
                        <td><code>{{ $log->event_key?->value }}</code></td>
                        <td><span class="{{ $log->status->badgeClass() }}">{{ $log->status->label() }}</span></td>
                        <td>{{ $log->provider_message_id ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted">Belum ada pesan.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        {{ $logs->links() }}
    </x-card>
@endsection
