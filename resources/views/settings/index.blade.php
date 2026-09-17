@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <x-page-header title="Settings" :breadcrumb="['System' => null, 'Settings' => route('settings.index')]" />

    <x-alert />

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-xl-6">
                <x-card title="Application">
                    <x-input name="app_name" label="Application Name" :value="$settings['app_name']" required />

                    <x-select
                        name="timezone"
                        label="Timezone"
                        :options="collect($timezones)->mapWithKeys(fn ($timezone) => [$timezone => $timezone])->all()"
                        :selected="$settings['timezone']"
                        required
                        :placeholder="null"
                    />

                    <x-select
                        name="records_per_page"
                        label="Default Records Per Page"
                        :options="['10' => '10', '25' => '25', '50' => '50', '100' => '100']"
                        :selected="$settings['records_per_page']"
                        required
                        :placeholder="null"
                    />

                    <div class="mb-3">
                        <label class="form-label">Application Logo</label>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="border rounded p-2 bg-light">
                                <img src="{{ $logoUrl }}" alt="Logo" style="max-height: 48px; max-width: 160px;">
                            </div>
                            @if ($hasCustomLogo)
                                <span class="badge bg-success">Custom logo</span>
                            @else
                                <span class="badge bg-secondary">Default Skote logo</span>
                            @endif
                        </div>
                        <input
                            type="file"
                            name="logo"
                            id="logo"
                            class="form-control @error('logo') is-invalid @enderror"
                            accept="image/png,image/jpeg,image/svg+xml,image/webp"
                        >
                        <small class="text-muted">PNG, JPG, SVG, or WEBP. Max 2 MB.</small>
                        @error('logo')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    @if ($hasCustomLogo)
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="remove_logo" id="remove_logo" value="1">
                            <label class="form-check-label" for="remove_logo">Remove custom logo</label>
                        </div>
                    @endif
                </x-card>
            </div>

            <div class="col-xl-6">
                <x-card title="Email SMTP">
                    <x-select
                        name="mail_mailer"
                        label="Mailer"
                        :options="['smtp' => 'SMTP', 'log' => 'Log (development)', 'sendmail' => 'Sendmail']"
                        :selected="$settings['mail_mailer']"
                        required
                        :placeholder="null"
                    />

                    <div class="row">
                        <div class="col-md-8">
                            <x-input name="mail_host" label="SMTP Host" :value="$settings['mail_host']" placeholder="smtp.mailtrap.io" />
                        </div>
                        <div class="col-md-4">
                            <x-input name="mail_port" type="number" label="Port" :value="$settings['mail_port']" placeholder="587" />
                        </div>
                    </div>

                    <x-input name="mail_username" label="Username" :value="$settings['mail_username']" autocomplete="off" />

                    <x-input
                        name="mail_password"
                        type="password"
                        label="Password"
                        :placeholder="$hasMailPassword ? 'Leave blank to keep current password' : 'SMTP password'"
                        autocomplete="new-password"
                    />
                    <small class="text-muted d-block mb-3">Gmail requires an App Password. Use TLS on port 587, or SSL on port 465.</small>

                    <x-select
                        name="mail_encryption"
                        label="Encryption"
                        :options="['tls' => 'TLS', 'ssl' => 'SSL']"
                        :selected="$settings['mail_encryption']"
                        placeholder="None"
                    />

                    <x-input name="mail_from_address" type="email" label="From Address" :value="$settings['mail_from_address']" required />
                    <x-input name="mail_from_name" label="From Name" :value="$settings['mail_from_name']" required />
                </x-card>
            </div>
        </div>

        @can('settings.update')
            <div class="mb-4">
                <x-button type="submit" icon="bx bx-save">Save Settings</x-button>
            </div>
        @else
            <div class="alert alert-info">You can view settings, but you do not have permission to update them.</div>
        @endcan
    </form>

    @can('settings.update')
        <div class="row">
            <div class="col-xl-6 offset-xl-6">
                <x-card title="Send Test Email">
                    <p class="text-muted">Save SMTP settings first, then send a test message.</p>
                    <form method="POST" action="{{ route('settings.test-email') }}">
                        @csrf
                        <x-input name="test_email" type="email" label="Recipient" :value="old('test_email', auth()->user()->email)" required />
                        <x-button type="submit" variant="info" icon="bx bx-send">Send Test Email</x-button>
                    </form>
                </x-card>
            </div>
        </div>
    @endcan
@endsection
