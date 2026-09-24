<?php

namespace App\Models;

use App\Enums\LabOrderStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DentalLabOrder extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'visit_id',
        'work_type',
        'tooth_numbers',
        'shade',
        'vendor',
        'due_date',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'visit_id' => 'integer',
            'due_date' => 'date',
            'status' => LabOrderStatus::class,
            'created_by' => 'integer',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
