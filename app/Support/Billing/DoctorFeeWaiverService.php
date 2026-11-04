<?php

namespace App\Support\Billing;

use App\Enums\DoctorFeeWaiverStatus;
use App\Enums\InvoiceItemSource;
use App\Enums\InvoiceStatus;
use App\Enums\ProcedureAdjustmentType;
use App\Enums\ProcedureDiscountType;
use App\Models\DoctorFeeWaiverLog;
use App\Models\DoctorFeeWaiverSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\Models\Visit;
use App\Support\Clinical\TariffResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorFeeWaiverService
{
    public function __construct(
        protected TariffResolver $tariffs,
        protected ProcedureTariffCalculator $calculator,
    ) {}

    public function canWaive(User $user, Visit $visit): bool
    {
        if (! $user->can('procedure.waive_doctor_fee') || ! $visit->doctor_id) {
            return false;
        }

        if ($user->can('procedure.manage_doctor_fee_waiver_policy')) {
            return true;
        }

        $setting = $this->resolveSetting($visit);

        return $setting?->is_allowed === true;
    }

    public function canDiscount(User $user): bool
    {
        return $user->can('procedure.discount');
    }

    public function canApprove(User $user): bool
    {
        return $user->can('procedure.approve_doctor_fee_waiver');
    }

    public function canViewHistory(User $user): bool
    {
        return $user->can('procedure.manage_doctor_fee_waiver_policy')
            || $user->can('procedure.approve_doctor_fee_waiver')
            || $user->can('finance.view')
            || $user->can('audit_log.view')
            || $user->can('report.view');
    }

    public function resolveSetting(Visit $visit): ?DoctorFeeWaiverSetting
    {
        if (! $visit->doctor_id) {
            return null;
        }

        $settings = DoctorFeeWaiverSetting::query()
            ->where('tenant_id', $visit->tenant_id)
            ->where('doctor_user_id', $visit->doctor_id)
            ->get();

        return $settings->first(fn (DoctorFeeWaiverSetting $setting) => (int) $setting->branch_id === (int) $visit->branch_id)
            ?? $settings->first(fn (DoctorFeeWaiverSetting $setting) => $setting->branch_id === null);
    }

    /**
     * @param  array<string, mixed>  $adjustment
     * @return array<string, mixed>
     */
    public function quote(Visit $visit, int $procedureId, array $adjustment, User $actor): array
    {
        $resolved = $this->resolveAdjustment($visit, $actor, $adjustment);
        $quote = $this->calculator->quote(
            $this->tariffs->componentsFor($visit, $procedureId),
            $resolved['type'],
            $resolved['discount_type'],
            $resolved['discount_value'],
        );

        $quote['waiver_status'] = $resolved['status']->value;
        $quote['include_waived_in_sharing_fee'] = $resolved['include_waived_in_sharing_fee'];
        $quote['can_waive'] = $this->canWaive($actor, $visit);
        $quote['can_discount'] = $this->canDiscount($actor);
        $quote['requires_approval'] = $resolved['status'] === DoctorFeeWaiverStatus::Pending;

        if ($resolved['status'] === DoctorFeeWaiverStatus::Pending) {
            $normal = $this->calculator->quote($this->tariffs->componentsFor($visit, $procedureId));
            $quote['charged_unit_price'] = $normal['charged_unit_price'];
            $quote['doctor_fee_charged'] = $normal['doctor_fee_charged'];
            $quote['doctor_fee_waived'] = '0.00';
        }

        return $quote;
    }

    /**
     * @param  array<string, mixed>  $adjustment
     * @return array<string, mixed>
     */
    public function attributesForCreate(Visit $visit, int $procedureId, array $adjustment, User $actor): array
    {
        $resolved = $this->resolveAdjustment($visit, $actor, $adjustment, persist: true);
        $components = $this->tariffs->componentsFor($visit, $procedureId);
        $applied = $resolved['status'] === DoctorFeeWaiverStatus::Pending
            ? ProcedureAdjustmentType::Normal
            : $resolved['type'];
        $quote = $this->calculator->quote(
            $components,
            $applied,
            $applied === ProcedureAdjustmentType::ProcedureDiscount ? $resolved['discount_type'] : null,
            $applied === ProcedureAdjustmentType::ProcedureDiscount ? $resolved['discount_value'] : '0',
        );

        return [
            'price_at_time' => $quote['charged_unit_price'],
            'tariff_price_at_time' => $quote['tariff_price'],
            'doctor_fee_at_time' => $quote['doctor_fee'],
            'bhp_amount_at_time' => $quote['bhp_amount'],
            'clinic_fee_at_time' => $quote['clinic_fee'],
            'adjustment_type' => $resolved['type']->value,
            'discount_type' => $resolved['type'] === ProcedureAdjustmentType::ProcedureDiscount
                ? $resolved['discount_type']?->value
                : null,
            'discount_value' => $resolved['type'] === ProcedureAdjustmentType::ProcedureDiscount
                ? $resolved['discount_value']
                : '0.00',
            'discount_amount' => $quote['discount_amount'],
            'waiver_reason' => $resolved['reason'],
            'waived_by' => $resolved['type'] === ProcedureAdjustmentType::Normal ? null : $actor->id,
            'waived_at' => $resolved['type'] === ProcedureAdjustmentType::Normal ? null : now(),
            'waiver_status' => $resolved['status']->value,
            'approved_by' => $resolved['status'] === DoctorFeeWaiverStatus::Applied ? $actor->id : null,
            'approved_at' => $resolved['status'] === DoctorFeeWaiverStatus::Applied ? now() : null,
            'include_waived_in_sharing_fee' => $resolved['include_waived_in_sharing_fee'],
            '_resolved' => $resolved,
            '_quote' => $quote,
        ];
    }

    public function recordLog(Visit $visit, ProcedureRecord $record, array $resolved, array $quote, User $actor): DoctorFeeWaiverLog
    {
        if ($resolved['type'] === ProcedureAdjustmentType::Normal) {
            return new DoctorFeeWaiverLog;
        }

        $log = DoctorFeeWaiverLog::query()->create([
            'tenant_id' => $visit->tenant_id,
            'branch_id' => $visit->branch_id,
            'visit_id' => $visit->id,
            'procedure_record_id' => $record->id,
            'doctor_user_id' => $visit->doctor_id,
            'waived_by' => $actor->id,
            'approved_by' => $resolved['status'] === DoctorFeeWaiverStatus::Applied ? $actor->id : null,
            'adjustment_type' => $resolved['type']->value,
            'status' => $resolved['status']->value,
            'reason' => $resolved['reason'],
            'doctor_fee_before' => bcmul($quote['doctor_fee'], (string) $record->quantity, 2),
            'doctor_fee_after' => bcmul(
                $resolved['status'] === DoctorFeeWaiverStatus::Pending
                    ? $quote['doctor_fee']
                    : $quote['doctor_fee_charged'],
                (string) $record->quantity,
                2,
            ),
            'tariff_price' => bcmul($quote['tariff_price'], (string) $record->quantity, 2),
            'charged_unit_price' => $record->price_at_time,
            'discount_type' => $record->discount_type?->value,
            'discount_value' => $record->discount_value,
            'discount_amount' => bcmul((string) $record->discount_amount, (string) $record->quantity, 2),
            'include_waived_in_sharing_fee' => $record->include_waived_in_sharing_fee,
            'waived_at' => $record->waived_at,
            'approved_at' => $record->approved_at,
            'snapshot' => $quote,
        ]);

        activity_log('procedure_adjustment', $record, [
            'adjustment_type' => $resolved['type']->value,
            'status' => $resolved['status']->value,
            'doctor_fee_before' => $quote['doctor_fee'],
            'doctor_fee_after' => $log->doctor_fee_after,
            'reason' => $resolved['reason'],
        ], 'Penyesuaian tarif tindakan', 'procedure_records', $actor);

        return $log;
    }

    /**
     * @param  array<string, mixed>  $adjustment
     */
    public function updateUnbilled(Visit $visit, ProcedureRecord $record, array $adjustment, User $actor): ProcedureRecord
    {
        if (! $record->isUnbilled()) {
            throw ValidationException::withMessages([
                'adjustment_type' => 'Tagihan tindakan yang sudah masuk invoice tidak dapat diubah langsung. Gunakan pembatalan tagihan atau mekanisme koreksi.',
            ]);
        }

        $attrs = $this->attributesForCreate($visit, (int) $record->procedure_id, $adjustment, $actor);
        $resolved = $attrs['_resolved'];
        $quote = $attrs['_quote'];
        unset($attrs['_resolved'], $attrs['_quote']);

        $record->update($attrs);

        if ($resolved['type'] !== ProcedureAdjustmentType::Normal) {
            $this->recordLog($visit, $record->fresh() ?? $record, $resolved, $quote, $actor);
        }

        return $record->fresh() ?? $record;
    }

    public function approve(DoctorFeeWaiverLog $log, User $actor): ProcedureRecord
    {
        if (! $this->canApprove($actor)) {
            abort(403, 'Anda tidak berhak menyetujui pembebasan jasa dokter.');
        }

        if (! $log->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Pembebasan ini sudah diproses.',
            ]);
        }

        return DB::transaction(function () use ($log, $actor) {
            /** @var ProcedureRecord $record */
            $record = ProcedureRecord::query()->whereKey($log->procedure_record_id)->lockForUpdate()->firstOrFail();
            $visit = $record->visit ?? Visit::query()->findOrFail($record->visit_id);

            $this->assertInvoiceMutable($record);

            $quote = $this->calculator->quote(
                [
                    'doctor_fee' => $record->doctor_fee_at_time,
                    'bhp_amount' => $record->bhp_amount_at_time,
                    'clinic_fee' => $record->clinic_fee_at_time,
                    'price' => $record->tariff_price_at_time,
                ],
                ProcedureAdjustmentType::DoctorFeeWaiver,
            );

            $record->update([
                'price_at_time' => $quote['charged_unit_price'],
                'waiver_status' => DoctorFeeWaiverStatus::Applied->value,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $log->update([
                'status' => DoctorFeeWaiverStatus::Applied->value,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'doctor_fee_after' => bcmul($quote['doctor_fee_charged'], (string) $record->quantity, 2),
                'charged_unit_price' => $quote['charged_unit_price'],
                'snapshot' => $quote,
            ]);

            $this->syncOpenInvoiceItem($record->fresh() ?? $record);

            activity_audit('procedure_waiver_approved', $record, [], 'Pembebasan jasa dokter disetujui', 'procedure_records');

            return $record->fresh() ?? $record;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceSnapshot(ProcedureRecord $record): array
    {
        $type = $record->adjustment_type instanceof ProcedureAdjustmentType
            ? $record->adjustment_type
            : ProcedureAdjustmentType::from((string) ($record->adjustment_type ?? 'normal'));
        $applied = $record->waiver_status === DoctorFeeWaiverStatus::Pending
            ? ProcedureAdjustmentType::Normal
            : $type;

        $quote = $this->calculator->quote(
            [
                'doctor_fee' => $record->doctor_fee_at_time,
                'bhp_amount' => $record->bhp_amount_at_time,
                'clinic_fee' => $record->clinic_fee_at_time,
                'price' => $record->tariff_price_at_time ?: $record->price_at_time,
            ],
            $applied,
            $record->discount_type instanceof ProcedureDiscountType ? $record->discount_type : null,
            (string) ($record->discount_value ?? '0'),
        );

        return [
            'adjustment_type' => $type->value,
            'original_unit_price' => $quote['tariff_price'],
            'doctor_fee_original' => $quote['doctor_fee'],
            'doctor_fee_charged' => $quote['doctor_fee_charged'],
            'doctor_fee_waived' => $quote['doctor_fee_waived'],
            'bhp_amount' => $quote['bhp_amount'],
            'clinic_fee' => $quote['clinic_fee'],
            'discount_amount' => $quote['discount_amount'],
            'include_waived_in_sharing_fee' => (bool) $record->include_waived_in_sharing_fee,
            'component_snapshot' => array_merge($quote, [
                'waiver_status' => $record->waiver_status instanceof DoctorFeeWaiverStatus
                    ? $record->waiver_status->value
                    : (string) $record->waiver_status,
                'reason' => $record->waiver_reason,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $adjustment
     * @return array{
     *     type: ProcedureAdjustmentType,
     *     discount_type: ProcedureDiscountType|null,
     *     discount_value: string,
     *     reason: string|null,
     *     status: DoctorFeeWaiverStatus,
     *     include_waived_in_sharing_fee: bool
     * }
     */
    protected function resolveAdjustment(Visit $visit, User $actor, array $adjustment, bool $persist = false): array
    {
        $waive = $this->truthy($adjustment['waive_doctor_fee'] ?? false);
        $discountType = filled($adjustment['discount_type'] ?? null)
            ? ProcedureDiscountType::tryFrom((string) $adjustment['discount_type'])
            : null;
        $discountValue = $this->calculator->normalize((string) ($adjustment['discount_value'] ?? '0'));
        $hasDiscount = $discountType !== null && bccomp($discountValue, '0', 2) > 0;
        $reason = filled($adjustment['waiver_reason'] ?? null) ? trim((string) $adjustment['waiver_reason']) : null;

        if ($waive && $hasDiscount) {
            throw ValidationException::withMessages([
                'waive_doctor_fee' => 'Pembebasan jasa dokter dan diskon tindakan tidak dapat dipakai bersamaan.',
            ]);
        }

        if ($waive) {
            if ($persist && ! $this->canWaive($actor, $visit)) {
                throw ValidationException::withMessages([
                    'waive_doctor_fee' => 'Dokter ini tidak diizinkan membebaskan jasa dokter.',
                ]);
            }

            if ($persist && ! $visit->doctor_id) {
                throw ValidationException::withMessages([
                    'waive_doctor_fee' => 'Kunjungan belum memiliki dokter pelaksana.',
                ]);
            }

            if ($persist && blank($reason)) {
                throw ValidationException::withMessages([
                    'waiver_reason' => 'Alasan pembebasan jasa dokter wajib diisi.',
                ]);
            }

            $setting = $this->resolveSetting($visit);
            $needsApproval = $setting?->require_approval === true && ! $this->canApprove($actor);

            return [
                'type' => ProcedureAdjustmentType::DoctorFeeWaiver,
                'discount_type' => null,
                'discount_value' => '0.00',
                'reason' => $reason,
                'status' => $needsApproval ? DoctorFeeWaiverStatus::Pending : DoctorFeeWaiverStatus::Applied,
                'include_waived_in_sharing_fee' => (bool) ($setting?->include_waived_in_sharing_fee),
            ];
        }

        if ($hasDiscount) {
            if ($persist && ! $this->canDiscount($actor)) {
                throw ValidationException::withMessages([
                    'discount_value' => 'Anda tidak berhak memberikan diskon tindakan.',
                ]);
            }

            if ($persist && blank($reason)) {
                throw ValidationException::withMessages([
                    'waiver_reason' => 'Alasan diskon tindakan wajib diisi.',
                ]);
            }

            return [
                'type' => ProcedureAdjustmentType::ProcedureDiscount,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'reason' => $reason,
                'status' => DoctorFeeWaiverStatus::Applied,
                'include_waived_in_sharing_fee' => false,
            ];
        }

        return [
            'type' => ProcedureAdjustmentType::Normal,
            'discount_type' => null,
            'discount_value' => '0.00',
            'reason' => null,
            'status' => DoctorFeeWaiverStatus::None,
            'include_waived_in_sharing_fee' => false,
        ];
    }

    protected function assertInvoiceMutable(ProcedureRecord $record): void
    {
        $item = InvoiceItem::query()
            ->where('source_type', InvoiceItemSource::Procedure)
            ->where('source_id', $record->id)
            ->latest('id')
            ->first();

        if (! $item) {
            return;
        }

        $invoice = $item->invoice;

        if ($invoice && ($invoice->isPaid() || $invoice->isVoid() || $invoice->status === InvoiceStatus::Partial)) {
            throw ValidationException::withMessages([
                'status' => 'Tagihan sudah memiliki pembayaran. Pembebasan tidak dapat diubah langsung. Gunakan mekanisme koreksi atau pengembalian dana.',
            ]);
        }
    }

    protected function syncOpenInvoiceItem(ProcedureRecord $record): void
    {
        $item = InvoiceItem::query()
            ->where('source_type', InvoiceItemSource::Procedure)
            ->where('source_id', $record->id)
            ->latest('id')
            ->first();

        if (! $item) {
            return;
        }

        $invoice = $item->invoice;

        if (! $invoice || ! $invoice->isOpen() || $invoice->isPaid() || bccomp((string) $invoice->paid_amount, '0', 2) > 0) {
            return;
        }

        $snapshot = $this->invoiceSnapshot($record);
        $item->update(array_merge($snapshot, [
            'unit_price' => (string) $record->price_at_time,
            'subtotal' => bcmul((string) $record->price_at_time, (string) $record->quantity, 2),
        ]));

        app(BillingService::class)->recalculate($invoice);

        DoctorFeeWaiverLog::query()
            ->where('procedure_record_id', $record->id)
            ->latest('id')
            ->first()
            ?->update(['invoice_id' => $invoice->id]);
    }

    protected function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'on' || $value === 'true';
    }
}
