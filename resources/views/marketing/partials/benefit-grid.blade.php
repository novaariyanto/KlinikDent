@php
    $benefits = $benefits ?? [];
@endphp
<section class="section section--soft" id="{{ $sectionId ?? 'keuntungan' }}">
    <div class="wrap">
        <div class="section-head section-head--center">
            <h2 class="section-title">{{ $title ?? 'Keuntungan memakai '.$appName }}</h2>
            <p class="section-lede">{{ $lede ?? 'Dirancang untuk alur kerja klinik gigi — dari pasien pertama hingga operasional multi-cabang.' }}</p>
        </div>
        <div class="benefit-grid {{ count($benefits) >= 5 ? 'benefit-grid--five' : '' }}">
            @foreach ($benefits as $index => $benefit)
                <article class="benefit-card">
                    <span class="benefit-card__icon" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <h3>{{ $benefit['title'] }}</h3>
                    <p>{{ $benefit['body'] }}</p>
                    <ul>
                        @foreach ($benefit['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </div>
</section>
