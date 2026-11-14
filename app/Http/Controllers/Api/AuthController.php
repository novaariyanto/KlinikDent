<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\MobileUserResource;
use App\Services\Mobile\MobileAuthService;
use App\Support\Mobile\ActiveBranchResolver;
use App\Support\Saas\ClinicProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(
        RegisterRequest $request,
        MobileAuthService $auth,
        ClinicProvisioningService $provisioning,
        ActiveBranchResolver $branches,
    ): JsonResponse {
        $data = $request->validated();
        $deviceName = $data['device_name'] ?? null;
        unset($data['device_name']);

        $result = $auth->register(
            $data,
            $provisioning,
            is_string($deviceName) ? $deviceName : null,
            $branches->requestedId($request->header('X-Branch-Id')),
        );

        return response()->json([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'data' => (new MobileUserResource($result['user'], $result['branch']))->toArray($request),
            'message' => 'Registrasi berhasil. Lanjutkan onboarding.',
        ]);
    }

    public function store(Request $request, MobileAuthService $auth, ActiveBranchResolver $branches): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $result = $auth->login(
            $data['email'],
            $data['password'],
            $data['device_name'] ?? null,
            (string) $request->ip(),
            $branches->requestedId($request->header('X-Branch-Id')),
        );

        return response()->json([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'data' => (new MobileUserResource($result['user'], $result['branch']))->toArray($request),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->user()?->currentAccessToken()?->delete();

        activity_log('logout', $user, [], 'Signed out', 'auth');

        return response()->json(['message' => 'Signed out']);
    }
}