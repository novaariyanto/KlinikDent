<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\Concerns\ScopesFinanceBranch;
use App\Http\Requests\Finance\DoctorFeeWaiver\StoreDoctorFeeWaiverSettingRequest;
use App\Http\Requests\Finance\DoctorFeeWaiver\UpdateDoctorFeeWaiverSettingRequest;
use App\Models\DoctorFeeWaiverSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DoctorFeeWaiverSettingController extends Controller
{
    use ScopesFinanceBranch;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DoctorFeeWaiverSetting::class);

        $query = DoctorFeeWaiverSetting::query()->with(['doctor', 'branch']);
        $ids = $request->user()?->restrictedBranchIds();

        if ($ids !== null) {
            $query->where(function ($inner) use ($ids) {
                $inner->whereNull('branch_id')->orWhereIn('branch_id', $ids);
            });
        }

        if ($request->filled('doctor_user_id')) {
            $query->where('doctor_user_id', $request->integer('doctor_user_id'));
        }

        $settings = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('finance.doctor-fee-waivers.settings.index', [
            'settings' => $settings,
            'doctors' => $this->doctorOptions($request),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', DoctorFeeWaiverSetting::class);

        return view('finance.doctor-fee-waivers.settings.form', $this->formData($request, new DoctorFeeWaiverSetting([
            'is_allowed' => true,
            'require_approval' => false,
            'include_waived_in_sharing_fee' => false,
        ])));
    }

    public function store(StoreDoctorFeeWaiverSettingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()?->tenant_id;
        $data['created_by'] = $request->user()?->id;
        $data['updated_by'] = $request->user()?->id;

        $setting = DoctorFeeWaiverSetting::query()->create($data);
        activity_log('created', $setting, $data, 'Kebijakan pembebasan jasa dokter dibuat', 'doctor_fee_waiver_settings');

        return redirect()
            ->route('finance.doctor-fee-waivers.settings.index')
            ->with('success', 'Kebijakan pembebasan jasa dokter disimpan.');
    }

    public function edit(Request $request, DoctorFeeWaiverSetting $doctorFeeWaiverSetting): View
    {
        $this->authorize('update', $doctorFeeWaiverSetting);

        return view('finance.doctor-fee-waivers.settings.form', $this->formData($request, $doctorFeeWaiverSetting));
    }

    public function update(UpdateDoctorFeeWaiverSettingRequest $request, DoctorFeeWaiverSetting $doctorFeeWaiverSetting): RedirectResponse
    {
        $data = $request->validated();
        $data['updated_by'] = $request->user()?->id;
        $doctorFeeWaiverSetting->update($data);
        activity_log('updated', $doctorFeeWaiverSetting, $data, 'Kebijakan pembebasan jasa dokter diubah', 'doctor_fee_waiver_settings');

        return redirect()
            ->route('finance.doctor-fee-waivers.settings.index')
            ->with('success', 'Kebijakan pembebasan jasa dokter diperbarui.');
    }

    public function destroy(DoctorFeeWaiverSetting $doctorFeeWaiverSetting): RedirectResponse
    {
        $this->authorize('delete', $doctorFeeWaiverSetting);
        activity_log('deleted', $doctorFeeWaiverSetting, [
            'doctor_user_id' => $doctorFeeWaiverSetting->doctor_user_id,
        ], 'Kebijakan pembebasan jasa dokter dihapus', 'doctor_fee_waiver_settings');
        $doctorFeeWaiverSetting->delete();

        return redirect()
            ->route('finance.doctor-fee-waivers.settings.index')
            ->with('success', 'Kebijakan pembebasan jasa dokter dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(Request $request, DoctorFeeWaiverSetting $setting): array
    {
        return [
            'setting' => $setting,
            'doctors' => $this->doctorOptions($request),
            'branches' => $this->assignedBranches($request->user()),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function doctorOptions(Request $request)
    {
        return User::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'dentist'))
            ->orderBy('name')
            ->get();
    }
}
