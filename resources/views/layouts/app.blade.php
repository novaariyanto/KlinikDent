@extends('layouts.master')

@section('body_attributes')
    data-sidebar="dark" data-layout-mode="light" @if (request()->routeIs('care.show')) class="care-mode" @endif
@endsection

@section('layout')
    <div id="layout-wrapper">
        @include('layouts.navbar')
        @include('layouts.sidebar')

        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">
                    @if (is_impersonating())
                        <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
                            <div>
                                <i class="bx bx-user-check me-1"></i>
                                You are impersonating <strong>{{ auth()->user()->name }}</strong>
                                <span class="text-muted">({{ auth()->user()->email }})</span>
                            </div>
                            <form action="{{ route('impersonate.leave') }}" method="POST" class="mb-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-dark">
                                    Return to original account
                                </button>
                            </form>
                        </div>
                    @endif
                    @yield('content')
                </div>
            </div>

            @include('layouts.footer')
        </div>
    </div>

    <div class="rightbar-overlay"></div>
@endsection
