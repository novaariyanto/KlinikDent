<?php

namespace App\Http\Requests\Menu;

use App\Enums\MenuScope;
use App\Enums\MenuType;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('menus.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:120', 'unique:menus,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'scope' => ['required', Rule::enum(MenuScope::class)],
            'icon' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::enum(MenuType::class)],
            'parent_id' => ['nullable', 'integer', 'exists:menus,id'],
            'route_name' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'permission' => ['nullable', 'string', 'exists:permissions,name'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', Rule::enum(UserStatus::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('type') === MenuType::Heading->value && $this->input('parent_id')) {
                $validator->errors()->add('parent_id', 'Heading menus cannot have a parent.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->input('parent_id') ?: null,
            'permission' => $this->input('permission') ?: null,
            'route_name' => $this->input('route_name') ?: null,
            'url' => $this->input('url') ?: null,
            'icon' => $this->input('icon') ?: null,
            'name' => $this->input('name') ?: null,
            'description' => $this->input('description') ?: null,
        ]);
    }
}
