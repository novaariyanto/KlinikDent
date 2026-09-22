<?php

namespace App\Http\Controllers\Auth;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Support\Saas\ClinicProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(ClinicProvisioningService $provisioning): View
    {
        return view('auth.register', [
            'packages' => $provisioning->publicPackages(),
            'defaultPackageId' => $provisioning->defaultPublicPackage()?->id,
        ]);
    }

    public function store(RegisterRequest $request, ClinicProvisioningService $provisioning): RedirectResponse
    {
        $data = $request->validated();
        $packageId = $data['package_id'] ?? $provisioning->defaultPublicPackage()?->id;

        $result = $provisioning->provision(
            name: $data['name'],
            subdomain: $data['subdomain'],
            ownerName: $data['owner_name'],
            ownerEmail: $data['owner_email'],
            ownerPassword: $data['password'],
            status: TenantStatus::Trial,
            packageId: $packageId,
            startTrial: true,
        );

        activity_log('registered', $result['tenant'], [
            'subdomain' => $result['tenant']->subdomain,
            'owner_email' => $result['owner']->email,
        ], 'Public registration for '.$result['tenant']->name, 'auth', $result['owner']);

        Auth::login($result['owner']);
        $request->session()->regenerate();

        return redirect()->route('onboarding.show');
    }
}
