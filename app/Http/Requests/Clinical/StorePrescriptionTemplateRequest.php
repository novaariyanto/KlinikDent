<?php

namespace App\Http\Requests\Clinical;

use App\Enums\PrescriptionItemKind;
use App\Enums\VisitStatus;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\PrescriptionTemplate;
use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePrescriptionTemplateRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('prescription.create')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();
        $userId = $this->user()?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('prescription_templates', 'name')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)->where('created_by', $userId)
                ),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.kind' => ['nullable', Rule::in([PrescriptionItemKind::Ready->value, PrescriptionItemKind::Compound->value])],
            'items.*.medication_type' => ['nullable', 'string', 'max:2'],
            'items.*.medicine_id' => [
                'nullable',
                'integer',
                Rule::exists('medicines', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true)),
            ],
            'items.*.ingredients' => ['nullable', 'array'],
            'items.*.ingredients.*.name' => ['nullable', 'string', 'max:120'],
            'items.*.ingredients.*.medicine_id' => ['nullable', 'integer'],
            'items.*.ingredients.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.ingredients.*.role' => ['nullable', Rule::in(['active', 'additive'])],
            'items.*.ingredients.*.additive_kind' => ['nullable', 'string', 'max:30'],
            'items.*.ingredients.*.quantity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'items.*.ingredients.*.per_dose_quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'items.*.ingredients.*.strength_value' => ['nullable', 'numeric'],
            'items.*.ingredients.*.strength_unit' => ['nullable', 'string', 'max:30'],
            'items.*.dosage' => ['nullable', 'string', 'max:160'],
            'items.*.frequency' => ['nullable', 'string', 'max:160'],
            'items.*.duration' => ['nullable', 'string', 'max:160'],
            'items.*.course' => ['nullable', 'string', 'max:100'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Nama template ini sudah ada pada akun Anda.',
            'items.required' => 'Template membutuhkan minimal satu obat.',
            'items.*.medicine_id.required' => 'Pilih obat untuk setiap baris template.',
            'items.*.quantity.required' => 'Jumlah obat wajib diisi.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('items', []) as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $compound = ($row['kind'] ?? PrescriptionItemKind::Ready->value) === PrescriptionItemKind::Compound->value;
                if (! $compound && ! filled($row['medicine_id'] ?? null)) {
                    $validator->errors()->add('items.'.$index.'.medicine_id', 'Pilih obat untuk setiap baris template.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(function ($row) {
                if (! is_array($row)) {
                    return false;
                }

                if (($row['kind'] ?? '') === PrescriptionItemKind::Compound->value) {
                    return collect($row['ingredients'] ?? [])->contains(fn ($ingredient) => is_array($ingredient) && filled($ingredient['name'] ?? null));
                }

                return filled($row['medicine_id'] ?? null);
            })
            ->map(function (array $row) {
                $row['unit'] = filled($row['unit'] ?? null) ? trim((string) $row['unit']) : null;
                $row['course'] = filled($row['course'] ?? null) ? trim((string) $row['course']) : null;
                $row['kind'] = ($row['kind'] ?? '') === PrescriptionItemKind::Compound->value
                    ? PrescriptionItemKind::Compound->value
                    : PrescriptionItemKind::Ready->value;

                return $row;
            })
            ->values()
            ->all();

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'items' => $items,
        ]);
    }
}
