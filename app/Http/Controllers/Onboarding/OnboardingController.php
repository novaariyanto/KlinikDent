<?php

namespace App\Http\Controllers\Onboarding;

use App\Enums\OnboardingTemplate;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\ConfirmRolesRequest;
use App\Http\Requests\Onboarding\SaveBranchRequest;
use App\Http\Requests\Onboarding\SaveDoctorsRequest;
use App\Http\Requests\Onboarding\SaveIntegrationsRequest;
use App\Http\Requests\Onboarding\SaveMedicinesRequest;
use App\Http\Requests\Onboarding\SaveProfileRequest;
use App\Http\Requests\Onboarding\SaveServicesRequest;
use App\Http\Requests\Onboarding\SaveStaffRequest;
use App\Http\Requests\Onboarding\SelectTemplateRequest;
use App\Models\User;
use App\Support\ClinicSettings;
use App\Support\Onboarding\OnboardingCatalog;
use App\Support\Onboarding\OnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(OnboardingService $onboarding): View
    {
        $user = $this->owner();
        $tenant = $user->tenant;
        abort_unless($tenant, 403);

        $step = $onboarding->currentStepKey($tenant);
        $settings = ClinicSettings::current((int) $tenant->id);
        $branch = $tenant->branches()->orderBy('id')->first();
        $selectedRoles = $tenant->onboarding_selected_roles ?? [];
        $template = $tenant->onboarding_template ?? OnboardingTemplate::Small;

        return view('onboarding.show', [
            'tenant' => $tenant,
            'step' => $step,
            'stepNumber' => OnboardingCatalog::stepNumber($step),
            'totalSteps' => OnboardingCatalog::TOTAL_STEPS,
            'progress' => $tenant->onboardingProgressPercent(),
            'settings' => $settings,
            'branch' => $branch,
            'selectedRoles' => $selectedRoles,
            'template' => $template,
            'recommendedRoles' => OnboardingCatalog::recommendedRoles($template),
            'otherRoles' => OnboardingCatalog::otherRoles($template),
            'summary' => $step === 'review' ? $onboarding->summary($tenant) : null,
            'hasCustomLogo' => ClinicSettings::hasCustomLogo((int) $tenant->id),
            'logoUrl' => ClinicSettings::logoUrl('dark', (int) $tenant->id),
        ]);
    }

    public function step(string $step, Request $request, OnboardingService $onboarding): RedirectResponse
    {
        $user = $this->owner();
        abort_unless(in_array($step, OnboardingCatalog::STEPS, true), 404);

        try {
            match ($step) {
                'template' => $onboarding->saveTemplate($user, app(SelectTemplateRequest::class)->validated()),
                'roles' => $onboarding->saveRoles($user, app(ConfirmRolesRequest::class)->validated()),
                'profile' => $onboarding->saveProfile(
                    $user,
                    app(SaveProfileRequest::class)->validated(),
                    $request->file('logo')
                ),
                'branch' => $onboarding->saveBranch($user, app(SaveBranchRequest::class)->validated()),
                'doctors' => $onboarding->saveDoctors($user, app(SaveDoctorsRequest::class)->validated()),
                'staff' => $onboarding->saveStaff($user, app(SaveStaffRequest::class)->validated()),
                'services' => $onboarding->saveServices($user, app(SaveServicesRequest::class)->validated()),
                'medicines' => $onboarding->saveMedicines($user, app(SaveMedicinesRequest::class)->validated()),
                'integrations' => $onboarding->saveIntegrations($user, app(SaveIntegrationsRequest::class)->validated()),
                default => abort(404),
            };
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return redirect()->route('onboarding.show');
    }

    public function skip(string $step, OnboardingService $onboarding): RedirectResponse
    {
        $onboarding->skipStep($this->owner(), $step);

        return redirect()->route('onboarding.show');
    }

    public function back(OnboardingService $onboarding): RedirectResponse
    {
        $onboarding->goBack($this->owner());

        return redirect()->route('onboarding.show');
    }

    public function complete(OnboardingService $onboarding): RedirectResponse
    {
        $onboarding->complete($this->owner());

        return redirect()
            ->route('onboarding.done')
            ->with('onboarding_just_completed', true);
    }

    public function done(): View|RedirectResponse
    {
        $user = $this->owner();
        $tenant = $user->tenant;
        abort_unless($tenant, 403);

        if ($tenant->needsOnboarding()) {
            return redirect()->route('onboarding.show');
        }

        return view('onboarding.done', ['tenant' => $tenant]);
    }

    protected function owner(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        abort_unless($user->hasRole(RoleName::Owner) && $user->tenant_id, 403);

        return $user;
    }
}
