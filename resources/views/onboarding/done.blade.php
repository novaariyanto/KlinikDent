@extends('layouts.onboarding')

@section('title', 'Klinik siap digunakan')

@section('content')
    <div class="onboarding-step text-center py-5">
        <div class="onboarding-icon mx-auto mb-3"><i class="bx bx-check"></i></div>
        <h3 class="mb-2">Klinik Anda siap digunakan.</h3>
        <p class="text-muted mb-4">Setup awal {{ $tenant->name }} sudah selesai. Anda dapat menambah dokter, staf, dan layanan kapan saja.</p>
        <x-button href="{{ route('dashboard') }}" icon="bx bx-right-arrow-alt">Mulai Menggunakan KlinikDent</x-button>
    </div>
@endsection
