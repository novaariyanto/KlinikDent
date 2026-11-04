<?php

namespace App\Http\Requests\Finance\DoctorFeeWaiver;

use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\DoctorFeeWaiverSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDoctorFeeWaiverSettingRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        return $this->user()?->can('create', DoctorFeeWaiverSetting::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();

        return [
            'doctor_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'is_allowed' => ['nullable', 'boolean'],
            'require_approval' => ['nullable', 'boolean'],
            'include_waived_in_sharing_fee' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $exists = DoctorFeeWaiverSetting::query()
                ->where('tenant_id', $this->actorTenantId())
                ->where('doctor_user_id', $this->input('doctor_user_id'))
                ->when(
                    $this->filled('branch_id'),
                    fn ($query) => $query->where('branch_id', $this->input('branch_id')),
                    fn ($query) => $query->whereNull('branch_id'),
                )
                ->exists();

            if ($exists) {
                $validator->errors()->add('doctor_user_id', 'Kebijakan untuk dokter dan cabang ini sudah ada.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => $this->filled('branch_id') ? $this->input('branch_id') : null,
            'is_allowed' => $this->boolean('is_allowed'),
            'require_approval' => $this->boolean('require_approval'),
            'include_waived_in_sharing_fee' => $this->boolean('include_waived_in_sharing_fee'),
        ]);
    }
}
