@extends('layouts.master')

@section('body_attributes')
    data-sidebar="dark" data-layout-mode="light"
@endsection

@section('layout')
    <div id="layout-wrapper">
        @include('layouts.navbar')
        @include('layouts.sidebar')

        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">
                    @yield('content')
                </div>
            </div>

            @include('layouts.footer')
        </div>
    </div>

    <div class="rightbar-overlay"></div>
@endsection
