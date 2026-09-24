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
                    <p class="text-white-50 mb-0">Monitor Antrean per Dokter</p>
                </div>
                <div class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-light mb-2" id="enable-voice">Aktifkan suara</button>
                    <div class="text-white-50" id="updated-at">{{ $payload['updated_at'] }}</div>
                </div>
            </div>
            <div class="row" id="lanes">
                @forelse ($payload['lanes'] as $lane)
                    <div class="col-lg-6 mb-3">
                        <div class="card bg-dark border-0 text-white">
                            <div class="card-body">
                                <p class="text-white-50 mb-1">{{ $lane['doctor'] }} @if ($lane['room']) · {{ $lane['room'] }} @endif</p>
                                <div class="display-4 fw-bold text-warning">{{ $lane['current']['number'] ?? '--' }}</div>
                                <div>{{ $lane['current']['patient'] ?? 'Menunggu panggilan' }}</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12"><p class="text-white-50">Belum ada antrean hari ini.</p></div>
                @endforelse
            </div>
            @if ($authenticated)
                <p class="text-white-50 mt-3 mb-0 small">URL layar: {{ $branch->monitorUrl() }}</p>
            @endif
        </div>
    </div>
@endsection

@push('styles')
<style>
    body.queue-monitor-page { background: #0b1324; }
    .queue-monitor { min-height: 100vh; }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        const feedUrl = @json($feedUrl);
        const words = ['nol','satu','dua','tiga','empat','lima','enam','tujuh','delapan','sembilan'];
        const spoken = new Set();
        let voiceOn = false;
        const initial = @json($payload);
        (initial.lanes || []).forEach(function (lane) {
            if (lane.current && lane.current.call_key) spoken.add(lane.current.call_key);
        });

        function spell(number) {
            return String(number).split('').map(function (digit) {
                return words[digit] || digit;
            }).join(' ');
        }

        function speak(lane) {
            if (!voiceOn || !lane.current || !window.speechSynthesis) return;
            const doctor = lane.current.doctor || lane.doctor || 'dokter';
            const text = 'Nomor antrean ' + spell(lane.current.number) + ', silakan ke ' + doctor;
            const utter = new SpeechSynthesisUtterance(text);
            utter.lang = 'id-ID';
            window.speechSynthesis.speak(utter);
        }

        function render(payload) {
            document.getElementById('updated-at').textContent = payload.updated_at;
            const wrap = document.getElementById('lanes');
            wrap.innerHTML = '';
            if (!payload.lanes || !payload.lanes.length) {
                wrap.innerHTML = '<div class="col-12"><p class="text-white-50">Belum ada antrean hari ini.</p></div>';
                return;
            }
            payload.lanes.forEach(function (lane) {
                if (lane.current && lane.current.call_key && !spoken.has(lane.current.call_key)) {
                    spoken.add(lane.current.call_key);
                    speak(lane);
                }
                const col = document.createElement('div');
                col.className = 'col-lg-6 mb-3';
                const current = lane.current || {};
                col.innerHTML = '<div class="card bg-dark border-0 text-white"><div class="card-body">'
                    + '<p class="text-white-50 mb-1">' + lane.doctor + (lane.room ? ' · ' + lane.room : '') + '</p>'
                    + '<div class="display-4 fw-bold text-warning">' + (current.number || '--') + '</div>'
                    + '<div>' + (current.patient || 'Menunggu panggilan') + '</div>'
                    + '</div></div>';
                wrap.appendChild(col);
            });
        }

        document.getElementById('enable-voice').addEventListener('click', function () {
            voiceOn = true;
            this.textContent = 'Suara aktif';
        });

        setInterval(function () {
            fetch(feedUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(render)
                .catch(function () {});
        }, 5000);
    })();
</script>
@endpush
