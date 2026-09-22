<div class="hero-actions">
    <a class="btn btn--primary" href="{{ route('register') }}" data-track="{{ $primaryTrack ?? 'register_clicked' }}">{{ $primary ?? 'Coba '.$appName }}</a>
    <a class="btn {{ $secondaryClass ?? 'btn--ghost' }}" href="{{ $secondaryHref ?? route('home').'#demo' }}" data-track="{{ $secondaryTrack ?? 'demo_clicked' }}">{{ $secondary ?? 'Lihat Demo' }}</a>
</div>
