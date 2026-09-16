@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <x-page-header title="Settings" :breadcrumb="['System' => null, 'Settings' => route('settings.index')]" />

    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Application Settings">
                <form method="POST" action="{{ route('settings.update') }}" novalidate>
                    @csrf
                    @method('PUT')

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

                    <x-button type="submit" icon="bx bx-save">Save Settings</x-button>
                </form>
            </x-card>
        </div>
    </div>
@endsection
