<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Support\Clinical\CareExamOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDentalExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('examination.manage')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'occlusion' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::occlusion()))],
            'torus_palatinus' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::torus()))],
            'torus_mandibularis' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::torus()))],
            'palate' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::palate()))],
            'gingiva' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::gingiva()))],
            'mucosa' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::mucosa()))],
            'extraoral' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::finding()))],
            'extraoral_note' => ['nullable', 'string', 'max:2000'],
            'intraoral' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::finding()))],
            'intraoral_note' => ['nullable', 'string', 'max:2000'],
            'supernumerary' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::presence()))],
            'supernumerary_teeth' => ['nullable', 'string', 'max:100'],
            'diastema' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::presence()))],
            'diastema_elements' => ['nullable', 'string', 'max:100'],
            'anomaly' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::presence()))],
            'anomaly_description' => ['nullable', 'string', 'max:255'],
            'habits' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
