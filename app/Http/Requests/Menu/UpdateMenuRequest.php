<?php

namespace App\Http\Requests\Menu;

use App\Enums\MenuScope;
use App\Enums\MenuType;
use App\Enums\UserStatus;
use App\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Menu $menu */
        $menu = $this->route('menu');

        return $this->user()?->can('update', $menu) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Menu $menu */
        $menu = $this->route('menu');

        return [
            'title' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:120', Rule::unique('menus', 'name')->ignore($menu->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'scope' => ['required', Rule::enum(MenuScope::class)],
            'icon' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::enum(MenuType::class)],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:menus,id',
                Rule::notIn([$menu->id, ...$menu->descendantIds()]),
            ],
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
