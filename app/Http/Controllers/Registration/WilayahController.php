<?php

namespace App\Http\Controllers\Registration;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\Indonesia\WilayahService;
use App\Support\Integrations\IntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WilayahController extends Controller
{
    public function __construct(
        protected WilayahService $wilayah,
        protected IntegrationService $integrations,
    ) {}

    public function cities(Request $request): JsonResponse
    {
        abort_unless($this->canUseWilayah(), 403);

        $code = (string) $request->query('province_code', '');

        return response()->json([
            'options' => $this->wilayah->cities($code),
        ]);
    }

    public function districts(Request $request): JsonResponse
    {
        abort_unless($this->canUseWilayah(), 403);

        $code = (string) $request->query('city_code', '');

        return response()->json([
            'options' => $this->wilayah->districts($code),
        ]);
    }

    public function villages(Request $request): JsonResponse
    {
        abort_unless($this->canUseWilayah(), 403);

        $code = (string) $request->query('district_code', '');
        $villageCode = (string) $request->query('village_code', '');
        $options = $this->wilayah->villages($code);
        $source = 'laravolt';

        $branchId = (int) $request->query('branch_id', 0);
        $user = $request->user();

        if ($branchId > 0 && $user?->tenant_id && $user->canAccessBranch($branchId)) {
            $branch = Branch::query()->find($branchId);
            if ($branch && (int) $branch->tenant_id === (int) $user->tenant_id) {
                $ssOptions = $this->integrations->masterWilayahVillages(
                    (int) $user->tenant_id,
                    $branchId,
                    $code,
                );

                if ($ssOptions !== []) {
                    $options = $ssOptions;
                    $source = 'satusehat';
                }
            }
        }

        return response()->json([
            'options' => $options,
            'source' => $source,
            'postal_code' => $this->wilayah->villagePostalCode($villageCode ?: null),
            'city_name' => $this->wilayah->cityName((string) $request->query('city_code', '')),
        ]);
    }

    public function fromNik(Request $request): JsonResponse
    {
        abort_unless($this->canUseWilayah(), 403);

        $result = $this->wilayah->fromNik((string) $request->query('nik', $request->input('nik', '')));

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    protected function canUseWilayah(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('patient.view')
            || $user->can('patient.create')
            || $user->can('patient.update')
            || $user->can('registration.create')
            || $user->can('visit.view');
    }
}
