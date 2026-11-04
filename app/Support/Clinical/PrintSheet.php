<?php

namespace App\Support\Clinical;

use App\Models\DentalLabOrder;
use App\Models\Visit;
use App\Support\Indonesia\WilayahService;

/**
 * Isi resume medis rawat jalan untuk satu kunjungan.
 */
final class PrintSheet
{
    public function __construct(public Visit $visit) {}

    public static function for(Visit $visit): self
    {
        return new self($visit);
    }

    public function registrationNumber(): string
    {
        return (new ExaminationQueueRow($this->visit))->registrationNumber();
    }

    public function assessedAtLabel(): string
    {
        $at = $this->visit->arrived_at ?? $this->visit->created_at;

        return $at ? $at->format('d-M-Y/H:i:s') : '-';
    }

    public function patientName(): string
    {
        return $this->text($this->visit->patient?->name);
    }

    public function medicalRecordNumber(): string
    {
        return $this->text($this->visit->patient?->medical_record_number);
    }

    public function birthLabel(): string
    {
        $patient = $this->visit->patient;

        if ($patient === null) {
            return '-';
        }

        $dob = $patient->dob?->format('d-M-Y');
        $age = $patient->ageLabel();

        if ($dob && $age !== '-') {
            return $dob.' / '.$age;
        }

        return $dob ?: $age;
    }

    public function nik(): string
    {
        return $this->text($this->visit->patient?->nik);
    }

    public function genderLabel(): string
    {
        return $this->text($this->visit->patient?->gender?->label());
    }

    public function address(): string
    {
        $patient = $this->visit->patient;

        if ($patient === null) {
            return '-';
        }

        $labels = [];

        if (filled($patient->province_code) || filled($patient->city_code) || filled($patient->district_code) || filled($patient->village_code)) {
            $labels = app(WilayahService::class)->labels(
                $patient->province_code,
                $patient->city_code,
                $patient->district_code,
                $patient->village_code,
            );
        }

        $rtRw = (filled($patient->rt) || filled($patient->rw))
            ? 'RT '.($patient->rt ?: '-').' / RW '.($patient->rw ?: '-')
            : null;

        $line = collect([
            $patient->address,
            $rtRw,
            $labels['village'] ?? null,
            $labels['district'] ?? null,
            $labels['city'] ?? $patient->address_city,
            $labels['province'] ?? null,
            $patient->address_postal_code,
        ])->filter(fn ($value) => filled($value))->implode(', ');

        return $line !== '' ? $line : '-';
    }

    public function poli(): string
    {
        $row = new ExaminationQueueRow($this->visit);
        $poli = $row->poliName();

        if ($poli !== '-') {
            return $poli;
        }

        return $this->text($this->visit->room?->name ?: $this->visit->branch?->name);
    }

    public function doctorName(): string
    {
        return $this->text($this->visit->doctor?->name);
    }

    public function doctorSip(): string
    {
        return trim((string) ($this->visit->doctor?->doctorProfile?->sip ?? ''));
    }

    public function nurseName(): string
    {
        return '-';
    }

    public function payerName(): string
    {
        return (new ExaminationQueueRow($this->visit))->payerName();
    }

    public function insuranceNumber(): string
    {
        return $this->text((new ExaminationQueueRow($this->visit))->payerCard());
    }

    public function anamnesis(): string
    {
        $record = $this->visit->medicalRecord;
        $parts = array_values(array_filter([
            filled($record?->chief_complaint) ? trim((string) $record->chief_complaint) : null,
            filled($record?->anamnesis) ? trim((string) $record->anamnesis) : null,
        ]));

        return $parts === [] ? '-' : implode("\n", $parts);
    }

    public function allergy(string $bucket): string
    {
        $category = match ($bucket) {
            'food' => 'food',
            'air' => 'environment',
            'drug' => 'medication',
            default => 'biologic',
        };
        $history = $this->history();
        $status = (string) ($history['allergy_status'] ?? 'not_asked');
        $detail = trim((string) ($history['allergy_detail'] ?? ''));
        $current = (string) ($history['allergy_category'] ?? '');

        return match ($status) {
            'none_confirmed' => 'Tidak ada',
            'unknown' => 'Tidak diketahui',
            'present' => $current === $category
                ? ('Ada'.($detail !== '' ? ' — '.$detail : ''))
                : 'Tidak ada',
            default => '-',
        };
    }

    public function vital(string $key): string
    {
        return $this->text($this->vitals()[$key] ?? null);
    }

