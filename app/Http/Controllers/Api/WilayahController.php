<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Registration\WilayahController as RegistrationWilayahController;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WilayahController extends Controller
{
    public function __construct(protected RegistrationWilayahController $wilayah) {}

    public function cities(Request $request): JsonResponse
    {
        $this->authorizeWilayah();

        return $this->present($this->wilayah->cities($request));
    }

    public function districts(Request $request): JsonResponse
    {
        $this->authorizeWilayah();

        return $this->present($this->wilayah->districts($request));
    }

    public function villages(Request $request): JsonResponse
    {
        $this->authorizeWilayah();

        return $this->present($this->wilayah->villages($request));
    }

    public function fromNik(Request $request): JsonResponse
    {
        $this->authorizeWilayah();

        return $this->present($this->wilayah->fromNik($request));
    }

    protected function authorizeWilayah(): void
    {
        $user = request()->user();
        $allowed = $user && (
            $user->can('patient.view')
            || $user->can('patient.create')
            || $user->can('patient.update')
            || $user->can('registration.create')
            || $user->can('visit.view')
        );

        if (! $allowed) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    protected function present(JsonResponse $response): JsonResponse
    {
        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $payload = $response->getData(true);

        if (! is_array($payload) || array_key_exists('data', $payload)) {
            return $response;
        }

        return response()->json([
            'data' => $payload,
        ], $response->getStatusCode());
    }
}
