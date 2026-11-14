<?php

namespace App\Http\Controllers\Clinical;

use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\SavePrescriptionAsTemplateRequest;
use App\Http\Requests\Clinical\StorePrescriptionTemplateRequest;
use App\Http\Requests\Clinical\UpdatePrescriptionTemplateRequest;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\PrescriptionTemplate;
use App\Models\User;
use App\Models\Visit;
use App\Support\Clinical\MedicalRecordLock;
use App\Support\Clinical\PrescriptionTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class PrescriptionTemplateController extends Controller
{
    public function __construct(protected PrescriptionTemplateService $templates) {}

    public function store(StorePrescriptionTemplateRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $template = $this->templates->create(
            $request->user(),
            $request->validated('name'),
            $request->validated('items'),
        );

        activity_log('created', $template, ['name' => $template->name], 'Template resep '.$template->name.' disimpan.', 'prescriptions');

        return $this->respondTemplate($visit, $request->user(), 'Template resep disimpan.');
    }

    public function update(UpdatePrescriptionTemplateRequest $request, Visit $visit, PrescriptionTemplate $prescriptionTemplate): RedirectResponse|JsonResponse
    {
        $template = $this->templates->update(
            $prescriptionTemplate,
            $request->validated('name'),
            $request->validated('items'),
        );

        activity_log('updated', $template, ['name' => $template->name], 'Template resep '.$template->name.' diubah.', 'prescriptions');

        return $this->respondTemplate($visit, $request->user(), 'Template resep diperbarui.');
    }

    public function destroy(Visit $visit, PrescriptionTemplate $prescriptionTemplate): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $visit);
        abort_unless($requestUser = request()->user(), 403);
        abort_unless($requestUser->can('prescription.create'), 403);
        abort_unless($prescriptionTemplate->canBeManagedBy($requestUser), 403);

        $this->templates->delete($prescriptionTemplate);

        return $this->respondTemplate($visit, $requestUser, 'Template resep dihapus.');
    }

    public function apply(Visit $visit, PrescriptionTemplate $prescriptionTemplate): RedirectResponse|JsonResponse
    {
        $user = request()->user();
        abort_unless($user?->can('view', $visit), 403);
        abort_unless($user?->can('prescription.create'), 403);
        abort_unless($visit->status !== VisitStatus::Cancelled, 403);

        try {
            $result = $this->templates->apply($visit, $user, $prescriptionTemplate);
        } catch (ValidationException $exception) {
            if (request()->wantsJson() || request()->ajax()) {
                throw $exception;
            }

            return redirect()
                ->route('care.show', ['visit' => $visit, 'tab' => 'resep'])
                ->withErrors($exception->errors());
        }

        $message = 'Template diterapkan ke resep draft. Periksa dan ubah item sebelum dikirim.';
        if ($result['skipped'] !== []) {
            $message .= ' Obat tidak aktif dilewati: '.implode(', ', $result['skipped']).'.';
        }

        if (request()->wantsJson() || request()->ajax()) {
            $visit->load([
                'patient',
                'prescriptions.items.medicine',
                'prescriptions.items.ingredients.medicine',
                'prescriptions.doctor',
            ]);

            return response()->json([
                'ok' => true,
                'message' => $message,
                'section' => 'resep',
                'html' => $this->prescriptionListHtml($visit, $user),
                'finish_html' => view('clinical.care.partials.care-finish-rx', ['visit' => $visit])->render(),
            ]);
        }

        return redirect()
            ->route('care.show', ['visit' => $visit, 'tab' => 'resep'])
            ->with('success', $message);
    }

    public function storeFromPrescription(SavePrescriptionAsTemplateRequest $request, Visit $visit, Prescription $prescription): RedirectResponse|JsonResponse
    {
        $template = $this->templates->createFromPrescription(
            $request->user(),
            $prescription,
            $request->validated('name'),
        );

        activity_log('created', $template, ['name' => $template->name, 'prescription_id' => $prescription->id], 'Resep disimpan sebagai template '.$template->name.'.', 'prescriptions');

        return $this->respondTemplate($visit, $request->user(), 'Resep draft disimpan sebagai template.');
    }

    protected function respondTemplate(Visit $visit, ?User $user, string $message): RedirectResponse|JsonResponse
    {
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'section' => 'resep',
                'templates_html' => $this->templatesListHtml($visit, $user),
            ]);
        }

        return redirect()
            ->route('care.show', ['visit' => $visit, 'tab' => 'resep'])
            ->with('success', $message);
    }

    protected function templatesListHtml(Visit $visit, ?User $user): string
    {
        $medicines = Medicine::query()->active()->orderBy('name')->get();
        $prescriptionTemplates = PrescriptionTemplate::query()
            ->with(['author:id,name', 'items.medicine:id,name,unit', 'items.ingredients'])
            ->orderBy('name')
            ->get();

        return view('clinical.care.partials.plan-prescriptions-templates', [
            'visit' => $visit,
            'medicines' => $medicines,
            'prescriptionTemplates' => $prescriptionTemplates,
            'writable' => $this->careWritable($visit, $user),
        ])->render();
    }

    protected function prescriptionListHtml(Visit $visit, ?User $user): string
    {
        $visit->load([
            'patient',
            'prescriptions.items.medicine',
            'prescriptions.items.ingredients.medicine',
            'prescriptions.doctor',
        ]);

        return view('clinical.care.partials.plan-prescriptions-list', [
            'visit' => $visit,
            'record' => $visit->medicalRecord,
            'writable' => $this->careWritable($visit, $user),
            'medicines' => Medicine::query()->active()->orderBy('name')->get(),
        ])->render();
    }

    protected function careWritable(Visit $visit, ?User $user): bool
    {
        $writable = (bool) $user
            && $user->can('prescription.create')
            && $visit->status !== VisitStatus::Cancelled;

        $record = $visit->medicalRecord;
        if ($writable && $record && app(MedicalRecordLock::class)->isLocked($record)) {
            return false;
        }

        return $writable;
    }
}
