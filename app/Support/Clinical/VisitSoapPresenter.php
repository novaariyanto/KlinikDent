<?php

namespace App\Support\Clinical;

use App\Enums\DiagnosisKind;
use App\Enums\DiagnosisRank;
use App\Models\Diagnosis;
use App\Models\Visit;
use Illuminate\Support\Collection;

/**
 * Struktur SOAP baca-saja untuk satu kunjungan. Baris tanpa data tidak dikembalikan.
 */
final class VisitSoapPresenter
{
    public function __construct(public Visit $visit) {}

    /**
     * @return list<array{label: string, value: string}>
     */
    public function subjective(): array
    {
        $record = $this->visit->medicalRecord;
        $history = $this->history();
        $systemic = CareExamOptions::systemic();
        $categories = CareExamOptions::allergyCategories();
        $severities = CareExamOptions::allergySeverities();
        $clinical = CareExamOptions::allergyClinicalStatuses();

        $conditions = collect((array) ($history['conditions'] ?? []))
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->map(fn ($key) => $key === 'other' && filled($history['other_detail'] ?? null) ? 'Lainnya: '.$history['other_detail'] : ($systemic[$key] ?? $key));

        $pastIllness = $conditions->isNotEmpty()
            ? $conditions->implode(', ')
            : (filter_var($history['none'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'Tidak ada penyakit sistemik' : null);

        $allergy = match ((string) ($history['allergy_status'] ?? 'not_asked')) {
            'present' => collect([
                'Ada',
                $categories[$history['allergy_category'] ?? ''] ?? null,
                $history['allergy_detail'] ?? null,
                filled($history['allergy_reaction'] ?? null) ? 'reaksi: '.$history['allergy_reaction'] : null,
                $severities[$history['allergy_severity'] ?? ''] ?? null,
                $clinical[$history['allergy_clinical_status'] ?? ''] ?? null,
                $history['allergy_note'] ?? null,
            ])->filter(fn ($v) => filled($v))->implode(' · '),
            'none_confirmed' => 'Tidak ada alergi yang diketahui',
            'unknown' => 'Tidak diketahui',
            default => null,
        };

        $medication = match ((string) ($history['medication_status'] ?? 'not_asked')) {
            'present' => 'Sedang mengonsumsi: '.($history['medication_detail'] ?? '-'),
            'none_confirmed' => 'Tidak sedang mengonsumsi obat rutin',
            default => null,
        };

        return $this->rows([
            'Keluhan utama' => $record?->chief_complaint,
            'Riwayat keluhan sekarang / anamnesis' => $record?->anamnesis,
            'Riwayat penyakit dahulu (sistemik)' => $pastIllness,
            'Riwayat alergi' => $allergy,
            'Riwayat pengobatan' => $medication,
            'Riwayat penyakit keluarga' => $history['family_history'] ?? null,
            'Riwayat perawatan gigi' => $history['dental_history'] ?? null,
            'Alergi obat' => $history['drug_allergy'] ?? null,
            'Alergi bahan' => $history['material_allergy'] ?? null,
            'Golongan darah' => filled($history['blood_type'] ?? null)
                ? $history['blood_type'].match ($history['rhesus'] ?? null) { 'positive' => ' Rh+', 'negative' => ' Rh−', default => '' }
                : null,
            'Status kehamilan' => match ($history['pregnancy_status'] ?? null) {
                'pregnant' => 'Hamil',
                'not_pregnant' => 'Tidak hamil',
                default => null,
            },
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function vitals(): array
    {
        $stored = is_array($this->visit->medicalRecord?->vital_signs) ? $this->visit->medicalRecord->vital_signs : [];
        $history = $this->history();
        $sys = $stored['blood_pressure_sys'] ?? $history['blood_pressure_sys'] ?? null;
        $dia = $stored['blood_pressure_dia'] ?? $history['blood_pressure_dia'] ?? null;
        $bp = filled($sys) || filled($dia) ? trim(($sys ?: '-').'/'.($dia ?: '-')).' mmHg' : ($stored['blood_pressure'] ?? null);

        return array_filter([
            'Tekanan darah' => $bp,
            'Nadi' => filled($stored['pulse'] ?? null) ? $stored['pulse'].' x/menit' : null,
            'Respirasi' => filled($stored['respiration'] ?? null) ? $stored['respiration'].' x/menit' : null,
            'Suhu' => filled($stored['temperature'] ?? null) ? $stored['temperature'].' °C' : null,
            'Berat badan' => filled($stored['weight'] ?? null) ? $stored['weight'].' kg' : null,
            'Tinggi badan' => filled($stored['height'] ?? null) ? $stored['height'].' cm' : null,
            'Keadaan umum' => $stored['general_condition'] ?? null,
            'Kesadaran' => $stored['consciousness'] ?? null,
        ], fn ($v) => filled($v));
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function objective(): array
    {
        $record = $this->visit->medicalRecord;
        $exam = is_array($record?->dental_exam) ? $record->dental_exam : [];
        $finding = CareExamOptions::finding();
        $presence = CareExamOptions::presence();
        $occlusion = ($exam['occlusion'] ?? null) === 'deep' ? 'steep' : ($exam['occlusion'] ?? null);
        $ohis = is_array($exam['ohis'] ?? null) ? $exam['ohis'] : [];

        $clinical = collect([
            'Oklusi' => CareExamOptions::occlusion()[$occlusion ?? ''] ?? null,
            'Gingiva' => CareExamOptions::gingiva()[$exam['gingiva'] ?? ''] ?? null,
            'Mukosa' => CareExamOptions::mucosa()[$exam['mucosa'] ?? ''] ?? null,
            'Palatum' => CareExamOptions::palate()[$exam['palate'] ?? ''] ?? null,
            'Torus palatinus' => CareExamOptions::torus()[$exam['torus_palatinus'] ?? ''] ?? null,
            'Torus mandibularis' => CareExamOptions::torusMandibularis()[$exam['torus_mandibularis'] ?? ''] ?? null,
            'Diastema' => $this->withNote($presence[$exam['diastema'] ?? ''] ?? null, $exam['diastema_elements'] ?? null),
            'Supernumerary' => $this->withNote($presence[$exam['supernumerary'] ?? ''] ?? null, $exam['supernumerary_teeth'] ?? null),
            'Anomali' => $this->withNote($presence[$exam['anomaly'] ?? ''] ?? null, $exam['anomaly_description'] ?? null),
            'Kebiasaan' => $exam['habits'] ?? null,
            'OHI-S' => is_numeric($ohis['debris_index'] ?? null) && is_numeric($ohis['calculus_index'] ?? null)
                ? 'DI '.$ohis['debris_index'].' · CI '.$ohis['calculus_index'].' · total '.($ohis['total'] ?? '-').(filled($ohis['interpretation'] ?? null) ? ' ('.$ohis['interpretation'].')' : '')
                : null,
        ])->filter(fn ($v) => filled($v))->map(fn ($v, $k) => $k.': '.$v)->implode("\n");

        $labs = $this->visit->relationLoaded('labOrders') ? $this->visit->getRelation('labOrders') : collect();
        $supporting = $labs->map(fn ($order) => trim($order->work_type.(filled($order->tooth_numbers) ? ' (gigi '.$order->tooth_numbers.')' : '').(filled($order->notes) ? ' — '.$order->notes : '')))
            ->filter()
            ->implode("\n");

        return $this->rows([
            'Pemeriksaan awal' => $record?->initial_examination,
            'Pemeriksaan ekstraoral' => $this->withNote($finding[$exam['extraoral'] ?? ''] ?? null, $exam['extraoral_note'] ?? null),
            'Wajah' => $exam['face'] ?? null,
            'Pembengkakan' => $exam['swelling'] ?? null,
            'Kelenjar limfe' => $exam['lymph'] ?? null,
            'Sendi temporomandibular' => $exam['tmj'] ?? null,
            'Pemeriksaan intraoral' => $this->withNote($finding[$exam['intraoral'] ?? ''] ?? null, $exam['intraoral_note'] ?? null),
            'Lidah' => $exam['tongue'] ?? null,
            'Dasar mulut' => $exam['mouth_floor'] ?? null,
            'Saliva' => $exam['saliva'] ?? null,
            'Periodontal' => $exam['periodontal'] ?? null,
            'Pemeriksaan klinis gigi' => $clinical,
            'Penunjang / order lab' => $supporting,
        ]);
    }

    /**
     * Diagnosis dikelompokkan per jenis; utama di depan.
     *
     * @return array<string, array{label: string, items: Collection<int, Diagnosis>}>
     */
    public function assessment(): array
    {
        $groups = [];
        $order = [DiagnosisKind::Working, DiagnosisKind::Differential, DiagnosisKind::Clinical, DiagnosisKind::Final];

        $sorted = $this->visit->diagnoses->sortBy(fn (Diagnosis $d) => [$d->rank === DiagnosisRank::Primary ? 0 : 1, $d->id]);

        foreach ($order as $kind) {
            $items = $sorted->filter(fn (Diagnosis $d) => $d->kind === $kind)->values();

            if ($items->isNotEmpty()) {
                $groups[$kind->value] = ['label' => $kind->label(), 'items' => $items];
            }
        }

        $unknown = $sorted->filter(fn (Diagnosis $d) => $d->kind === null)->values();

        if ($unknown->isNotEmpty()) {
            $groups['unspecified'] = ['label' => 'Diagnosis (jenis tidak dicatat)', 'items' => $unknown];
        }

        return $groups;
    }

    public function primaryDiagnosis(): ?Diagnosis
    {
        return $this->visit->diagnoses->first(fn (Diagnosis $d) => $d->rank === DiagnosisRank::Primary)
            ?? $this->visit->diagnoses->sortBy('id')->first();
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function plan(): array
    {
        $record = $this->visit->medicalRecord;
        $plan = is_array($record?->plan_instructions) ? $record->plan_instructions : [];
        $labels = CareExamOptions::instructions();
        $statuses = CareExamOptions::carePlanStatuses();
        $instructions = collect($plan['items'] ?? [])
            ->reject(fn ($key) => $key === 'lainnya')
            ->map(fn ($key) => $labels[$key] ?? (is_string($key) ? $key : null))
            ->filter()
            ->when(filled($plan['extra'] ?? null), fn ($items) => $items->push($plan['extra']))
            ->implode("\n");

        $carePlan = collect([
            filled($plan['goal'] ?? null) ? 'Tujuan: '.$plan['goal'] : null,
            filled($plan['prognosis'] ?? null) ? 'Prognosis: '.$plan['prognosis'] : null,
            filled($plan['status'] ?? null) ? 'Status rencana: '.($statuses[$plan['status']] ?? $plan['status']) : null,
        ])->filter()->implode("\n");

        $followUp = collect([
            $record?->followup_date ? 'Follow up '.$record->followup_date->format('d M Y') : null,
            ...$this->visit->referrals->map(fn ($r) => 'Rujuk ke '.$r->referred_to.(filled($r->reason) ? ' — '.$r->reason : ''))->all(),
        ])->filter()->implode("\n");

        return $this->rows([
            'Rencana perawatan' => $carePlan,
            'Instruksi perawatan di rumah' => $instructions,
            'Jadwal kontrol' => $record?->control_date?->format('d M Y'),
            'Rencana tindak lanjut' => $followUp,
        ]);
    }

    public function doctorNotes(): ?string
    {
        $notes = trim((string) ($this->visit->medicalRecord?->care_notes ?? ''));

        return $notes !== '' ? $notes : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function history(): array
    {
        $history = $this->visit->medicalRecord?->systemic_history;

        return is_array($history) ? $history : [];
    }

    /**
     * @param  array<string, mixed>  $rows
     * @return list<array{label: string, value: string}>
     */
    private function rows(array $rows): array
    {
        $result = [];

        foreach ($rows as $label => $value) {
            $text = trim((string) ($value ?? ''));

            if ($text !== '') {
                $result[] = ['label' => $label, 'value' => $text];
            }
        }

        return $result;
    }

    private function withNote(?string $value, mixed $note): ?string
    {
        $text = trim((string) $value);

        if (filled($note)) {
            $text = trim($text.($text !== '' ? ' — ' : '').$note);
        }

        return $text !== '' ? $text : null;
    }
}
