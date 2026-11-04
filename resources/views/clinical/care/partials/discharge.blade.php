@php
    $summary = \App\Support\Clinical\DischargeSummary::for($visit);
    $groups = $summary['groups'];
    $vitalItems = $summary['vitalItems'];
    $dischargeMode = $summary['dischargeMode'];
@endphp

<section class="care-section" id="ringkasan">
    <div class="care-section__head d-flex justify-content-between align-items-start gap-2">
        <div>
            <h5 class="care-section__title">Ringkasan Pasien Pulang / Rencana Pulang</h5>
            <p class="care-section__hint">No. registrasi {{ $summary['registrationNumber'] }} · {{ $summary['registrationDate'] }}</p>
        </div>
        @can('medical_record.view')
            <a href="{{ route('care.discharge.pdf', $visit) }}" class="btn btn-sm btn-soft-secondary" target="_blank">
                <i class="bx bx-printer me-1"></i>Cetak resume pulang
            </a>
        @endcan
    </div>
    <div class="care-section__body">
        <div class="soap-sheet-wrap mb-3">
            <table class="soap-sheet discharge-sheet">
                <tbody>
                    @foreach ($groups as $group => $items)
                        @foreach ($items as $label => $value)
                            <tr>
                                @if ($loop->first)
                                    <th class="soap-sheet__group" rowspan="{{ count($items) }}">{{ $group }}</th>
                                @endif
                                <th class="soap-sheet__label">{{ $label }}</th>
                                <td class="soap-sheet__value">
                                    <span class="soap-sheet__colon">:</span>
                                    <div class="soap-sheet__fill">
                                        @if ($value === 'vitals')
                                            @if ($vitalItems === [])
                                                <span class="text-muted">—</span>
                                            @else
                                                <div class="discharge-vitals">
                                                    @foreach ($vitalItems as $vitalLabel => $vitalValue)
                                                        <div class="discharge-vitals__item">
                                                            <span class="discharge-vitals__label">{{ $vitalLabel }}</span>
                                                            <span>{{ $vitalValue }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @elseif ($value === 'mode')
                                            <span class="discharge-sheet__mode">{{ $dischargeMode }}</span>
                                        @elseif ($value === [])
                                            <span class="text-muted">—</span>
                                        @elseif (count($value) === 1)
                                            {{ $value[0] }}
                                        @else
                                            <ul class="discharge-sheet__list">
                                                @foreach ($value as $line)
                                                    <li>{{ $line }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="discharge-stack">
            @include('clinical.care.partials.plan-amendments')
        </div>
    </div>
</section>
