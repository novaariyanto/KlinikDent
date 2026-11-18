@extends('layouts.app')
@section('title', 'SEO Settings')
@section('content')
<x-page-header title="SEO Settings" :breadcrumb="['CMS' => route('cms.dashboard'), 'SEO Settings' => null]" />
<x-alert />
<x-card title="Pengaturan global">
    <form method="POST" action="{{ route('cms.seo-settings.update') }}">@csrf @method('PUT')
        <div class="row g-3">
            @foreach ([
                'site_name' => 'Site name',
                'default_seo_title' => 'Default SEO title',
                'default_meta_description' => 'Default meta description',
                'default_og_image' => 'Default OG image path',
                'canonical_base' => 'Canonical base URL',
                'google_verification' => 'Google verification',
                'bing_verification' => 'Bing verification',
                'ga4_id' => 'GA4 Measurement ID',
                'gtm_id' => 'GTM Container ID',
                'organization_name' => 'Organization name',
            ] as $key => $label)
                <div class="col-md-6">
                    <label class="form-label">{{ $label }}</label>
                    <input class="form-control" name="{{ $key }}" value="{{ old($key, $settings[$key] ?? '') }}">
                </div>
            @endforeach
            <div class="col-12">
                <label class="form-label">Site description</label>
                <textarea class="form-control" name="site_description" rows="3">{{ old('site_description', $settings['site_description'] ?? '') }}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label">Organization description</label>
                <textarea class="form-control" name="organization_description" rows="3">{{ old('organization_description', $settings['organization_description'] ?? '') }}</textarea>
            </div>
        </div>
        <button class="btn btn-primary mt-3" type="submit">Simpan</button>
    </form>
</x-card>
@endsection
