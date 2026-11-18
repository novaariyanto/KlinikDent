@php
    $startSteps = $startSteps ?? [];
@endphp
<section class="section" id="{{ $sectionId ?? 'mulai' }}">
    <div class="wrap">
        <div class="section-head section-head--center">
            <h2 class="section-title">{{ $title ?? 'Mulai memakai '.$appName.' dalam 4 langkah' }}</h2>
            <p class="section-lede">{{ $lede ?? 'Dari pendaftaran hingga kunjungan pertama tanpa berpindah-pindah sistem.' }}</p>
        </div>
        <ol class="steps steps--four">
            @foreach ($startSteps as $step)
                <li>
                    <strong>{{ $step['name'] }}</strong>
                    <p>{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
        <div class="mid-cta" style="justify-content:center">
            @include('marketing.partials.cta-pair')
        </div>
    </div>
</section>
