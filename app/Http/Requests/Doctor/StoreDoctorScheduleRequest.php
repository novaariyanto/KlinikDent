<?php

namespace App\Http\Requests\Doctor;

use App\Enums\Weekday;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDoctorScheduleRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        $doctor = $this->doctor();

        return $this->user()?->can('create', DoctorSchedule::class)
            && $doctor
            && $this->user()?->can('view', $doctor);
    }

    public function doctor(): ?Doctor
    {
        $doctor = $this->route('doctor');

        if ($doctor instanceof Doctor) {
            return $doctor;
        }

        $id = (int) $this->input('doctor_id');

        return $id > 0 ? Doctor::query()->find($id) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();

        return [
            'doctor_id' => [
                Rule::requiredIf(fn () => ! $this->route('doctor')),
                'nullable',
                'integer',
                Rule::exists('doctors', 'id')->where(function ($query) use ($tenantId) {
                    if ($tenantId) {
                        $query->where('tenant_id', $tenantId);
                    }
                }),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(function ($query) use ($tenantId) {
                    if ($tenantId) {
                        $query->where('tenant_id', $tenantId);
                    }
                }),
            ],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'weekday' => ['required', Rule::enum(Weekday::class)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'room_id' => $this->filled('room_id') ? $this->input('room_id') : null,
            'start_time' => $this->normalizeTime($this->input('start_time')),
            'end_time' => $this->normalizeTime($this->input('end_time')),
        ]);
    }

    protected function normalizeTime(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return strlen($value) === 5 ? $value : substr($value, 0, 5);
    }
}
