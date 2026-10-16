<?php

namespace App\Http\Controllers\Finance;

use App\Enums\SharingFeeCalcType;
use App\Enums\SharingFeeMaterialDeductionType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\Concerns\ScopesFinanceBranch;
use App\Http\Requests\Finance\SharingFee\PreviewSharingFeeRuleRequest;
use App\Http\Requests\Finance\SharingFee\StoreSharingFeeRuleRequest;
use App\Http\Requests\Finance\SharingFee\UpdateSharingFeeRuleRequest;
use App\Models\Procedure;
use App\Models\SharingFeeRule;
use App\Models\User;
use App\Support\Finance\SharingFee\SharingFeeCalculator;
use App\Support\Finance\SharingFee\SharingFeeException;
use App\Support\Finance\SharingFee\SharingFeeRuleResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SharingFeeRuleController extends Controller
{
    use ScopesFinanceBranch;

    public function __construct(
        protected SharingFeeRuleResolver $resolver,
        protected SharingFeeCalculator $calculator,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SharingFeeRule::class);

        $query = SharingFeeRule::query()->with(['doctor', 'branch', 'procedure']);
        $ids = $request->user()?->restrictedBranchIds();

        if ($ids !== null) {
            $query->where(function ($inner) use ($ids) {
                $inner->whereNull('branch_id')->orWhereIn('branch_id', $ids);
            });
        }

        if ($request->input('doctor_user_id') === SharingFeeRuleResolver::SCOPE_ALL) {
            $query->whereNull('doctor_user_id');
        } elseif ($request->filled('doctor_user_id')) {
            $query->where('doctor_user_id', $request->integer('doctor_user_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $rules = $query
            ->orderByRaw('CASE WHEN doctor_user_id IS NULL AND branch_id IS NULL AND procedure_id IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('finance.sharing-fees.rules.index', [
            'rules' => $rules,
            'doctors' => $this->doctorOptions($request),
            'branches' => $this->assignedBranches($request->user()),
            'scopeAll' => SharingFeeRuleResolver::SCOPE_ALL,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SharingFeeRule::class);

        return view('finance.sharing-fees.rules.form', $this->formData($request, new SharingFeeRule([
            'doctor_user_id' => null,
            'branch_id' => null,
            'procedure_id' => null,
            'calc_type' => SharingFeeCalcType::Percentage,
            'material_deduction_type' => SharingFeeMaterialDeductionType::None,
            'priority' => 100,
            'effective_from' => now()->toDateString(),
            'is_active' => true,
            'rate' => '40',
            'material_deduction_value' => '0',
        ])));
    }

    public function store(StoreSharingFeeRuleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()->tenant_id;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        try {
            $rule = DB::transaction(function () use ($data) {
                $rule = new SharingFeeRule($data);
                $this->resolver->assertNoConflictingPriority($rule);
                $rule->save();

                return $rule;
            });
        } catch (SharingFeeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        activity_audit('created', $rule, [], 'Aturan sharing fee dibuat.', 'sharing_fee', [
            'coverage' => $rule->coverageSummary(),
            'priority' => $rule->priority,
        ], $request->user());

        return redirect()
            ->route('finance.sharing-fees.rules.index')
            ->with('success', 'Aturan sharing fee berhasil disimpan.');
    }

    public function edit(Request $request, SharingFeeRule $sharingFeeRule): View
    {
        $this->authorize('update', $sharingFeeRule);

        return view('finance.sharing-fees.rules.form', $this->formData($request, $sharingFeeRule));
    }

    public function update(UpdateSharingFeeRuleRequest $request, SharingFeeRule $sharingFeeRule): RedirectResponse
    {
        $this->authorize('update', $sharingFeeRule);

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        try {
            DB::transaction(function () use ($sharingFeeRule, $data) {
                $sharingFeeRule->fill($data);
                $this->resolver->assertNoConflictingPriority($sharingFeeRule, $sharingFeeRule->id);
                $sharingFeeRule->save();
            });
        } catch (SharingFeeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        activity_audit('updated', $sharingFeeRule, [], 'Aturan sharing fee diperbarui.', 'sharing_fee', [
            'coverage' => $sharingFeeRule->coverageSummary(),
        ], $request->user());

        return redirect()
            ->route('finance.sharing-fees.rules.index')
            ->with('success', 'Aturan sharing fee berhasil diperbarui.');
    }

    public function destroy(Request $request, SharingFeeRule $sharingFeeRule): RedirectResponse
    {
        $this->authorize('delete', $sharingFeeRule);

        $sharingFeeRule->update([
            'is_active' => false,
            'updated_by' => $request->user()->id,
        ]);

        activity_audit('deactivated', $sharingFeeRule, [], 'Aturan sharing fee dinonaktifkan.', 'sharing_fee', [], $request->user());

        return redirect()
            ->route('finance.sharing-fees.rules.index')
            ->with('success', 'Aturan sharing fee dinonaktifkan.');
    }

    public function preview(PreviewSharingFeeRuleRequest $request): JsonResponse
    {
        $result = $this->resolver->preview(
            (int) $request->user()->tenant_id,
            $request->input('doctor_user_id') !== null ? (int) $request->input('doctor_user_id') : null,
            $request->input('branch_id') !== null ? (int) $request->input('branch_id') : null,
            $request->input('procedure_id') !== null ? (int) $request->input('procedure_id') : null,
            $request->date('on_date')->toDateString(),
            (string) $request->input('gross_base'),
            (int) $request->input('quantity'),
            $this->calculator,
        );

        return response()->json($result);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(Request $request, SharingFeeRule $rule): array
    {
        $scopeAll = SharingFeeRuleResolver::SCOPE_ALL;

        return [
            'rule' => $rule,
            'doctors' => $this->doctorOptions($request),
            'branches' => $this->assignedBranches($request->user()),
            'procedures' => Procedure::query()->where('is_active', true)->orderBy('name')->get(),
            'calcTypes' => SharingFeeCalcType::options(),
            'deductionTypes' => SharingFeeMaterialDeductionType::options(),
            'scopeAll' => $scopeAll,
            'doctorOptions' => [$scopeAll => 'Semua dokter'] + $this->doctorOptions($request)->mapWithKeys(fn ($d) => [$d->id => $d->name])->all(),
            'branchOptions' => [$scopeAll => 'Semua cabang'] + $this->assignedBranches($request->user())->mapWithKeys(fn ($b) => [$b->id => $b->name])->all(),
            'procedureOptions' => [$scopeAll => 'Semua tindakan'] + Procedure::query()->where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.$p->name])->all(),
            'previewUrl' => route('finance.sharing-fees.rules.preview'),
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
