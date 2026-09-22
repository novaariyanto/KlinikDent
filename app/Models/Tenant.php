<?php

namespace App\Models;

use App\Enums\OnboardingStatus;
use App\Enums\OnboardingTemplate;
use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'subdomain',
        'status',
        'plan_id',
        'onboarding_status',
        'onboarding_template',
        'onboarding_current_step',
        'onboarding_selected_roles',
        'onboarding_completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'plan_id' => 'integer',
            'onboarding_status' => OnboardingStatus::class,
            'onboarding_template' => OnboardingTemplate::class,
            'onboarding_current_step' => 'integer',
            'onboarding_selected_roles' => 'array',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return BelongsTo<SaasPackage, $this>
     */
    public function package(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SaasPackage::class, 'plan_id');
    }

    /**
     * @return HasMany<TenantIntegration, $this>
     */
    public function integrations(): HasMany
    {
        return $this->hasMany(TenantIntegration::class);
    }

    /**
     * @return HasMany<SaasSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(SaasSubscription::class);
    }

    /**
     * @return HasMany<SaasInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SaasInvoice::class);
    }

    public function currentSubscription(): ?SaasSubscription
    {
        return $this->subscriptions()
            ->with('package')
            ->latest('id')
            ->first();
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active || $this->status === TenantStatus::Trial;
    }

    public function needsOnboarding(): bool
    {
        return $this->onboarding_status !== OnboardingStatus::Completed;
    }

    public function onboardingProgressPercent(): int
    {
        $step = max(1, min(10, (int) $this->onboarding_current_step));

        if ($this->onboarding_status === OnboardingStatus::Completed) {
            return 100;
        }

        return (int) round(($step / 10) * 100);
    }
}
