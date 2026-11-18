<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Support\Clinical\AllergyHistory;
use App\Support\Clinical\CareExamOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSystemicHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        $user = $this->user();

        return ($user?->can('anamnesis.manage') || $user?->can('medical_record.update'))
            && $user?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conditions' => ['nullable', 'array'],
            'conditions.*' => ['string', Rule::in(array_keys(CareExamOptions::systemic()))],
            'blood_pressure_sys' => ['nullable', 'string', 'max:10'],
            'blood_pressure_dia' => ['nullable', 'string', 'max:10'],
            'allergies' => ['nullable', 'array'],
            'allergies.*.category' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::allergyCategories()))],
            'allergies.*.type' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::allergyTypes()))],
            'allergies.*.clinical_status' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::allergyClinicalStatuses()))],
            'allergies.*.severity' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::allergySeverities()))],
            'allergies.*.detail' => ['nullable', 'string', 'max:255'],
            'allergies.*.reaction' => ['nullable', 'string', 'max:255'],
            'allergies.*.note' => ['nullable', 'string', 'max:1000'],
            'allergy_detail' => ['nullable', 'string', 'max:255'],
            'allergy_status' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::allergyStatuses()))],
            'allergy_category' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::allergyCategories()))],
            'allergy_type' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::allergyTypes()))],
            'allergy_clinical_status' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::allergyClinicalStatuses()))],
            'allergy_reaction' => ['nullable', 'string', 'max:255'],
            'allergy_severity' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::allergySeverities()))],
            'allergy_note' => ['nullable', 'string', 'max:1000'],
            'medication_detail' => ['nullable', 'string', 'max:255', 'required_if:medication_status,present'],
            'medication_status' => ['nullable', 'string', Rule::in(['not_asked', 'none_confirmed', 'present'])],
            'blood_type' => ['nullable', 'string', Rule::in(['A', 'B', 'AB', 'O'])],
            'rhesus' => ['nullable', 'string', Rule::in(['positive', 'negative'])],
            'pregnancy_status' => ['nullable', 'string', Rule::in(['pregnant', 'not_pregnant'])],
            'other_detail' => ['nullable', 'string', 'max:255'],
            'family_history' => ['nullable', 'string', 'max:2000'],
            'dental_history' => ['nullable', 'string', 'max:2000'],
            'drug_allergy' => ['nullable', 'string', 'max:1000'],
            'material_allergy' => ['nullable', 'string', 'max:1000'],
            'none' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((string) $this->input('allergy_status') !== 'present') {
                return;
            }

            $items = AllergyHistory::items([
                'allergies' => $this->input('allergies', []),
                'allergy_category' => $this->input('allergy_category'),
                'allergy_detail' => $this->input('allergy_detail'),
                'allergy_type' => $this->input('allergy_type'),
                'allergy_clinical_status' => $this->input('allergy_clinical_status'),
                'allergy_severity' => $this->input('allergy_severity'),
                'allergy_reaction' => $this->input('allergy_reaction'),
                'allergy_note' => $this->input('allergy_note'),
            ]);

            if ($items === []) {
                $validator->errors()->add('allergies', 'Tambahkan minimal satu alergi (kategori dan zat pemicu).');

                return;
            }

            foreach ($items as $index => $item) {
                if ($item['category'] === '') {
                    $validator->errors()->add("allergies.$index.category", 'Kategori alergi wajib dipilih.');
                }
                if ($item['detail'] === '') {
                    $validator->errors()->add("allergies.$index.detail", 'Zat atau bahan pemicu wajib diisi.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $hasConditions = $this->exists('conditions') || $this->exists('none');
        $hasAllergy = $this->exists('allergy_status') || $this->exists('allergies');
        $hasMedication = $this->exists('medication_status') || $this->exists('medication_detail');

        $merge = [];

        if ($hasConditions) {
            $conditions = $this->input('conditions', []);
            if (! is_array($conditions)) {
                $conditions = [];
            }
            $merge['none'] = $this->boolean('none');
            $merge['conditions'] = array_values($conditions);
        } else {
            $conditions = [];
        }

        if ($hasAllergy) {
            $allergyStatus = (string) $this->input('allergy_status', 'not_asked');
            if ($allergyStatus === '') {
                $allergyStatus = 'not_asked';
            }

            $allergies = [];
            foreach ((array) $this->input('allergies', []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $item = AllergyHistory::normalizeItem($row);
                if ($item !== null) {
                    $allergies[] = $item;
                }
            }

            if ($hasConditions) {
                if (in_array('allergy', $conditions, true) && in_array($allergyStatus, ['', 'not_asked'], true)) {
                    $allergyStatus = 'present';
                }
                if ($allergyStatus === 'present' && ! in_array('allergy', $conditions, true)) {
                    $conditions[] = 'allergy';
                    $merge['conditions'] = array_values($conditions);
                }
            }

            $merge['allergy_status'] = $allergyStatus;
            $merge['allergies'] = $allergies;

            if ($allergies !== []) {
                $first = $allergies[0];
                $merge['allergy_category'] = $first['category'] ?: null;
                $merge['allergy_type'] = $first['type'] ?: null;
                $merge['allergy_clinical_status'] = $first['clinical_status'] ?: null;
                $merge['allergy_severity'] = $first['severity'] ?: null;
                $merge['allergy_detail'] = $first['detail'] ?: null;
                $merge['allergy_reaction'] = $first['reaction'] ?: null;
                $merge['allergy_note'] = $first['note'] ?: null;
            } elseif ($allergyStatus !== 'present') {
                $merge['allergy_category'] = null;
                $merge['allergy_type'] = null;
                $merge['allergy_clinical_status'] = null;
                $merge['allergy_severity'] = null;
                $merge['allergy_detail'] = null;
                $merge['allergy_reaction'] = null;
                $merge['allergy_note'] = null;
            }
        }

        if ($hasMedication) {
            $medicationStatus = (string) $this->input('medication_status', 'not_asked');
            if ($medicationStatus === '') {
                $medicationStatus = 'not_asked';
            }

            if ($hasConditions) {
                if (in_array('medication', $conditions, true) && in_array($medicationStatus, ['', 'not_asked'], true)) {
                    $medicationStatus = 'present';
                }
                if ($medicationStatus === 'present' && ! in_array('medication', $conditions, true)) {
                    $conditions[] = 'medication';
                    $merge['conditions'] = array_values($conditions);
                }
            }

            $merge['medication_status'] = $medicationStatus;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'medication_detail.required_if' => 'Nama obat rutin wajib diisi jika sedang memakai obat.',
        ];
    }
}
