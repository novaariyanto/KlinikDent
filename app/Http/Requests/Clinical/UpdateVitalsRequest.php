<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVitalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('vital_sign.manage')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'blood_pressure' => ['nullable', 'string', 'max:20'],
            'blood_pressure_sys' => ['nullable', 'string', 'max:10'],
            'blood_pressure_dia' => ['nullable', 'string', 'max:10'],
            'pulse' => ['nullable', 'string', 'max:10'],
            'temperature' => ['nullable', 'string', 'max:10'],
            'respiration' => ['nullable', 'string', 'max:10'],
            'weight' => ['nullable', 'string', 'max:10'],
            'height' => ['nullable', 'string', 'max:10'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $sys = trim((string) $this->input('blood_pressure_sys', ''));
        $dia = trim((string) $this->input('blood_pressure_dia', ''));

        if ($sys !== '' || $dia !== '') {
            $this->merge([
                'blood_pressure' => trim($sys.'/'.$dia, '/'),
            ]);
        }
    }
}
