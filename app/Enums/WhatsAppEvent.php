<?php

namespace App\Enums;

enum WhatsAppEvent: string
{
    case AppointmentCreated = 'appointment_created';
    case AppointmentConfirmation = 'appointment_confirmation';
    case AppointmentReminder = 'appointment_reminder';
    case AppointmentToday = 'appointment_today';
    case AppointmentRescheduled = 'appointment_rescheduled';
    case AppointmentCancelled = 'appointment_cancelled';
    case AppointmentMissed = 'appointment_missed';
    case TreatmentCompleted = 'treatment_completed';
    case ControlReminder = 'control_reminder';
    case FollowupReminder = 'followup_reminder';
    case InvoiceCreated = 'invoice_created';
    case PaymentReceived = 'payment_received';
    case PaymentReminder = 'payment_reminder';
    case PrescriptionReady = 'prescription_ready';
    case PromotionGeneral = 'promotion_general';
    case BirthdayPromotion = 'birthday_promotion';

    public function label(): string
    {
        return match ($this) {
            self::AppointmentCreated => 'Appointment Dibuat',
            self::AppointmentConfirmation => 'Konfirmasi Appointment',
            self::AppointmentReminder => 'Pengingat Jadwal',
            self::AppointmentToday => 'Pengingat Hari-H',
            self::AppointmentRescheduled => 'Perubahan Jadwal',
            self::AppointmentCancelled => 'Pembatalan Jadwal',
            self::AppointmentMissed => 'Pasien Tidak Hadir',
            self::TreatmentCompleted => 'Tindakan Selesai',
            self::ControlReminder => 'Pengingat Kontrol',
            self::FollowupReminder => 'Pengingat Follow-up',
            self::InvoiceCreated => 'Tagihan Dibuat',
            self::PaymentReceived => 'Pembayaran Diterima',
            self::PaymentReminder => 'Pengingat Pembayaran',
            self::PrescriptionReady => 'Resep Siap Diambil',
            self::PromotionGeneral => 'Promosi Klinik',
            self::BirthdayPromotion => 'Promo Ulang Tahun',
        };
    }

    public function category(): WhatsAppCategory
    {
        return match ($this) {
            self::AppointmentCreated,
            self::AppointmentConfirmation,
            self::AppointmentReminder,
            self::AppointmentToday,
            self::AppointmentRescheduled,
            self::AppointmentCancelled,
            self::AppointmentMissed => WhatsAppCategory::Appointment,
            self::TreatmentCompleted,
            self::ControlReminder,
            self::FollowupReminder => WhatsAppCategory::Treatment,
            self::InvoiceCreated,
            self::PaymentReceived,
            self::PaymentReminder => WhatsAppCategory::Billing,
            self::PrescriptionReady => WhatsAppCategory::Pharmacy,
            self::PromotionGeneral,
            self::BirthdayPromotion => WhatsAppCategory::Marketing,
        };
    }

    public function requiresMarketingConsent(): bool
    {
        return $this->category() === WhatsAppCategory::Marketing;
    }

    /**
     * @return list<self>
     */
    public static function recommended(): array
    {
        return [
            self::AppointmentConfirmation,
            self::AppointmentReminder,
            self::AppointmentToday,
            self::AppointmentRescheduled,
            self::AppointmentCancelled,
            self::ControlReminder,
        ];
    }
}
