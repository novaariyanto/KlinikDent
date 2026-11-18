@php
    $trustLogos = $trustLogos ?? \App\Support\Marketing\MarketingCatalog::trustLogos();
@endphp
<section class="band on-sky-wash" aria-label="Kesiapan dan ringkasan produk">
    <div class="wrap">
        <p class="trust-label">{{ $trustLabel ?? 'Terdaftar & terintegrasi' }}</p>
        <div class="regs">
            @foreach ($trustLogos as $logo)
                @php
                    $webp = public_path('image/'.$logo['base'].'.webp');
                    $png = public_path('image/'.$logo['base'].'.web.png');
                    $hasWebp = is_file($webp);
                    $hasPng = is_file($png);
                @endphp
                <figure class="reg">
                    <a class="reg-logo" href="{{ $logo['href'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $logo['aria'] }}">
                        @if ($hasWebp || $hasPng)
                            <picture>
                                @if ($hasWebp)
                                    <source type="image/webp" srcset="{{ asset('image/'.$logo['base'].'.webp') }}">
                                @endif
                                <img
                                    src="{{ asset('image/'.$logo['base'].($hasPng ? '.web.png' : '.webp')) }}"
                                    alt="{{ $logo['alt'] }}"
                                    width="{{ $logo['width'] }}"
                                    height="{{ $logo['height'] }}"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </picture>
                        @else
                            <span class="reg-logo-fallback">{{ $logo['fallback'] }}</span>
                        @endif
                    </a>
                    <figcaption><b>{{ $logo['title'] }}</b><span class="reg-num">{{ $logo['caption'] }}</span></figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
