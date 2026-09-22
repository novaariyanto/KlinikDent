@extends('layouts.app')

@section('title', 'Template Pesan')

@section('content')
    <x-page-header title="Template Pesan" :breadcrumb="['WhatsApp Otomatis' => route('whatsapp.dashboard'), 'Template Pesan' => null]" />
    <x-alert />
    @include('whatsapp.partials.nav')

    @foreach ($categories as $category)
        @php $templates = $grouped[$category->value] ?? collect(); @endphp
        @continue($templates->isEmpty())

        <x-card :title="$category->label()" class="mb-3">
            <div class="row">
                @foreach ($templates as $template)
                    <div class="col-md-6 col-xl-4 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="mb-1">{{ $template->globalTemplate?->label ?? $template->event_key->label() }}</h5>
                                    <code>{{ $template->event_key->value }}</code>
                                </div>
                                @if ($template->is_enabled)
                                    <span class="badge bg-success">● Aktif</span>
                                @else
                                    <span class="badge bg-secondary">○ Nonaktif</span>
                                @endif
                            </div>
                            <p class="mb-1 text-muted small">Template WhatsApp: <strong>{{ $template->whatsapp_template_name }}</strong></p>
                            <p class="mb-1 text-muted small">Bahasa: {{ $template->language_code === 'id' ? 'Indonesia' : strtoupper($template->language_code) }}</p>
                            <p class="mb-1 text-muted small">Parameter: {{ $template->parameterCount() }}</p>
                            @if ($template->remote_status)
                                <p class="mb-3 text-muted small">Meta:
                                    @if ($template->remote_status === 'approved')
                                        <span class="badge bg-success">approved</span>
                                    @elseif ($template->remote_status === 'pending')
                                        <span class="badge bg-warning">pending</span>
                                    @else
                                        <span class="badge bg-danger">{{ $template->remote_status }}</span>
                                    @endif
                                </p>
                            @else
                                <p class="mb-3 text-muted small">Meta: belum disinkronkan</p>
                            @endif
                            <x-button href="{{ route('whatsapp.templates.edit', $template) }}" variant="secondary" icon="bx bx-edit">Edit</x-button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endforeach
@endsection
