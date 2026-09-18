<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Expense::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();
        $branchId = $this->user()?->can('branch.manage')
            ? $this->input('branch_id')
            : ($this->input('branch_id') ?: $this->user()?->branch_id);

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'cash_account_id' => [
                'required',
                'integer',
                Rule::exists('cash_accounts', 'id')->where(function ($query) use ($tenantId, $branchId) {
                    $query->where('tenant_id', $tenantId);
                    if ($branchId) {
                        $query->where('branch_id', $branchId);
                    }
                }),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();
        $ids = $user?->assignedBranchIds() ?? [];

        if ($user && ! $user->can('branch.manage') && count($ids) === 1) {
            $this->merge(['branch_id' => $ids[0]]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $branchId = (int) $this->input('branch_id');

            if ($branchId && $this->user() && ! $this->user()->canAccessBranch($branchId)) {
                $validator->errors()->add('branch_id', 'Anda tidak memiliki akses ke cabang ini.');
            }
        });
    }
}
