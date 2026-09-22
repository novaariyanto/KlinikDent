@extends('layouts.app')

@section('title', 'Detail Pesan')

@section('content')
    <x-page-header title="Detail Pesan" :breadcrumb="['WhatsApp Otomatis' => route('whatsapp.dashboard'), 'Riwayat Pesan' => route('whatsapp.logs.index'), 'Detail' => null]" />
    <x-alert />
    @include('whatsapp.partials.nav')

    <div class="row">
        <div class="col-xl-6">
            <x-card title="Pengiriman">
                <p><strong>Status:</strong> <span class="{{ $log->status->badgeClass() }}">{{ $log->status->label() }}</span></p>
                <p><strong>Event:</strong> <code>{{ $log->event_key?->value }}</code></p>
                <p><strong>Template:</strong> {{ $log->template_name }}</p>
                <p><strong>Pasien:</strong> {{ $log->patient?->name ?: $log->to_name }}</p>
                <p><strong>Nomor:</strong> {{ $log->phone_number }}</p>
                <p><strong>Cabang:</strong> {{ $log->branch?->name ?: '-' }}</p>
                <p><strong>Message ID:</strong> {{ $log->provider_message_id ?: '-' }}</p>
                @if ($log->error_message)
                    <p class="text-danger mb-0"><strong>Error:</strong> {{ $log->error_code }} — {{ $log->error_message }}</p>
                    <p class="text-muted small">{{ $log->failed_at?->format('d M Y H:i') }}</p>
                @endif
            </x-card>
        </div>
        <div class="col-xl-6">
            <x-card title="Parameter terkirim">
                <pre class="mb-0 small">{{ json_encode($log->body_params, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
            </x-card>
            <x-card title="Response API">
                <pre class="mb-0 small">{{ json_encode($log->response_payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) ?: '-' }}</pre>
            </x-card>
        </div>
    </div>
@endsection
