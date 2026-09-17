@extends('layouts.app')

@section('title', 'Master Data Klinik')

@section('content')
    <x-page-header title="Master Data Klinik" :breadcrumb="['Klinik' => null, 'Master Data' => route('management.clinic')]" />

    <x-alert />

    <div class="row">
        @forelse ($modules as $module)
            <div class="col-md-4 col-xl-3 mb-4">
                <a href="{{ route($module['route']) }}" class="text-reset">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="avatar-sm me-3">
                                    <span class="avatar-title rounded-circle bg-primary-subtle text-primary font-size-18">
                                        <i class="{{ $module['icon'] }}"></i>
                                    </span>
                                </div>
                                <h5 class="mb-0">{{ $module['title'] }}</h5>
                            </div>
                            <p class="text-muted mb-0">{{ $module['description'] }}</p>
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12">
                <x-card title="Master Data">
                    <p class="text-muted mb-0">Tidak ada modul master data yang dapat diakses.</p>
                </x-card>
            </div>
        @endforelse
    </div>
@endsection
