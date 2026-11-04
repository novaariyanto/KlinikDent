@php
    $id = 'soap-'.$visit->id;
    $record = $visit->medicalRecord;
    $subjective = $soap->subjective();
    $vitals = $soap->vitals();
    $objective = $soap->objective();
    $assessment = $soap->assessment();
    $plan = $soap->plan();
    $doctorNotes = $soap->doctorNotes();
    $prescriptions = $visit->prescriptions;
    $sections = [
        'S' => ['title' => 'Subjective — anamnesis', 'filled' => $subjective !== []],
        'O' => ['title' => 'Objective — pemeriksaan', 'filled' => $vitals !== [] || $objective !== [] || $odoChanges !== []],
        'A' => ['title' => 'Assessment — diagnosis', 'filled' => $assessment !== [] || $doctorNotes],
        'P' => ['title' => 'Plan — rencana & terapi', 'filled' => $plan !== [] || $visit->procedureRecords->isNotEmpty() || $prescriptions->isNotEmpty()],
    ];
    $rowsBlock = function (array $rows) {
        return collect($rows)->map(fn ($row) => '<dt>'.e($row['label']).'</dt><dd>'.nl2br(e($row['value'])).'</dd>')->implode('');
    };
@endphp

<div class="ph-detail">
    <div class="ph-detail__bar">
        <span class="text-muted small">
            @if ($record?->finalized_at)
                Rekam medis difinalisasi {{ $record->finalized_at->format('d M Y H:i') }}{{ $record->finalizer ? ' oleh '.$record->finalizer->name : '' }}.
            @elseif ($record)
                Rekam medis belum difinalisasi.
            @else
                Belum ada rekam medis untuk kunjungan ini.
            @endif
        </span>
        <span class="d-flex gap-2 flex-wrap">
            @can('view', $visit)
                <a href="{{ route('care.show', $visit) }}" class="btn btn-sm btn-outline-primary"><i class="bx bx-plus-medical me-1"></i>Buka Pelayanan</a>
            @endcan
            @if ($record && auth()->user()->can('medical_record.view') && auth()->user()->can('examination.view'))
                <a href="{{ route('care.resume.pdf', $visit) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="bx bx-printer me-1"></i>Resume kunjungan</a>
            @endif
        </span>
    </div>

    <div class="accordion ph-soap" id="{{ $id }}">
        @foreach ($sections as $key => $section)
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $id }}-{{ $key }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                        <span class="ph-soap__key">{{ $key }}</span> {{ $section['title'] }}
                        @unless ($section['filled'])
                            <span class="ms-2 text-muted small">tidak dicatat</span>
                        @endunless
                    </button>
                </h2>
                <div id="{{ $id }}-{{ $key }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}">
                    <div class="accordion-body">
                        @if ($key === 'S')
                            @if ($subjective)
                                <dl class="ph-dl">{!! $rowsBlock($subjective) !!}</dl>
                            @else
                                <p class="text-muted mb-0">Tidak ada anamnesis yang dicatat pada kunjungan ini.</p>
                            @endif
                        @elseif ($key === 'O')
                            @if ($vitals)
                                <div class="ph-vitals">
                                    @foreach ($vitals as $label => $value)
                                        <div><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
                                    @endforeach
                                </div>
                            @endif
                            @if ($objective)
                                <dl class="ph-dl">{!! $rowsBlock($objective) !!}</dl>
                            @endif
                            <div class="ph-subhead">Odontogram kunjungan ini</div>
                            @if ($odoChanges)
                                <ul class="ph-odo-changes">
                                    @foreach ($odoChanges as $number => $change)
                                        <li>
                                            <strong>{{ $number }}</strong>
                                            <span class="text-muted">{{ $change['before'] ? \App\Support\Clinical\ToothConditions::summary($change['before']) : 'Sehat / belum dicatat' }}</span>
                                            <i class="bx bx-right-arrow-alt"></i>
                                            <span>{{ $change['after'] ? \App\Support\Clinical\ToothConditions::summary($change['after']) : 'Sehat / kondisi dihapus' }}</span>
                                            @if ($change['approximate'])
                                                <span class="badge bg-light text-muted border" title="Data lama hanya menyimpan kode kondisi utama">perkiraan</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-muted small">Tidak ada perubahan odontogram yang tercatat pada kunjungan ini.</p>
                            @endif
                            @if ($odoState)
                                <details class="ph-odo-details">
                                    <summary>Lihat odontogram setelah kunjungan ini</summary>
                                    @include('clinical.history.partials.odontogram-chart', ['state' => $odoState])
                                </details>
                            @endif
                        @elseif ($key === 'A')
                            @forelse ($assessment as $group)
                                <div class="ph-subhead">{{ $group['label'] }}</div>
                                <ul class="ph-dx">
                                    @foreach ($group['items'] as $diagnosis)
                                        <li>
                                            @if ($diagnosis->rank === \App\Enums\DiagnosisRank::Primary)
                                                <span class="badge bg-primary">Utama</span>
                                            @else
                                                <span class="badge bg-light text-dark border">Sekunder</span>
                                            @endif
                                            <span class="ph-dx__code">{{ $diagnosis->code ?: 'Tanpa kode' }}</span>
                                            <span class="{{ $diagnosis->rank === \App\Enums\DiagnosisRank::Primary ? 'fw-semibold' : '' }}">{{ $diagnosis->description }}</span>
                                            @if ($diagnosis->tooth_number)
                                                <span class="text-muted">· gigi {{ $diagnosis->tooth_number }}</span>
                                            @endif
                                            @if (filled($diagnosis->clinical_note))
                                                <div class="text-muted small">{{ $diagnosis->clinical_note }}</div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @empty
                                <p class="text-muted">Tidak ada diagnosis yang dicatat.</p>
                            @endforelse
                            @if ($doctorNotes)
                                <div class="ph-subhead">Catatan dokter</div>
                                <p class="mb-0">{!! nl2br(e($doctorNotes)) !!}</p>
                            @endif
                        @else
                            @if ($visit->procedureRecords->isNotEmpty())
                                <div class="ph-subhead">Tindakan dilakukan</div>
                                <table class="table table-sm align-middle mb-3">
                                    <thead><tr><th>Tindakan</th><th>Gigi</th><th>ICD-9</th><th class="text-end">Jumlah</th></tr></thead>
                                    <tbody>
                                        @foreach ($visit->procedureRecords as $procedure)
                                            <tr>
                                                <td>{{ $procedure->procedure?->name ?: 'Tindakan' }}</td>
                                                <td>{{ $procedure->tooth_number ?: '-' }}</td>
                                                <td>{{ $procedure->icd9_code ?: '-' }}</td>
                                                <td class="text-end">{{ $procedure->quantity }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                            @if ($plan)
                                <dl class="ph-dl">{!! $rowsBlock($plan) !!}</dl>
                            @endif
                            @foreach ($prescriptions as $prescription)
                                <div class="ph-subhead d-flex justify-content-between align-items-center">
                                    <span>Resep #{{ $prescription->id }} · {{ $prescription->doctor?->name ?: '-' }} <span class="{{ $prescription->status->badgeClass() }}">{{ $prescription->status->label() }}</span></span>
                                    @can('prescription.view')
                                        <a href="{{ route('patients.history.prescription.print', [$patient, $prescription]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="bx bx-printer"></i> Cetak</a>
                                    @endcan
                                </div>
                                <ul class="ph-rx">
                                    @foreach ($prescription->items as $item)
                                        <li>
                                            <span class="fw-semibold">{{ $item->label() }}</span>
                                            <span class="text-muted">{{ collect([$item->dosage, $item->frequency, $item->duration])->filter()->implode(' · ') ?: 'Aturan belum dicatat' }}</span>
                                            @if ($item->quantity) <span>× {{ $item->quantity }}</span>@endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endforeach
                            @if (! $sections['P']['filled'])
                                <p class="text-muted mb-0">Tidak ada rencana, tindakan, atau resep yang dicatat.</p>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($record && $record->amendments->isNotEmpty())
        <div class="ph-subhead mt-3">Adendum rekam medis</div>
        <ul class="ph-rx">
            @foreach ($record->amendments as $amendment)
                <li><span class="text-muted">{{ $amendment->created_at?->format('d M Y H:i') }}</span> {{ $amendment->body }}</li>
            @endforeach
        </ul>
    @endif
</div>
