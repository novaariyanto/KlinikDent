@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Dashboard' => route('dashboard'), $title => null]" />
    <x-alert />

    @if (! empty($note))
        <div class="alert alert-info">{{ $note }}</div>
    @endif

    @if (! empty($cards))
        <div class="row">
            @foreach ($cards as $card)
                <div class="col-md-6 col-xl-3">
                    <div class="card mini-stats-wid">
                        <div class="card-body">
                            <p class="text-muted fw-medium">{{ $card['label'] }}</p>
                            <h4 class="mb-0">{{ $card['value'] }}</h4>
                            @if (! empty($card['href']))
                                <a href="{{ $card['href'] }}" class="small">Lihat</a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @if (! empty($links))
            <div class="mb-3">
                @foreach ($links as $link)
                    <x-button href="{{ $link['href'] }}" variant="{{ $loop->first ? 'primary' : 'secondary' }}">{{ $link['label'] }}</x-button>
                @endforeach
            </div>
        @endif
    @else
        <div class="row">
            <div class="col-xl-8">
                <x-module-placeholder
                    :icon="$icon"
                    :title="$title"
                    :description="$description"
                />
            </div>
            <div class="col-xl-4">
                <x-card title="Akun">
                    <p class="mb-1"><strong>Nama:</strong> {{ auth()->user()->name }}</p>
                    <p class="mb-1"><strong>Email:</strong> {{ auth()->user()->email }}</p>
                    <p class="mb-0">
                        <strong>Role:</strong>
                        {{ auth()->user()->roles->map(fn ($role) => \App\Enums\RoleName::tryFrom($role->name)?->label() ?? $role->name)->join(', ') ?: '-' }}
                    </p>
                </x-card>
            </div>
        </div>
    @endif
@endsection
