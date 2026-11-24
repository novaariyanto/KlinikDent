<section class="cta-band final">
    <div class="wrap">
        <h2 class="section-title">{{ $title ?? 'Siapkan sistem klinik gigi Anda dari sekarang.' }}</h2>
        <p class="section-lede">{{ $lede ?? 'Mulai dengan pasien pertama. Bangun workflow yang rapi. Kembangkan klinik dengan sistem yang siap mengikuti pertumbuhan.' }}</p>
        @include('marketing.partials.cta-pair', [
            'secondaryClass' => $secondaryClass ?? 'btn--ghost',
            'primaryTrack' => $primaryTrack ?? 'register_clicked',
            'secondaryTrack' => $secondaryTrack ?? 'demo_clicked',
        ])
    </div>
</section>
