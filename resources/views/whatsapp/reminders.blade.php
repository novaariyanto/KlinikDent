@extends('layouts.app')

@section('title', 'Pengingat Jadwal')

@section('content')
    <x-page-header title="Pengingat Jadwal" :breadcrumb="['WhatsApp Otomatis' => route('whatsapp.dashboard'), 'Pengingat Jadwal' => null]" />
    <x-alert />
    @include('whatsapp.partials.nav')

    <x-card title="Pengingat Jadwal">
        <form method="POST" action="{{ route('whatsapp.reminders.update') }}">
            @csrf
            @method('PUT')

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="reminder_enabled" value="1" id="reminder_enabled" @checked(old('reminder_enabled', $reminder?->is_enabled)) @disabled(! $canManage)>
                <label class="form-check-label" for="reminder_enabled">Aktifkan pengingat jadwal</label>
            </div>

            <p class="fw-semibold">Kapan dikirim?</p>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="h2" value="1" id="h2" @checked(old('h2', $reminder?->configValue('h2', true))) @disabled(! $canManage)>
                <label class="form-check-label" for="h2">H-2 hari</label>
            </div>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="h1" value="1" id="h1" @checked(old('h1', $reminder?->configValue('h1', true))) @disabled(! $canManage)>
                <label class="form-check-label" for="h1">H-1 hari</label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="today" value="1" id="today" @checked(old('today', $rules->get('appointment_today')?->is_enabled)) @disabled(! $canManage)>
                <label class="form-check-label" for="today">Hari-H</label>
            </div>

            <x-input name="send_time" type="time" label="Jam pengiriman" :value="old('send_time', $reminder?->configValue('send_time', '09:00'))" :disabled="! $canManage" />

            <hr>
            <p class="fw-semibold">Automation terkait</p>
            @foreach ([
                'confirmation' => ['Konfirmasi appointment', 'appointment_confirmation'],
                'rescheduled' => ['Perubahan jadwal', 'appointment_rescheduled'],
                'cancelled' => ['Pembatalan jadwal', 'appointment_cancelled'],
                'missed' => ['Pasien tidak hadir', 'appointment_missed'],
                'control' => ['Pengingat kontrol', 'control_reminder'],
            ] as $field => $meta)
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="{{ $field }}" value="1" id="{{ $field }}" @checked(old($field, $rules->get($meta[1])?->is_enabled)) @disabled(! $canManage)>
                    <label class="form-check-label" for="{{ $field }}">{{ $meta[0] }}</label>
                </div>
            @endforeach

            @if ($canManage)
                <div class="mt-3">
                    <x-button type="submit" icon="bx bx-save">Simpan</x-button>
                </div>
            @endif
        </form>
    </x-card>
@endsection
