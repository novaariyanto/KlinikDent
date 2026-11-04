<?php

namespace App\Http\Requests\Clinical;

use App\Enums\DiagnosisKind;
use App\Enums\DiagnosisRank;
use App\Enums\MedicalLetterType;
use App\Enums\VisitStatus;
use App\Models\MedicalLetter;
use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicalLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('visit');

        if (! $visit instanceof Visit) {
            $letter = $this->route('letter');
            $visit = $letter instanceof MedicalLetter ? $letter->visit : null;
        }

        return $visit instanceof Visit
            && $this->user()?->can('medical_record.update')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(MedicalLetterType::class)],
            'issued_on' => ['required', 'date'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'address_override' => ['nullable', 'string', 'max:500'],
            'diagnosis_ids' => ['nullable', 'array'],
            'diagnosis_ids.*' => ['integer'],
            'diagnosis_scope' => ['nullable', Rule::in(['selected'])],
            'diagnoses' => ['nullable', 'array'],
            'diagnoses.*.id' => ['nullable', 'integer'],
            'diagnoses.*.selected' => ['nullable', 'boolean'],
            'diagnoses.*.kind' => ['nullable', Rule::enum(DiagnosisKind::class)],
            'diagnoses.*.rank' => ['nullable', Rule::enum(DiagnosisRank::class)],
            'diagnoses.*.code' => ['nullable', 'string', 'max:50'],
            'diagnoses.*.description' => ['nullable', 'string', 'max:255'],
            'diagnoses.*.clinical_note' => ['nullable', 'string', 'max:1000'],
            'rest_from' => ['required_if:type,sick', 'nullable', 'date'],
            'rest_until' => ['required_if:type,sick', 'nullable', 'date', 'after_or_equal:rest_from'],
            'rest_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'condition_note' => ['nullable', 'string', 'max:2000'],
            'healthy_for' => ['required_if:type,healthy', 'nullable', Rule::in(['pekerjaan', 'sekolah', 'pendaftaran', 'lainnya'])],
            'exam_result' => ['nullable', 'string', 'max:2000'],
            'conclusion' => ['nullable', 'string', 'max:2000'],
            'complaint' => ['nullable', 'string', 'max:2000'],
            'radiograph_date' => ['required_if:type,radiograph', 'nullable', 'date'],
            'radiograph_type' => ['required_if:type,radiograph', 'nullable', Rule::in(['periapikal', 'panoramik', 'sefalometri', 'oklusal', 'bitewing', 'lainnya'])],
            'radiograph_region' => ['nullable', 'string', 'max:160'],
            'radiograph_finding' => ['nullable', 'string', 'max:2000'],
            'radiograph_conclusion' => ['nullable', 'string', 'max:2000'],
            'procedures_note' => ['nullable', 'string', 'max:2000'],
            'treatment_date' => ['required_if:type,treatment', 'nullable', 'date'],
            'follow_up' => ['nullable', 'string', 'max:2000'],
            'control_date' => ['required_if:type,control', 'nullable', 'date'],
            'control_reason' => ['nullable', 'string', 'max:2000'],
            'doctor_note' => ['nullable', 'string', 'max:2000'],
            'title' => ['required_if:type,other', 'nullable', 'string', 'max:160'],
            'revises_id' => ['nullable', 'integer', 'exists:medical_letters,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rest_from.required_if' => 'Tanggal mulai sakit wajib diisi untuk surat sakit.',
            'rest_until.required_if' => 'Tanggal selesai istirahat wajib diisi untuk surat sakit.',
            'rest_until.after_or_equal' => 'Tanggal selesai istirahat tidak boleh sebelum tanggal mulai.',
            'healthy_for.required_if' => 'Keperluan surat sehat wajib dipilih.',
            'treatment_date.required_if' => 'Tanggal perawatan wajib diisi.',
            'control_date.required_if' => 'Tanggal kontrol wajib diisi.',
            'title.required_if' => 'Judul surat wajib diisi.',
            'radiograph_date.required_if' => 'Tanggal pemeriksaan rontgen wajib diisi.',
            'radiograph_type.required_if' => 'Jenis pemeriksaan rontgen wajib dipilih.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('diagnoses', []) as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                if (! empty($row['selected']) && ! filled($row['description'] ?? null)) {
                    $validator->errors()->add('diagnoses.'.$index.'.description', 'Diagnosa sementara wajib dipilih dari ICD-10.');
                }
            }
        });
    }
}
