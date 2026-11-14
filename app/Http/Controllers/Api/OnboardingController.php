<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Exceptions\MobileApiException;
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
use App\Http\Resources\OnboardingStateResource;
use App\Models\User;
use App\Support\Onboarding\OnboardingCatalog;
use App\Support\Onboarding\OnboardingService;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function show(): OnboardingStateResource
    {
        return new OnboardingStateResource($this->owner());
    }

    public function step(string $step, Request $request, OnboardingService $onboarding): OnboardingStateResource
    {
        $user = $this->ownerInProgress();
        $this->knownStep($step);

        if ($step === 'review') {
            abort(404);
        }

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

        return new OnboardingStateResource($user->fresh());
    }

    public function skip(string $step, OnboardingService $onboarding): OnboardingStateResource
    {
        $user = $this->ownerInProgress();
        $this->knownStep($step);
        $onboarding->skipStep($user, $step);

        return new OnboardingStateResource($user->fresh());
    }

    public function back(OnboardingService $onboarding): OnboardingStateResource
    {
        $user = $this->ownerInProgress();
        $onboarding->goBack($user);

        return new OnboardingStateResource($user->fresh());
    }

    public function complete(OnboardingService $onboarding): OnboardingStateResource
    {
        $user = $this->ownerInProgress();
        $onboarding->complete($user);

        return new OnboardingStateResource($user->fresh());
    }

    protected function owner(): User
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->hasRole(RoleName::Owner) || ! $user->tenant_id) {
            throw new MobileApiException(
                'Hanya owner klinik yang dapat menjalankan onboarding.',
                'owner_only',
                403,
            );
        }

        return $user;
    }

    protected function ownerInProgress(): User
    {
        $user = $this->owner();

        if (! $user->tenant?->needsOnboarding()) {
            throw new MobileApiException(
                'Onboarding klinik sudah selesai.',
                'onboarding_completed',
                422,
            );
        }

        return $user;
    }

    protected function knownStep(string $step): void
    {
        abort_unless(in_array($step, OnboardingCatalog::STEPS, true), 404);
    }
}