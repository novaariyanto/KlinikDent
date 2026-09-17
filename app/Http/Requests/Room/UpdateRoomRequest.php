<?php

namespace App\Http\Requests\Room;

use App\Enums\RoomType;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        /** @var Room $room */
        $room = $this->route('room');

        return $this->user()?->can('update', $room) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Room $room */
        $room = $this->route('room');
        $tenantId = $this->actorTenantId();

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('rooms', 'name')
                    ->where(fn ($query) => $query->where('branch_id', $this->input('branch_id')))
                    ->ignore($room->id),
            ],
            'type' => ['required', Rule::enum(RoomType::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
