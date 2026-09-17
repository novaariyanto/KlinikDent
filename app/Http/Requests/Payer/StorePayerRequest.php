<?php

namespace App\Http\Requests\Payer;

use App\Enums\PayerType;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Payer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayerRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Payer::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();

        return [
            'type' => ['required', Rule::enum(PayerType::class)],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('payers', 'name')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'contract_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
