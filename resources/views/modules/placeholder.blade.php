@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="$breadcrumb" />

    <div class="row">
        <div class="col-lg-8">
            <x-module-placeholder :icon="$icon" :title="$title" :description="$description" />
        </div>
        <div class="col-lg-4">
            <x-card title="Informasi">
                <p class="text-muted mb-2">
                    Halaman ini adalah placeholder arsitektur. Fitur bisnis belum diaktifkan.
                </p>
                <p class="mb-0">
                    <span class="badge badge-soft-info">Coming Soon</span>
                    Modul ini akan dikembangkan pada tahap berikutnya.
                </p>
            </x-card>
        </div>
    </div>
@endsection
