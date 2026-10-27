<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Support\Clinical\CareExamOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'allergy_detail' => ['nullable', 'string', 'max:255', 'required_if:allergy_status,present'],
            'allergy_status' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::allergyStatuses()))],
            'allergy_category' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::allergyCategories())), 'required_if:allergy_status,present'],
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
            'none' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $conditions = $this->input('conditions', []);

        if (! is_array($conditions)) {
            $conditions = [];
        }

        $allergyStatus = (string) $this->input('allergy_status', 'not_asked');
        $medicationStatus = (string) $this->input('medication_status', 'not_asked');

        if (in_array('allergy', $conditions, true) && in_array($allergyStatus, ['', 'not_asked'], true)) {
            $allergyStatus = 'present';
        }

        if ($allergyStatus === 'present' && ! in_array('allergy', $conditions, true)) {
            $conditions[] = 'allergy';
        }

        if (in_array('medication', $conditions, true) && in_array($medicationStatus, ['', 'not_asked'], true)) {
            $medicationStatus = 'present';
        }

        if ($medicationStatus === 'present' && ! in_array('medication', $conditions, true)) {
            $conditions[] = 'medication';
        }

        $this->merge([
            'none' => $this->boolean('none'),
            'conditions' => array_values($conditions),
            'allergy_status' => $allergyStatus === '' ? 'not_asked' : $allergyStatus,
            'medication_status' => $medicationStatus === '' ? 'not_asked' : $medicationStatus,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'allergy_detail.required_if' => 'Zat atau bahan pemicu wajib diisi jika ada alergi.',
            'allergy_category.required_if' => 'Kategori alergi wajib dipilih jika ada alergi.',
            'medication_detail.required_if' => 'Nama obat rutin wajib diisi jika sedang memakai obat.',
        ];
    }
}
