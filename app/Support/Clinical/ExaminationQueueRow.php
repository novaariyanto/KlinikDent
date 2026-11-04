<?php

namespace App\Support\Clinical;

use App\Enums\PayerType;
use App\Enums\RoomType;
use App\Enums\SatuSehatStatus;
use App\Enums\VisitStatus;
use App\Models\Visit;

final class ExaminationQueueRow
{
    public function __construct(public Visit $visit) {}

    public function queueNumber(): string
    {
        return $this->visit->queue?->displayNumber() ?: '-';
    }

    public function registrationNumber(): string
    {
        $date = $this->visit->visit_date?->format('Ymd') ?? '00000000';

        return $date.str_pad((string) $this->visit->id, 4, '0', STR_PAD_LEFT);
    }

    public function registrationDate(): string
    {
        return $this->visit->visit_date?->format('d-m-Y') ?: '-';
    }

    public function screeningDone(): bool
    {
        $record = $this->visit->medicalRecord;

        return CareProgress::screeningComplete(
            $record?->vital_signs ?? [],
            $record?->systemic_history ?? [],
        );
    }

    /**
     * @return array{label: string, class: string}
     */
    public function nurseStatus(): array
    {
        return $this->roleStatus($this->screeningDone());
    }

    /**
     * @return array{label: string, class: string}
     */
    public function doctorStatus(): array
    {
        $record = $this->visit->medicalRecord;
        $done = filled($record?->finalized_at)
            || CareProgress::examComplete($record?->dental_exam ?? [], $record?->initial_examination);

        return $this->roleStatus($done);
    }

    public function visitKind(): string
    {
        return 'Kunjungan Sakit';
    }

    public function poliName(): string
    {
        $room = $this->visit->room;

        if ($room === null) {
            return '-';
        }

        return $room->type === RoomType::Poli ? $room->name : '-';
    }

    public function roomName(): string
    {
        $room = $this->visit->room;

        if ($room === null) {
            return '-';
        }

        $type = $room->type instanceof RoomType ? $room->type->label() : null;

        return $type ? $room->name.' · '.$type : $room->name;
    }

    public function doctorName(): string
    {
        return $this->visit->doctor?->name ?: '-';
    }

    public function payerName(): string
    {
        return $this->visit->payer?->name ?: '-';
    }

    public function payerCard(): ?string
    {
        $payer = $this->visit->payer;

        if ($payer?->type === PayerType::Bpjs) {
            $number = $this->visit->patient?->bpjs_number;

            return filled($number) ? (string) $number : null;
        }

        return filled($payer?->contract_number) ? (string) $payer->contract_number : null;
    }

    /**
     * @return array{label: string, class: string}
     */
    public function dischargeStatus(): array
    {
        if ($this->visit->status === VisitStatus::Cancelled) {
            return ['label' => 'Batal', 'class' => 'badge bg-danger'];
        }

        if ($this->visit->status === VisitStatus::Done) {
            return ['label' => 'Pulang', 'class' => 'badge bg-success'];
        }

        $record = $this->visit->medicalRecord;

        if (
            CareProgress::instructionComplete($record)
            || filled($record?->control_date)
            || filled($record?->followup_date)
        ) {
            return ['label' => 'Direncanakan', 'class' => 'badge bg-info'];
        }

        return ['label' => 'Belum', 'class' => 'badge bg-secondary'];
    }

    /**
     * @return array{label: string, class: string}
     */
    public function pcareStatus(): array
    {
        return ['label' => 'Belum', 'class' => 'badge bg-secondary'];
    }

    /**
     * @return array{label: string, class: string}
     */
    public function satuSehatStatus(): array
    {
        $status = $this->visit->satusehat_status ?? SatuSehatStatus::Pending;

        return ['label' => $status->label(), 'class' => $status->badgeClass()];
    }

    /**
     * @return array{label: string, class: string}
     */
    protected function roleStatus(bool $workDone): array
    {
        if ($this->visit->status === VisitStatus::Cancelled) {
            return ['label' => 'Batal', 'class' => 'badge bg-danger'];
        }

        if ($this->visit->status === VisitStatus::Done || $workDone) {
            return ['label' => 'Selesai', 'class' => 'badge bg-success'];
        }

        if ($this->visit->status === VisitStatus::InService) {
            return ['label' => 'Proses', 'class' => 'badge bg-info'];
        }

        return ['label' => 'Menunggu', 'class' => 'badge bg-warning'];
    }
}
