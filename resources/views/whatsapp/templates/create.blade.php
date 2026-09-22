@extends('layouts.app')

@section('title', 'Ajukan Template Meta')

@section('content')
    <x-page-header title="Ajukan Template Meta" :breadcrumb="['WhatsApp Otomatis' => route('whatsapp.dashboard'), 'Template Pesan' => route('whatsapp.templates.index'), 'Edit' => route('whatsapp.templates.edit', $template), 'Ajukan' => null]" />
    <x-alert />
    @include('whatsapp.partials.nav')

    <x-card title="Ajukan ke WhatsCRM / Meta">
        <p class="text-muted">Event: <code>{{ $template->event_key->value }}</code> · Kategori WhatsCRM: <strong>{{ $category }}</strong></p>
        <p class="text-muted small">Status awal selalu pending. Template baru bisa dipakai otomasi setelah Meta menyetujui.</p>

        <form method="POST" action="{{ route('whatsapp.templates.store', $template) }}">
            @csrf
            <x-input name="name" label="Nama tampilan" :value="old('name', $template->globalTemplate?->label ?? $template->event_key->label())" required />
            <x-select
                name="language"
                label="Bahasa"
                :options="$languages"
                :selected="old('language', $template->language_code ?: 'id')"
                :placeholder="null"
                required
            />
            <x-select
                name="header_type"
                label="Header"
                :options="['none' => 'Tidak ada', 'text' => 'Teks']"
                :selected="old('header_type', 'none')"
                :placeholder="null"
                required
            />
            <div id="header-text-wrap" class="@if (old('header_type', 'none') !== 'text') d-none @endif">
                <x-input name="header_text" label="Teks header" :value="old('header_text')" maxlength="60" />
            </div>
            <div class="mb-3">
                <label for="body_text" class="form-label">Isi template</label>
                <textarea name="body_text" id="body_text" rows="10" class="form-control font-monospace" required>{{ old('body_text', $template->preview_body) }}</textarea>
                <p class="text-muted small mt-1 mb-0">Gunakan <code>@{{1}}</code>, <code>@{{2}}</code>. Sistem mengirim placeholder angka ke WhatsCRM.</p>
            </div>
            <x-input name="footer_text" label="Footer (opsional)" :value="old('footer_text')" maxlength="60" />
            <x-button type="submit" icon="bx bx-send">Ajukan ke Meta</x-button>
            <x-button href="{{ route('whatsapp.templates.edit', $template) }}" variant="secondary">Batal</x-button>
        </form>
    </x-card>
@endsection

@push('scripts')
<script>
    (function () {
        const type = document.getElementById('header_type');
        const wrap = document.getElementById('header-text-wrap');
        if (! type || ! wrap) {
            return;
        }
        type.addEventListener('change', function () {
            wrap.classList.toggle('d-none', type.value !== 'text');
        });
    })();
</script>
@endpush