    public function physicalExam(): string
    {
        $record = $this->visit->medicalRecord;
        $exam = is_array($record?->dental_exam) ? $record->dental_exam : [];
        $occlusion = ($exam['occlusion'] ?? null) === 'deep' ? 'steep' : ($exam['occlusion'] ?? null);
        $lines = array_values(array_filter([
            ($text = $this->withNote($this->option('finding', $exam['extraoral'] ?? null), $exam['extraoral_note'] ?? null)) ? 'Ekstraoral: '.$text : null,
            ($text = $this->withNote($this->option('finding', $exam['intraoral'] ?? null), $exam['intraoral_note'] ?? null)) ? 'Intraoral: '.$text : null,
            ($text = $this->option('occlusion', $occlusion)) ? 'Oklusi: '.$text : null,
            ($text = $this->option('gingiva', $exam['gingiva'] ?? null)) ? 'Gingiva: '.$text : null,
            ($text = $this->option('mucosa', $exam['mucosa'] ?? null)) ? 'Mukosa: '.$text : null,
            ($text = $this->option('palate', $exam['palate'] ?? null)) ? 'Palatum: '.$text : null,
            ($text = $this->option('torus', $exam['torus_palatinus'] ?? null)) ? 'Torus palatinus: '.$text : null,
            ($text = $this->option('torus_mandibularis', $exam['torus_mandibularis'] ?? null)) ? 'Torus mandibularis: '.$text : null,
            ($text = $this->withNote($this->option('presence', $exam['diastema'] ?? null), $exam['diastema_elements'] ?? null)) ? 'Diastema: '.$text : null,
            ($text = $this->withNote($this->option('presence', $exam['supernumerary'] ?? null), $exam['supernumerary_teeth'] ?? null)) ? 'Supernumerary: '.$text : null,
            ($text = $this->withNote($this->option('presence', $exam['anomaly'] ?? null), $exam['anomaly_description'] ?? null)) ? 'Anomali: '.$text : null,
            filled($exam['habits'] ?? null) ? 'Kebiasaan: '.$exam['habits'] : null,
            filled($record?->initial_examination) ? trim((string) $record->initial_examination) : null,
        ]));
        $ohis = is_array($exam['ohis'] ?? null) ? $exam['ohis'] : [];

        if (is_numeric($ohis['debris_index'] ?? null) && is_numeric($ohis['calculus_index'] ?? null)) {
            $lines[] = 'OHIS: DI '.$ohis['debris_index'].' · CI '.$ohis['calculus_index'];
        }

        return $lines === [] ? '-' : implode('; ', $lines);
    }

    public function supporting(): string
    {
        if (! $this->visit->id) {
            return '-';
        }

        $lines = DentalLabOrder::withoutGlobalScopes()
            ->where('visit_id', $this->visit->id)
            ->orderBy('id')
            ->get()
            ->map(function (DentalLabOrder $order) {
                $label = trim((string) $order->work_type);

                return filled($order->tooth_numbers) ? $label.' ('.$order->tooth_numbers.')' : $label;
            })
            ->filter()
            ->values();

        return $lines->isNotEmpty() ? $lines->implode('; ') : '-';
    }

    public function icd10(): string
    {
        $lines = $this->visit->diagnoses
            ->map(fn ($diagnosis) => $diagnosis->displayLine())
            ->filter()
            ->values();

        return $lines->isNotEmpty() ? $lines->implode("\n") : '-';
    }

    public function icd9(): string
    {
        $lines = $this->visit->procedureRecords
            ->filter(fn ($item) => filled($item->icd9_code))
            ->map(fn ($item) => Icd9Catalog::label((string) $item->icd9_code))
            ->unique()
            ->values();

        return $lines->isNotEmpty() ? $lines->implode("\n") : '-';
    }

    public function nursingDiagnosis(): string
    {
        return '-';
    }

    public function carePlan(): string
    {
        $record = $this->visit->medicalRecord;
        $plan = is_array($record?->plan_instructions) ? $record->plan_instructions : [];
        $labels = CareExamOptions::instructions();
        $bits = collect($plan['items'] ?? [])
            ->map(fn ($key) => $labels[$key] ?? (is_string($key) ? $key : null))
            ->filter()
            ->values();

        if (filled($plan['goal'] ?? null)) {
            $bits->push('Tujuan: '.$plan['goal']);
        }

        if (filled($plan['extra'] ?? null)) {
            $bits->push($plan['extra']);
        }

        if ($record?->control_date) {
            $bits->push('Kontrol '.$record->control_date->format('d-M-Y'));
        }

        if ($record?->followup_date) {
            $bits->push('Follow up '.$record->followup_date->format('d-M-Y'));
        }

        return $bits->isNotEmpty() ? $bits->implode('; ') : '-';
    }

