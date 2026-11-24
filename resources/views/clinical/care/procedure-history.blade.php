@extends('layouts.app')

@section('title', 'Riwayat tindakan')

@php
    $back = route('care.show', array_filter([
        'visit' => $visit,
        'tab' => 'tindakan',
        'from' => request('from') === 'pelayanan' ? 'pelayanan' : null,
    ]));
    $review = is_array($record?->follow_up_review) ? $record->follow_up_review : [];
    $procedureEvals = is_array($review['procedure_evals'] ?? null) ? $review['procedure_evals'] : [];
    $isFollowUp = $record?->assessment_kind === \App\Enums\AssessmentKind::FollowUp;
    $canEditEval = ($writable ?? false) && $isFollowUp && auth()->user()?->can('medical_record.update');
    $showQuery = array_filter(['from' => request('from') === 'pelayanan' ? 'pelayanan' : null]);
@endphp

@section('content')
    <x-page-header title="Riwayat tindakan" :breadcrumb="['Pelayanan' => route('examinations.initial'), ($visit->patient?->name ?: 'Kunjungan') => $back, 'Riwayat tindakan' => null]" />
    <x-alert />

    <x-card title="Riwayat tindakan pasien">
        @if ($canEditEval)
            <form method="POST" action="{{ route('care.follow-up.update', $visit) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="_return" value="procedure-history">
                @if (request('from') === 'pelayanan')
                    <input type="hidden" name="from" value="pelayanan">
                @endif
        @endif
        <x-table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Tindakan</th>
                    <th>Gigi</th>
                    <th>Qty</th>
                    <th>Dokter</th>
                    @if ($isFollowUp)
                        <th>Evaluasi</th>
                    @endif
                    <th class="text-end">Tampilkan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($history as $item)
                    @php($eval = $procedureEvals[$item->id] ?? [])
                    <tr>
                        <td class="text-nowrap">{{ $item->visit?->visit_date?->format('d M Y') ?: '—' }}</td>
                        <td>
                            {{ $item->procedure?->name ?: 'Tindakan' }}
                            @if ($item->icd9_code)
                                <div class="text-muted small">ICD-9 {{ $item->icd9_code }}</div>
                            @endif
                        </td>
                        <td>{{ $item->tooth_number ?: '—' }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ $item->visit?->doctor?->name ?: '—' }}</td>
                        @if ($isFollowUp)
                            <td>
                                @if ($canEditEval)
                                    <input class="form-control" name="procedure_evals[{{ $item->id }}][result]" value="{{ $eval['result'] ?? '' }}" placeholder="Evaluasi hasil tindakan" maxlength="1000">
                                @else
                                    {{ filled($eval['result'] ?? null) ? $eval['result'] : '—' }}
                                @endif
                            </td>
                        @endif
                        <td class="text-end">
                            <a class="btn btn-sm btn-soft-primary" href="{{ route('care.procedure.show', array_merge(['visit' => $visit, 'procedure_record' => $item], $showQuery)) }}">Tampilkan</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isFollowUp ? 7 : 6 }}" class="text-muted">Belum ada riwayat tindakan pada kunjungan sebelumnya.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($canEditEval && $history->isNotEmpty())
            <div class="text-end mt-3">
                <x-button type="submit" icon="bx bx-save">Simpan evaluasi</x-button>
            </div>
        @endif
        @if ($canEditEval)
            </form>
        @endif
        <div class="mt-3">
            <x-button href="{{ $back }}" variant="secondary" icon="bx bx-arrow-back">Kembali ke tindakan</x-button>
        </div>
    </x-card>
@endsection
