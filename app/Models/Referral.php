<?php

namespace App\Models;

use App\Models\Concerns\ScopedByVisitTenant;
use Database\Factories\ReferralFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    /** @use HasFactory<ReferralFactory> */
    use HasFactory, ScopedByVisitTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'visit_id',
        'referred_to',
        'reason',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_id' => 'integer',
        ];
    }
}
