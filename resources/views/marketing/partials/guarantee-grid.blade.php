@php
    $guarantees = $guarantees ?? [];
@endphp
<section class="section section--soft" id="{{ $sectionId ?? 'jaminan' }}">
    <div class="wrap">
        <div class="section-head section-head--center">
            <h2 class="section-title">{{ $title ?? 'Jaminan bersama '.$appName }}</h2>
            <p class="section-lede">{{ $lede ?? 'Integrasi dan kontrol akses yang dikonfigurasi sesuai kebutuhan klinik Anda.' }}</p>
        </div>
        <div class="guarantee-grid">
            @foreach ($guarantees as $item)
                <article class="guarantee-card">
                    <h3>{{ $item['title'] }}</h3>
                    <p>{{ $item['body'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
