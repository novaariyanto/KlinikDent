@extends('layouts.master')

@section('title', 'Monitor Antrean '.$branch->name)

@section('body_attributes')
    class="queue-monitor-page"
@endsection

@section('layout')
    <div class="queue-monitor">
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0 text-white">{{ $branch->name }}</h2>
                    <p class="text-white-50 mb-0">Monitor Antrean</p>
                </div>
                <div class="text-white-50" id="updated-at">{{ $payload['updated_at'] }}</div>
            </div>
            <div class="row">
                <div class="col-lg-7">
                    <div class="card bg-dark border-0 text-center py-5">
                        <div class="card-body">
                            <p class="text-uppercase text-white-50 mb-2">Sedang dipanggil</p>
                            <div id="current-number" class="display-1 fw-bold text-warning">{{ $payload['current']['number'] ?? '--' }}</div>
                            <h3 id="current-patient" class="text-white mt-3">{{ $payload['current']['patient'] ?? 'Menunggu panggilan berikutnya' }}</h3>
                            <p id="current-room" class="text-white-50 mb-0">{{ $payload['current']['room'] ?? '' }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Menunggu</h5></div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush" id="waiting-list">
                                @forelse ($payload['waiting'] as $item)
                                    <li class="list-group-item d-flex justify-content-between">
                                        <strong>{{ $item['number'] }}</strong>
                                        <span>{{ $item['patient'] }}</span>
                                    </li>
                                @empty
                                    <li class="list-group-item text-muted">Tidak ada antrean menunggu.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                    @if ($authenticated)
                        <p class="text-white-50 mt-3 mb-0 small">URL layar: {{ $branch->monitorUrl() }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    body.queue-monitor-page { background: #0b1324; }
    .queue-monitor { min-height: 100vh; }
    #current-number { letter-spacing: .12em; }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        const feedUrl = @json($feedUrl);
        function render(payload) {
            document.getElementById('updated-at').textContent = payload.updated_at;
            document.getElementById('current-number').textContent = payload.current ? payload.current.number : '--';
            document.getElementById('current-patient').textContent = payload.current ? payload.current.patient : 'Menunggu panggilan berikutnya';
            document.getElementById('current-room').textContent = payload.current && payload.current.room ? payload.current.room : '';
            const list = document.getElementById('waiting-list');
            list.innerHTML = '';
            if (!payload.waiting.length) {
                list.innerHTML = '<li class="list-group-item text-muted">Tidak ada antrean menunggu.</li>';
                return;
            }
            payload.waiting.forEach(function (item) {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between';
                li.innerHTML = '<strong>' + item.number + '</strong><span>' + item.patient + '</span>';
                list.appendChild(li);
            });
        }
        setInterval(function () {
            fetch(feedUrl, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(render)
                .catch(() => {});
        }, 5000);
    })();
</script>
@endpush
