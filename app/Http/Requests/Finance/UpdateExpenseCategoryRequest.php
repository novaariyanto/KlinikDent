<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        $category = $this->route('expenseCategory');

        return $category instanceof ExpenseCategory
            && ($this->user()?->can('update', $category) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();
        $category = $this->route('expenseCategory');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('expense_categories', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($category instanceof ExpenseCategory ? $category->id : null),
            ],
        ];
    }
}
