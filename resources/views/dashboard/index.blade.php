@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Dashboard' => route('dashboard'), $title => null]" />
    <x-alert />

    @if (session('onboarding_just_completed'))
        <div class="alert alert-success">Klinik Anda siap digunakan.</div>
    @endif

    @if (! empty($setupChecklist ?? null))
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Setup Klinik</h5>
                    <span class="fw-semibold">{{ $setupChecklist['percent'] }}% selesai</span>
                </div>
                <div class="progress mb-3" style="height: 8px;">
                    <div class="progress-bar bg-primary" style="width: {{ $setupChecklist['percent'] }}%"></div>
                </div>
                <div class="row">
                    @foreach ($setupChecklist['items'] as $item)
                        <div class="col-md-6">
                            <p class="mb-2">
                                @if ($item['done'])
                                    <i class="bx bx-check-circle text-success"></i>
                                @else
                                    <i class="bx bx-circle text-muted"></i>
                                @endif
                                @if ($item['done'])
                                    {{ $item['label'] }}
                                @else
                                    <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                                @endif
                                @if (! empty($item['optional']))
                                    <span class="badge bg-light text-muted">Opsional</span>
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
                @php $next = collect($setupChecklist['items'])->first(fn ($item) => ! $item['done'] && empty($item['optional'])); @endphp
                @if ($next)
                    <x-button href="{{ $next['href'] }}" variant="primary">Lanjutkan Setup</x-button>
                @endif
            </div>
        </div>
    @endif

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
