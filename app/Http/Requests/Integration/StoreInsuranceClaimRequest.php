<?php

namespace App\Http\Requests\Integration;

use App\Models\InsuranceClaim;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInsuranceClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InsuranceClaim::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->where('tenant_id', $tenantId)],
            'visit_id' => ['nullable', 'integer', Rule::exists('visits', 'id')->where('tenant_id', $tenantId)],
            'payer_id' => ['nullable', 'integer', Rule::exists('payers', 'id')->where('tenant_id', $tenantId)],
            'amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ];
    }
}
