<?php

namespace App\Observers;

use App\Enums\VisitStatus;
use App\Enums\WhatsAppEvent;
use App\Models\Visit;
use App\Support\WhatsApp\WhatsAppAutomationService;

class VisitObserver
{
    public function __construct(protected WhatsAppAutomationService $whatsapp) {}

    public function created(Visit $visit): void
    {
        $this->whatsapp->queueVisit(WhatsAppEvent::AppointmentCreated, $visit);
        $this->whatsapp->queueVisit(WhatsAppEvent::AppointmentConfirmation, $visit);
    }

    public function updated(Visit $visit): void
    {
        if ($visit->wasChanged('visit_date') && $visit->status?->isOpen()) {
            $this->whatsapp->queueVisit(WhatsAppEvent::AppointmentRescheduled, $visit, 'rescheduled');
        }

        if ($visit->wasChanged('status') && $visit->status === VisitStatus::Cancelled) {
            $this->whatsapp->queueVisit(WhatsAppEvent::AppointmentCancelled, $visit);
        }

        if ($visit->wasChanged('status') && $visit->status === VisitStatus::Done) {
            $this->whatsapp->syncControlDates($visit->loadMissing('medicalRecord'));
            $this->whatsapp->queueVisit(WhatsAppEvent::TreatmentCompleted, $visit);
        }
    }
}
