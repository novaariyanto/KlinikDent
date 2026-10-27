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
            'torus_mandibularis' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::torusMandibularis()))],
            'palate' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::palate()))],
            'gingiva' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::gingiva()))],
            'mucosa' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::mucosa()))],
            'extraoral' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::finding()))],
            'extraoral_note' => ['nullable', 'string', 'max:2000', 'required_if:extraoral,abnormal'],
            'intraoral' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::finding()))],
            'intraoral_note' => ['nullable', 'string', 'max:2000', 'required_if:intraoral,abnormal'],
            'supernumerary' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::presence()))],
            'supernumerary_teeth' => ['nullable', 'string', 'max:100', 'required_if:supernumerary,present'],
            'diastema' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::presence()))],
            'diastema_elements' => ['nullable', 'string', 'max:100', 'required_if:diastema,present'],
            'anomaly' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::presence()))],
            'anomaly_description' => ['nullable', 'string', 'max:255', 'required_if:anomaly,present'],
            'habits' => ['nullable', 'string', 'max:2000'],
            'ohis' => ['nullable', 'array'],
            'ohis.debris_index' => ['nullable', 'numeric', 'min:0', 'max:3', 'required_with:ohis.calculus_index'],
            'ohis.calculus_index' => ['nullable', 'numeric', 'min:0', 'max:3', 'required_with:ohis.debris_index'],
            'ohis.total' => ['nullable', 'numeric', 'min:0', 'max:6'],
            'ohis.interpretation' => ['nullable', 'string', Rule::in(['baik', 'sedang', 'buruk'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'extraoral_note.required_if' => 'Temuan ekstraoral wajib diisi jika ada kelainan.',
            'intraoral_note.required_if' => 'Temuan intraoral wajib diisi jika ada kelainan.',
            'diastema_elements.required_if' => 'Elemen diastema wajib diisi jika diastema ada.',
            'supernumerary_teeth.required_if' => 'Nomor gigi supernumerary wajib diisi jika temuan ada.',
            'anomaly_description.required_if' => 'Deskripsi anomali wajib diisi jika anomali ada.',
            'ohis.debris_index.required_with' => 'Debris Index dan Calculus Index harus diisi bersamaan.',
            'ohis.calculus_index.required_with' => 'Debris Index dan Calculus Index harus diisi bersamaan.',
            'ohis.debris_index.min' => 'Debris Index minimal 0.',
            'ohis.debris_index.max' => 'Debris Index maksimal 3.',
            'ohis.calculus_index.min' => 'Calculus Index minimal 0.',
            'ohis.calculus_index.max' => 'Calculus Index maksimal 3.',
        ];
    }
}
