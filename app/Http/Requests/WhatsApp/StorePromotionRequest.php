<?php

namespace App\Http\Requests\WhatsApp;

use App\Http\Requests\Concerns\ResolvesActorTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('whatsapp.promotions.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();

        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'whatsapp_template_name' => ['nullable', 'string', 'max:100'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_enabled' => $this->boolean('is_enabled'),
            'branch_id' => $this->filled('branch_id') ? $this->input('branch_id') : null,
        ]);
    }
}
