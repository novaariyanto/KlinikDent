@extends('layouts.master')

@section('title', $title ?? 'Setup Klinik')

@section('body_attributes')
    class="onboarding-page"
@endsection

@push('styles')
<style>
    .onboarding-page { background: #f6f8fb; }
    .onboarding-shell { min-height: 100vh; }
    .onboarding-topbar {
        background: #fff;
        border-bottom: 1px solid #e9ecef;
        padding: 0.85rem 1.25rem;
    }
    .onboarding-progress { height: 8px; border-radius: 999px; }
    .onboarding-card-choice {
        border: 2px solid #e9ecef;
        border-radius: 1rem;
        transition: border-color .15s ease, box-shadow .15s ease;
        height: 100%;
    }
    .onboarding-card-choice:hover {
        border-color: #556ee6;
        box-shadow: 0 0.5rem 1.25rem rgba(85,110,230,.08);
    }
    .onboarding-icon {
        width: 56px; height: 56px;
        border-radius: 16px;
        display: inline-flex; align-items: center; justify-content: center;
        background: #eef2ff; color: #556ee6; font-size: 1.6rem;
    }
    .onboarding-step { max-width: 920px; margin: 0 auto; }
</style>
@endpush

@section('layout')
    <div class="onboarding-shell d-flex flex-column">
        <div class="onboarding-topbar d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <img src="{{ app_logo('dark') }}" alt="{{ $appName ?? config('app.name') }}" height="28">
                <span class="fw-semibold">{{ $appName ?? config('app.name') }}</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" class="mb-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Keluar</button>
                </form>
            </div>
        </div>
        <div class="flex-grow-1 p-3 p-md-4">
            @yield('content')
        </div>
    </div>
@endsection