    public function specialNotes(): string
    {
        return $this->text($this->visit->medicalRecord?->clinical_notes);
    }

    public function therapy(): string
    {
        $lines = $this->visit->prescriptions->flatMap(function ($prescription) {
            return $prescription->items->map(function ($item) {
                $bits = array_filter([
                    $item->label(),
                    $item->dosage,
                    $item->frequency,
                    $item->duration,
                ]);

                return implode(' ', $bits);
            });
        })->filter()->values();

        return $lines->isNotEmpty() ? $lines->implode("\n") : '-';
    }

    public function procedures(): string
    {
        $lines = $this->visit->procedureRecords->map(function ($item) {
            $parts = array_filter([
                $item->procedure?->name ?: 'Tindakan',
                filled($item->tooth_number) ? 'gigi '.$item->tooth_number : null,
                (int) $item->quantity > 1 ? 'qty '.$item->quantity : null,
            ]);

            return implode(', ', $parts);
        })->filter()->values();

        return $lines->isNotEmpty() ? $lines->implode("\n") : '-';
    }

    public function screeningComplaint(): string
    {
        return $this->text($this->visit->medicalRecord?->chief_complaint);
    }

    public function screeningDrugAllergy(): string
    {
        return $this->allergy('drug');
    }

    public function screeningProlanis(): string
    {
        $history = $this->history();
        $conditions = array_values(array_filter(
            (array) ($history['conditions'] ?? []),
            fn ($value) => is_string($value) && $value !== '',
        ));

        if (array_intersect($conditions, ['diabetes', 'hypertension']) !== []) {
            return 'Ya';
        }

        if (filter_var($history['none'] ?? false, FILTER_VALIDATE_BOOLEAN) || $conditions !== []) {
            return 'Tidak';
        }

        return '-';
    }

    public function documentHash(?string $verificationCode = null): string
    {
        $record = $this->visit->medicalRecord;

        return hash('sha256', implode('|', [
            'RJ',
            (string) $this->visit->id,
            (string) $this->visit->patient?->medical_record_number,
            (string) $verificationCode,
            (string) $record?->updated_at?->toIso8601String(),
            $this->anamnesis(),
            $this->icd10(),
            $this->therapy(),
        ]));
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
     * @return array<string, mixed>
     */
    private function vitals(): array
    {
        $record = $this->visit->medicalRecord;
        $stored = is_array($record?->vital_signs) ? $record->vital_signs : [];
        $history = $this->history();
        $split = array_pad(explode('/', (string) ($stored['blood_pressure'] ?? ''), 2), 2, '');

        return [
            'sys' => $stored['blood_pressure_sys'] ?? ($split[0] !== '' ? $split[0] : ($history['blood_pressure_sys'] ?? null)),
            'dia' => $stored['blood_pressure_dia'] ?? ($split[1] !== '' ? $split[1] : ($history['blood_pressure_dia'] ?? null)),
            'pulse' => $stored['pulse'] ?? null,
            'respiration' => $stored['respiration'] ?? null,
            'temperature' => $stored['temperature'] ?? null,
            'weight' => $stored['weight'] ?? null,
            'height' => $stored['height'] ?? null,
            'waist' => $stored['abdominal_circumference'] ?? $stored['waist'] ?? $stored['lingkar_perut'] ?? null,
        ];
    }

    private function option(string $group, mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $options = match ($group) {
            'finding' => CareExamOptions::finding(),
            'occlusion' => CareExamOptions::occlusion(),
            'gingiva' => CareExamOptions::gingiva(),
            'mucosa' => CareExamOptions::mucosa(),
            'palate' => CareExamOptions::palate(),
            'torus' => CareExamOptions::torus(),
            'torus_mandibularis' => CareExamOptions::torusMandibularis(),
            'presence' => CareExamOptions::presence(),
            default => [],
        };

        return $options[$value] ?? $value;
    }

    private function withNote(?string $value, mixed $note): ?string
    {
        $text = $value ?: '';

        if (filled($note)) {
            $text = trim($text.($text !== '' ? ' — ' : '').$note);
        }

        return $text !== '' ? $text : null;
    }

    private function text(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : '-';
    }
}
