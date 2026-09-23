@extends('layouts.master')

@section('title', 'Daftar Klinik')

@section('layout')
    <div class="account-pages my-5 pt-sm-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-7 col-xl-6">
                    <div class="card overflow-hidden">
                        <div class="bg-primary-subtle">
                            <div class="row">
                                <div class="col-8">
                                    <div class="text-primary p-4">
                                        <h5 class="text-primary">Daftarkan klinik Anda</h5>
                                        <p class="mb-0">Buat akun owner, lalu siapkan klinik melalui onboarding.</p>
                                    </div>
                                </div>
                                <div class="col-4 align-self-end">
                                    <img src="{{ theme('images/profile-img.png') }}" alt="" class="img-fluid">
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="auth-logo">
                                <a href="{{ route('login') }}" class="auth-logo-dark">
                                    <div class="avatar-md profile-user-wid mb-4">
                                        <span class="avatar-title rounded-circle bg-light">
                                            <img src="{{ app_logo('dark') }}" alt="" class="rounded-circle" height="34">
                                        </span>
                                    </div>
                                </a>
                            </div>
                            <div class="p-2">
                                <x-alert />

                                <form method="POST" action="{{ route('register.store') }}" novalidate>
                                    @csrf

                                    <h6 class="text-muted text-uppercase mb-3">Data klinik</h6>
                                    <x-input name="name" label="Nama Klinik" :value="old('name')" required />

                                    @if ($packages->isNotEmpty())
                                        <x-select
                                            name="package_id"
                                            label="Paket"
                                            :options="$packages->mapWithKeys(fn ($package) => [$package->id => $package->name.($package->isFree() ? ' (Gratis / Trial)' : '')])->all()"
                                            :selected="old('package_id', $defaultPackageId)"
                                            placeholder="Pilih paket"
                                        />
                                    @endif

                                    <h6 class="text-muted text-uppercase mb-3 mt-2">Akun owner</h6>
                                    <x-input name="owner_name" label="Nama Owner" :value="old('owner_name')" required />
                                    <x-input name="owner_email" type="email" label="Email" :value="old('owner_email')" required />
                                    <x-input name="password" type="password" label="Password" required autocomplete="new-password" />
                                    <x-input name="password_confirmation" type="password" label="Konfirmasi Password" required autocomplete="new-password" />

                                    <div class="mt-3 d-grid">
                                        <button class="btn btn-primary waves-effect waves-light" type="submit">Daftarkan Klinik</button>
                                    </div>

                                    <div class="mt-4 text-center">
                                        <p class="mb-0">Sudah punya akun? <a href="{{ route('login') }}" class="fw-medium text-primary">Masuk</a></p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 text-center">
                        <p>© {{ now()->year }} {{ $appName ?? config('app.name') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
