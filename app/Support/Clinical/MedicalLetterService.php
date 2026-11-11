<?php

namespace App\Support\Clinical;

use App\Enums\DiagnosisKind;
use App\Enums\DiagnosisRank;
use App\Enums\MedicalLetterStatus;
use App\Enums\MedicalLetterType;
use App\Models\Diagnosis;
use App\Models\MedicalLetter;
use App\Models\User;
use App\Models\Visit;
use App\Support\ClinicSettings;
use App\Support\Registration\DocumentSequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MedicalLetterService
{
    public function __construct(protected DocumentSequenceGenerator $sequences) {}

    public function assertCanIssue(User $user, Visit $visit): void
    {
        abort_unless($user->can('medical_record.update'), 403);
        abort_unless($user->can('view', $visit), 403);
        abort_unless($visit->doctor_id, 422, 'Kunjungan belum memiliki dokter pemeriksa.');
        abort_unless($user->canAccessBranch((int) $visit->branch_id), 403);

        if ($user->hasRole(\App\Enums\RoleName::Dentist) && ! $user->can('branch.manage')) {
            abort_unless((int) $visit->doctor_id === (int) $user->id, 403, 'Surat hanya dapat diterbitkan oleh dokter pemeriksa kunjungan ini.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(Visit $visit): array
    {
        $visit->loadMissing(['patient', 'doctor.doctorProfile', 'branch', 'diagnoses', 'procedureRecords.procedure', 'medicalRecord']);
        $record = $visit->medicalRecord;
        $visitDate = $visit->visit_date?->toDateString() ?: now()->toDateString();
        $procedures = $visit->procedureRecords
            ->map(fn ($item) => $item->procedure?->name ?: 'Tindakan')
            ->filter()
            ->implode(', ');

        $base = [
            'type' => MedicalLetterType::Sick->value,
            'issued_on' => now()->toDateString(),
            'purpose' => '',
            'rest_from' => $visitDate,
            'rest_until' => $visitDate,
            'rest_days' => 1,
            'condition_note' => (string) ($record?->chief_complaint ?? ''),
            'healthy_for' => 'pekerjaan',
            'exam_result' => (string) ($record?->initial_examination ?? ''),
            'conclusion' => '',
            'complaint' => (string) ($record?->chief_complaint ?? ''),
            'radiograph_date' => $visitDate,
            'radiograph_type' => 'panoramik',
            'radiograph_region' => '',
            'radiograph_facility' => '',
            'radiograph_indication' => (string) ($record?->chief_complaint ?? ''),
            'radiograph_note' => '',
            'radiograph_finding' => '',
            'radiograph_conclusion' => '',
            'procedures_note' => $procedures,
            'treatment_date' => $visitDate,
            'follow_up' => (string) data_get($record?->plan_instructions, 'extra', ''),
            'control_date' => $record?->control_date?->toDateString() ?: '',
            'control_reason' => '',
            'doctor_note' => (string) ($record?->clinical_notes ?? ''),
            'title' => '',
            'diagnosis_ids' => $visit->diagnoses->pluck('id')->all(),
        ];
        $base['body'] = $this->suggestedBody($visit, $base);

        return $base;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function suggestedBody(Visit $visit, array $data): string
    {
        $visit->loadMissing(['diagnoses', 'medicalRecord', 'procedureRecords.procedure']);
        $type = MedicalLetterType::tryFrom((string) ($data['type'] ?? '')) ?? MedicalLetterType::Sick;
        $diagnosisText = $this->diagnosisTextFor($visit, $data);
        $days = $this->restDays($data);

        return match ($type) {
            MedicalLetterType::Sick => trim(implode("\n\n", array_filter([
                'Yang bertanda tangan di bawah ini menerangkan bahwa pasien tersebut di atas perlu beristirahat karena sakit dan tidak dapat melakukan pekerjaan, kegiatan sekolah, atau aktivitas lainnya.',
                'Istirahat terhitung mulai '.$this->dateLabel($data['rest_from'] ?? null).' sampai '.$this->dateLabel($data['rest_until'] ?? null).($days ? ' ('.$days.' hari).' : '.'),
                filled($data['condition_note'] ?? null) ? 'Keterangan kondisi: '.$data['condition_note'] : null,
                'Diagnosis yang mendasari: '.$diagnosisText,
            ]))),
            MedicalLetterType::Healthy => trim(implode("\n\n", array_filter([
                'Yang bertanda tangan di bawah ini menerangkan bahwa pasien tersebut di atas telah diperiksa di klinik ini untuk keperluan '.($this->healthyLabel($data['healthy_for'] ?? null)).'.',
                filled($data['exam_result'] ?? null) ? 'Hasil pemeriksaan: '.$data['exam_result'] : 'Hasil pemeriksaan sesuai catatan rekam medis kunjungan ini.',
                filled($data['conclusion'] ?? null) ? 'Kesimpulan: '.$data['conclusion'] : null,
                'Diagnosis: '.$diagnosisText,
            ]))),
            MedicalLetterType::Examination => trim(implode("\n\n", array_filter([
                'Yang bertanda tangan di bawah ini menerangkan bahwa pasien tersebut di atas telah melakukan pemeriksaan di klinik ini pada '.$this->examinedAtLabel($visit).'.',
                filled($data['complaint'] ?? null) ? 'Keluhan: '.$data['complaint'] : null,
                filled($data['exam_result'] ?? null) ? 'Hasil pemeriksaan: '.$data['exam_result'] : null,
                'Diagnosis klinis: '.$diagnosisText,
            ]))),
            MedicalLetterType::Radiograph => trim(implode("\n\n", array_filter([
                'Dengan hormat, bersama ini kami mengantar pasien tersebut di atas untuk dilakukan pemeriksaan rontgen di '
                    .(filled($data['radiograph_facility'] ?? null) ? $data['radiograph_facility'] : 'rumah sakit / fasilitas radiologi').'.',
                'Pemeriksaan yang diminta: '.$this->radiographLabel($data['radiograph_type'] ?? null)
                    .(filled($data['radiograph_region'] ?? null) ? ', regio '.$data['radiograph_region'] : '').'.',
                'Tanggal pengantar: '.$this->dateLabel($data['radiograph_date'] ?? null).'.',
                filled($data['radiograph_indication'] ?? $data['radiograph_finding'] ?? null)
                    ? 'Indikasi klinis: '.($data['radiograph_indication'] ?? $data['radiograph_finding'])
                    : null,
                'Diagnosis klinis: '.$diagnosisText,
                filled($data['radiograph_note'] ?? $data['radiograph_conclusion'] ?? null)
                    ? 'Catatan untuk fasilitas radiologi: '.($data['radiograph_note'] ?? $data['radiograph_conclusion'])
                    : null,
                'Demikian surat pengantar ini dibuat untuk dipergunakan sebagaimana mestinya. Atas bantuan dan kerja samanya, kami ucapkan terima kasih.',
            ]))),
            MedicalLetterType::Treatment => trim(implode("\n\n", array_filter([
                'Yang bertanda tangan di bawah ini menerangkan bahwa pasien tersebut di atas sedang menjalani perawatan medis di klinik ini.',
                'Tindakan: '.(filled($data['procedures_note'] ?? null) ? $data['procedures_note'] : '-'),
                'Tanggal perawatan: '.$this->dateLabel($data['treatment_date'] ?? null).'.',
                filled($data['follow_up'] ?? null) ? 'Tindak lanjut: '.$data['follow_up'] : null,
                'Diagnosis: '.$diagnosisText,
            ]))),
            MedicalLetterType::Control => trim(implode("\n\n", array_filter([
                'Yang bertanda tangan di bawah ini menerangkan bahwa pasien tersebut di atas perlu melakukan kunjungan kontrol.',
                'Tanggal kontrol yang dianjurkan: '.$this->dateLabel($data['control_date'] ?? null).'.',
                filled($data['control_reason'] ?? null) ? 'Alasan kontrol: '.$data['control_reason'] : null,
                filled($data['doctor_note'] ?? null) ? 'Catatan dokter: '.$data['doctor_note'] : null,
                'Diagnosis: '.$diagnosisText,
            ]))),
            MedicalLetterType::Other => trim(implode("\n\n", array_filter([
                filled($data['title'] ?? null) ? (string) $data['title'] : 'Surat keterangan medis',
                filled($data['purpose'] ?? null) ? 'Keperluan: '.$data['purpose'] : null,
                'Diagnosis: '.$diagnosisText,
            ]))),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function issue(Visit $visit, User $actor, array $data, ?MedicalLetter $revises = null): MedicalLetter
    {
        $this->assertCanIssue($actor, $visit);

        if ($revises) {
            abort_unless((int) $revises->visit_id === (int) $visit->id, 404);
            abort_unless($revises->isIssued(), 422, 'Hanya surat yang masih berlaku yang dapat direvisi.');
        }

        return DB::transaction(function () use ($visit, $actor, $data, $revises) {
            if ($revises) {
                $revises = MedicalLetter::query()->whereKey($revises->id)->lockForUpdate()->firstOrFail();
                abort_unless($revises->isIssued(), 422, 'Surat sudah tidak berlaku.');
            }

            $this->persistDiagnoses($visit, $actor, $data);
            $visit->unsetRelation('diagnoses');
            $attributes = $this->attributes($visit, $actor, $data, $revises);
            $letter = MedicalLetter::query()->create($attributes);

            if ($revises) {
                $revises->update([
                    'status' => MedicalLetterStatus::Revised,
                    'revised_by_id' => $letter->id,
                ]);
                activity_audit('updated', $revises, null, 'Revised medical letter '.$revises->number, 'medical_letters', [
                    'replacement_id' => $letter->id,
                ], $actor);
            }

            activity_log('created', $letter, [
                'number' => $letter->number,
                'type' => $letter->type->value,
                'revises_id' => $revises?->id,
            ], $revises ? 'Issued medical letter revision' : 'Issued medical letter', 'medical_letters', $actor);

            return $letter;
        });
    }

    public function cancel(MedicalLetter $letter, User $actor, string $reason): void
    {
        $letter->loadMissing('visit');
        abort_unless($letter->visit, 404);
        $this->assertCanIssue($actor, $letter->visit);
        abort_unless($letter->isIssued(), 422, 'Surat ini tidak dapat dibatalkan.');

        $before = activity_snapshot($letter);
        $letter->update([
            'status' => MedicalLetterStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
        activity_audit('updated', $letter, $before, 'Cancelled medical letter '.$letter->number, 'medical_letters', [
            'cancel_reason' => $reason,
        ], $actor);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function draft(Visit $visit, User $actor, array $data, ?MedicalLetter $revises = null): MedicalLetter
    {
        $attributes = $this->attributes($visit, $actor, $data, $revises, draft: true);
        $letter = new MedicalLetter($attributes);
        $letter->number = 'DRAFT';
        $letter->setRelation('patient', $visit->patient);
        $letter->setRelation('doctor', $visit->doctor);
        $letter->setRelation('branch', $visit->branch);
        $letter->setRelation('visit', $visit);

        return $letter;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function attributes(Visit $visit, User $actor, array $data, ?MedicalLetter $revises = null, bool $draft = false): array
    {
        $visit->loadMissing(['patient', 'doctor.doctorProfile', 'branch', 'diagnoses', 'medicalRecord']);
        $patient = $visit->patient;
        abort_unless($patient, 422, 'Kunjungan tidak memiliki pasien.');

        $type = MedicalLetterType::from((string) $data['type']);
        $diagnosisRows = $this->diagnosisSnapshots($visit, $data);
        $body = trim((string) ($data['body'] ?? ''));

        if ($body === '') {
            $body = $this->suggestedBody($visit, $data);
        }

        $issuedOn = Carbon::parse($data['issued_on'] ?? now())->startOfDay();
        $doctor = $visit->doctor;
        $address = trim((string) ($data['address_override'] ?? ''));

        if ($address === '') {
            $address = collect([
                $patient->address,
                (filled($patient->rt) || filled($patient->rw)) ? 'RT '.($patient->rt ?: '-').'/RW '.($patient->rw ?: '-') : null,
                $patient->address_city,
                $patient->address_postal_code,
            ])->filter(fn ($value) => filled($value))->implode(', ');
        }

        $settings = ClinicSettings::current((int) $visit->tenant_id);
        $addressLines = ClinicSettings::addressLines((int) $visit->tenant_id);

        $examinedAt = ($visit->arrived_at ?? $visit->created_at)?->toDateTimeString();
        $payload = match ($type) {
            MedicalLetterType::Sick => [
                'rest_from' => $data['rest_from'] ?? null,
                'rest_until' => $data['rest_until'] ?? null,
                'rest_days' => $this->restDays($data),
                'condition_note' => $data['condition_note'] ?? null,
            ],
            MedicalLetterType::Healthy => [
                'healthy_for' => $data['healthy_for'] ?? null,
                'exam_result' => $data['exam_result'] ?? null,
                'conclusion' => $data['conclusion'] ?? null,
            ],
            MedicalLetterType::Examination => [
                'complaint' => $data['complaint'] ?? null,
                'exam_result' => $data['exam_result'] ?? null,
                'examined_at' => $examinedAt,
            ],
            MedicalLetterType::Radiograph => [
                'radiograph_date' => $data['radiograph_date'] ?? null,
                'radiograph_type' => $data['radiograph_type'] ?? null,
                'radiograph_region' => $data['radiograph_region'] ?? null,
                'radiograph_facility' => $data['radiograph_facility'] ?? null,
                'radiograph_indication' => $data['radiograph_indication'] ?? $data['radiograph_finding'] ?? null,
                'radiograph_note' => $data['radiograph_note'] ?? $data['radiograph_conclusion'] ?? null,
            ],
            MedicalLetterType::Treatment => [
                'procedures_note' => $data['procedures_note'] ?? null,
                'treatment_date' => $data['treatment_date'] ?? null,
                'follow_up' => $data['follow_up'] ?? null,
            ],
            MedicalLetterType::Control => [
                'control_date' => $data['control_date'] ?? null,
                'control_reason' => $data['control_reason'] ?? null,
                'doctor_note' => $data['doctor_note'] ?? null,
            ],
            MedicalLetterType::Other => [
                'title' => $data['title'] ?? null,
            ],
        };
        $payload = array_filter($payload, fn ($value) => filled($value));

        return [
            'tenant_id' => $visit->tenant_id,
            'branch_id' => $visit->branch_id,
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'doctor_id' => $visit->doctor_id,
            'created_by' => $actor->id,
            'type' => $type,
            'number' => $draft ? 'DRAFT' : $this->sequences->nextMedicalLetter((int) $visit->tenant_id, (int) $visit->branch_id),
            'purpose' => filled($data['purpose'] ?? null) ? $data['purpose'] : null,
            'body' => $body,
            'payload' => $payload,
            'snapshot' => [
                'patient' => [
                    'name' => $patient->name,
                    'medical_record_number' => $patient->medical_record_number,
                    'nik' => $patient->nik,
                    'birth_place' => $patient->birth_place,
                    'dob' => $patient->dob?->toDateString(),
                    'gender' => $patient->gender?->label(),
                    'address' => $address !== '' ? $address : null,
                ],
                'doctor' => [
                    'name' => $doctor?->name,
                    'sip' => $doctor?->doctorProfile?->sip,
                ],
                'clinic' => [
                    'name' => ClinicSettings::name((int) $visit->tenant_id),
                    'address' => implode(', ', $addressLines),
                    'phone' => $settings['phone'] ?: $visit->branch?->phone,
                    'branch' => $visit->branch?->name,
                    'city' => $settings['print_city'] ?: ($settings['city'] ?: $visit->branch?->name),
                ],
                'visit' => [
                    'date' => $visit->visit_date?->toDateString(),
                    'examined_at' => ($visit->arrived_at ?? $visit->created_at)?->toDateTimeString(),
                ],
                'diagnoses' => $diagnosisRows,
            ],
            'status' => MedicalLetterStatus::Issued,
            'signature_path' => $doctor?->doctorProfile?->signature_path,
            'issued_at' => $issuedOn->setTimeFromTimeString(now()->format('H:i:s')),
            'revises_id' => $revises?->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function diagnosisTextFor(Visit $visit, array $data): string
    {
        if (array_key_exists('diagnoses', $data)) {
            $lines = collect($this->diagnosisSnapshots($visit, $data))->pluck('line')->filter();

            return $lines->isEmpty() ? 'tidak dicantumkan' : $lines->implode('; ');
        }

        return $this->diagnosisText($this->selectedDiagnoses(
            $visit,
            $data['diagnosis_ids'] ?? [],
            ($data['diagnosis_scope'] ?? '') === 'selected',
        ));
    }

    /**
     * Baris diagnosis yang tercetak. Jika form mengirim ubahan, teks surat memakai ubahan itu.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public function diagnosisSnapshots(Visit $visit, array $data): array
    {
        if (! array_key_exists('diagnoses', $data)) {
            return $this->selectedDiagnoses($visit, $data['diagnosis_ids'] ?? [], ($data['diagnosis_scope'] ?? '') === 'selected')
                ->map(fn (Diagnosis $diagnosis) => [
                    'kind_label' => $diagnosis->kind?->label(),
                    'rank_label' => $diagnosis->rank?->label(),
                    'code' => $diagnosis->code,
                    'description' => $diagnosis->description,
                    'note' => $diagnosis->clinical_note,
                    'line' => $diagnosis->displayLine(),
                ])->values()->all();
        }

        return collect($data['diagnoses'])
            ->filter(fn ($row) => is_array($row) && ! empty($row['selected']) && filled($row['description'] ?? null))
            ->map(function (array $row) {
                $code = filled($row['code'] ?? null) ? trim((string) $row['code']) : null;
                $description = trim((string) $row['description']);

                return [
                    'kind_label' => 'Diagnosa sementara',
                    'rank_label' => null,
                    'code' => $code,
                    'description' => $description,
                    'note' => null,
                    'line' => trim(($code ? $code.' — ' : '').$description),
                ];
            })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function persistDiagnoses(Visit $visit, User $actor, array $data): void
    {
        if (! array_key_exists('diagnoses', $data) || ! $actor->can('diagnosis.manage')) {
            return;
        }

        $record = $visit->medicalRecord;
        if ($record && app(MedicalRecordLock::class)->isLocked($record)) {
            return;
        }

        $visit->loadMissing('diagnoses');

        foreach ($data['diagnoses'] as $row) {
            if (! is_array($row) || ! filled($row['description'] ?? null)) {
                continue;
            }

            if (empty($row['selected'])) {
                continue;
            }

            $code = filled($row['code'] ?? null) ? trim((string) $row['code']) : null;
            $description = trim((string) $row['description']);
            $id = (int) ($row['id'] ?? 0);
            $exists = $id > 0 || $visit->diagnoses->contains(function (Diagnosis $diagnosis) use ($code, $description) {
                return ($code !== null && $diagnosis->code === $code)
                    || ($code === null && $diagnosis->description === $description);
            });

            if ($exists) {
                continue;
            }

            $values = [
                'kind' => DiagnosisKind::Working->value,
                'rank' => DiagnosisRank::Secondary->value,
                'code' => $code,
                'description' => $description,
            ];
            $created = $visit->diagnoses()->create($values);
            activity_log('created', $created, $values, 'Added diagnosis from medical letter', 'diagnoses', $actor);
        }
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return Collection<int, Diagnosis>
     */
    public function selectedDiagnoses(Visit $visit, array $ids, bool $strict = false): Collection
    {
        $wanted = collect($ids)->map(fn ($id) => (int) $id)->filter()->all();

        if ($strict || $wanted !== []) {
            return $visit->diagnoses->whereIn('id', $wanted)->values();
        }

        return $visit->diagnoses->values();
    }

    /**
     * @param  Collection<int, Diagnosis>  $diagnoses
     */
    public function diagnosisText(Collection $diagnoses): string
    {
        if ($diagnoses->isEmpty()) {
            return 'tidak dicantumkan';
        }

        return $diagnoses->map(fn (Diagnosis $diagnosis) => $diagnosis->displayLine())->implode('; ');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function restDays(array $data): ?int
    {
        if (filled($data['rest_from'] ?? null) && filled($data['rest_until'] ?? null)) {
            $from = Carbon::parse($data['rest_from'])->startOfDay();
            $until = Carbon::parse($data['rest_until'])->startOfDay();

            if ($until->lt($from)) {
                throw ValidationException::withMessages([
                    'rest_until' => 'Tanggal selesai istirahat tidak boleh sebelum tanggal mulai.',
                ]);
            }

            return (int) abs($from->diffInDays($until)) + 1;
        }

        return filled($data['rest_days'] ?? null) ? (int) $data['rest_days'] : null;
    }

    public function dateLabel(mixed $value): string
    {
        if (! filled($value)) {
            return '-';
        }

        $date = Carbon::parse($value);
        $months = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

        return $date->format('j').' '.$months[(int) $date->format('n')].' '.$date->format('Y');
    }

    public function examinedAtLabel(Visit $visit): string
    {
        $at = $visit->arrived_at ?? $visit->created_at;

        if (! $at) {
            return $this->dateLabel($visit->visit_date);
        }

        return $this->dateLabel($at).' pukul '.$at->format('H:i');
    }

    public function radiographLabel(mixed $value): string
    {
        return match ((string) $value) {
            'periapikal' => 'foto periapikal',
            'panoramik' => 'foto panoramik',
            'sefalometri' => 'foto sefalometri',
            'oklusal' => 'foto oklusal',
            'bitewing' => 'foto bitewing',
            'lainnya' => 'pemeriksaan rontgen lainnya',
            default => filled($value) ? (string) $value : '-',
        };
    }

    public function healthyLabel(mixed $value): string
    {
        return match ((string) $value) {
            'sekolah' => 'persyaratan sekolah atau pendidikan',
            'pendaftaran' => 'persyaratan pendaftaran',
            'lainnya' => 'persyaratan administrasi lainnya',
            default => 'persyaratan administrasi pekerjaan',
        };
    }
}
