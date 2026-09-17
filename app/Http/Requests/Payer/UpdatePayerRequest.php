<?php

namespace App\Http\Requests\Payer;

use App\Enums\PayerType;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Payer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayerRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        /** @var Payer $payer */
        $payer = $this->route('payer');

        return $this->user()?->can('update', $payer) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Payer $payer */
        $payer = $this->route('payer');
        $tenantId = $this->actorTenantId() ?? $payer->tenant_id;

        return [
            'type' => ['required', Rule::enum(PayerType::class)],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('payers', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($payer->id),
            ],
            'contract_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
