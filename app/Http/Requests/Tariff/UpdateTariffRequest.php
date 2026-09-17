<?php

namespace App\Http\Requests\Tariff;

use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Tariff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTariffRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        /** @var Tariff $tariff */
        $tariff = $this->route('tariff');

        return $this->user()?->can('update', $tariff) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();

        return [
            'procedure_id' => [
                'required',
                'integer',
                Rule::exists('procedures', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'payer_id' => [
                'nullable',
                'integer',
                Rule::exists('payers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Tariff $tariff */
            $tariff = $this->route('tariff');

            $exists = Tariff::query()
                ->where('tenant_id', $this->actorTenantId() ?? $tariff->tenant_id)
                ->where('procedure_id', $this->input('procedure_id'))
                ->where('effective_date', $this->input('effective_date'))
                ->whereKeyNot($tariff->id)
                ->when(
                    $this->filled('branch_id'),
                    fn ($query) => $query->where('branch_id', $this->input('branch_id')),
                    fn ($query) => $query->whereNull('branch_id'),
                )
                ->when(
                    $this->filled('payer_id'),
                    fn ($query) => $query->where('payer_id', $this->input('payer_id')),
                    fn ($query) => $query->whereNull('payer_id'),
                )
                ->exists();

            if ($exists) {
                $validator->errors()->add('procedure_id', 'Tarif untuk kombinasi tindakan, cabang, dan penjamin pada tanggal ini sudah ada.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => $this->filled('branch_id') ? $this->input('branch_id') : null,
            'payer_id' => $this->filled('payer_id') ? $this->input('payer_id') : null,
        ]);
    }
}
