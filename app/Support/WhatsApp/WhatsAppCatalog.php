<?php

namespace App\Support\WhatsApp;

use App\Enums\WhatsAppCategory;
use App\Enums\WhatsAppEvent;

final class WhatsAppCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function templates(): array
    {
        return [
            self::template(
                WhatsAppEvent::AppointmentCreated,
                <<<'TXT'
Halo {{1}},

Janji kunjungan Anda di {{2}} telah berhasil dibuat.

Cabang: {{3}}
Tanggal: {{4}}
Waktu: {{5}}
Dokter: {{6}}

Kami menunggu kedatangan Anda.
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Tanggal Kunjungan', 'appointment.date'),
                    self::param(5, 'Waktu Kunjungan', 'appointment.time'),
                    self::param(6, 'Nama Dokter', 'doctor.name'),
                ],
            ),
            self::template(
                WhatsAppEvent::AppointmentConfirmation,
                <<<'TXT'
Halo {{1}},

Mohon konfirmasi kehadiran kunjungan Anda di {{2}}.

Cabang: {{3}}
Tanggal: {{4}}
Waktu: {{5}}
Dokter: {{6}}

Balas pesan ini jika jadwal perlu diubah.
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Tanggal Kunjungan', 'appointment.date'),
                    self::param(5, 'Waktu Kunjungan', 'appointment.time'),
                    self::param(6, 'Nama Dokter', 'doctor.name'),
                ],
            ),
            self::template(
                WhatsAppEvent::AppointmentReminder,
                <<<'TXT'
Halo {{1}},

Pengingat kunjungan Anda di {{2}}.

Cabang: {{3}}
Tanggal: {{4}}
Waktu: {{5}}
Dokter: {{6}}

Harap datang 10 menit lebih awal.
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Tanggal Kunjungan', 'appointment.date'),
                    self::param(5, 'Waktu Kunjungan', 'appointment.time'),
                    self::param(6, 'Nama Dokter', 'doctor.name'),
                ],
            ),
            self::template(
                WhatsAppEvent::AppointmentToday,
                <<<'TXT'
Halo {{1}},

Hari ini Anda memiliki jadwal di {{2}}.

Cabang: {{3}}
Waktu: {{4}}
Dokter: {{5}}

Sampai jumpa di klinik.
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Waktu Kunjungan', 'appointment.time'),
                    self::param(5, 'Nama Dokter', 'doctor.name'),
                ],
            ),
            self::template(
                WhatsAppEvent::AppointmentRescheduled,
                <<<'TXT'
Halo {{1}},

Jadwal kunjungan Anda di {{2}} telah diubah.

Cabang: {{3}}
Tanggal baru: {{4}}
Waktu baru: {{5}}
Dokter: {{6}}
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Tanggal Kunjungan', 'appointment.date'),
                    self::param(5, 'Waktu Kunjungan', 'appointment.time'),
                    self::param(6, 'Nama Dokter', 'doctor.name'),
                ],
            ),
            self::template(
                WhatsAppEvent::AppointmentCancelled,
                <<<'TXT'
Halo {{1}},

Janji kunjungan Anda di {{2}} telah dibatalkan.

Cabang: {{3}}
Tanggal: {{4}}
Waktu: {{5}}

Silakan hubungi klinik untuk membuat jadwal baru.
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Tanggal Kunjungan', 'appointment.date'),
                    self::param(5, 'Waktu Kunjungan', 'appointment.time'),
                ],
            ),
            self::template(
                WhatsAppEvent::AppointmentMissed,
                <<<'TXT'
Halo {{1}},

Anda tidak hadir pada jadwal kunjungan di {{2}}.

Cabang: {{3}}
Tanggal: {{4}}
Dokter: {{5}}

Hubungi kami untuk mengatur ulang kunjungan.
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Tanggal Kunjungan', 'appointment.date'),
                    self::param(5, 'Nama Dokter', 'doctor.name'),
                ],
            ),
            self::template(
                WhatsAppEvent::TreatmentCompleted,
                <<<'TXT'
Halo {{1}},

Perawatan Anda di {{2}} telah selesai.

Cabang: {{3}}
Dokter: {{4}}

Terima kasih atas kunjungan Anda.
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Nama Dokter', 'doctor.name'),
                ],
            ),
            self::template(
                WhatsAppEvent::ControlReminder,
                <<<'TXT'
Halo {{1}},

Pengingat jadwal kontrol di {{2}}.

Cabang: {{3}}
Tanggal kontrol: {{4}}
Dokter: {{5}}

Harap datang sesuai jadwal.
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Tanggal Kontrol', 'treatment.control_date'),
                    self::param(5, 'Nama Dokter', 'doctor.name'),
                ],
            ),
            self::template(
                WhatsAppEvent::FollowupReminder,
                <<<'TXT'
Halo {{1}},

Pengingat follow-up perawatan di {{2}}.

Cabang: {{3}}
Tanggal: {{4}}
Dokter: {{5}}
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Tanggal Follow-up', 'treatment.followup_date'),
                    self::param(5, 'Nama Dokter', 'doctor.name'),
                ],
            ),
            self::template(
                WhatsAppEvent::InvoiceCreated,
                <<<'TXT'
Halo {{1}},

Tagihan kunjungan Anda di {{2}} telah dibuat.

Cabang: {{3}}
Nomor: {{4}}
Total: {{5}}
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Nomor Tagihan', 'invoice.number'),
                    self::param(5, 'Total Tagihan', 'invoice.total'),
                ],
            ),
            self::template(
                WhatsAppEvent::PaymentReceived,
                <<<'TXT'
Halo {{1}},

Pembayaran Anda di {{2}} telah kami terima.

Cabang: {{3}}
Nominal: {{4}}
Sisa tagihan: {{5}}
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Nominal Pembayaran', 'payment.amount'),
                    self::param(5, 'Sisa Tagihan', 'invoice.remaining'),
                ],
            ),
            self::template(
                WhatsAppEvent::PaymentReminder,
                <<<'TXT'
Halo {{1}},

Pengingat sisa tagihan di {{2}}.

Cabang: {{3}}
Nomor: {{4}}
Sisa: {{5}}

Silakan selesaikan pembayaran di klinik.
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Nomor Tagihan', 'invoice.number'),
                    self::param(5, 'Sisa Tagihan', 'invoice.remaining'),
                ],
            ),
            self::template(
                WhatsAppEvent::PrescriptionReady,
                <<<'TXT'
Halo {{1}},

Resep Anda di {{2}} sudah siap diambil.

Cabang: {{3}}
Dokter: {{4}}
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Nama Dokter', 'doctor.name'),
                ],
            ),
            self::template(
                WhatsAppEvent::PromotionGeneral,
                <<<'TXT'
Halo {{1}},

{{2}} punya promo untuk Anda.

Cabang: {{3}}
{{4}}

{{5}}
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Judul Promo', 'promotion.title'),
                    self::param(5, 'Isi Promo', 'promotion.message'),
                ],
            ),
            self::template(
                WhatsAppEvent::BirthdayPromotion,
                <<<'TXT'
Halo {{1}},

Selamat ulang tahun dari {{2}}!

Cabang: {{3}}
{{4}}
TXT,
                [
                    self::param(1, 'Nama Pasien', 'patient.name'),
                    self::param(2, 'Nama Klinik', 'clinic.name'),
                    self::param(3, 'Nama Cabang', 'branch.name'),
                    self::param(4, 'Pesan Promo', 'promotion.message'),
                ],
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function templateByEvent(WhatsAppEvent|string $event): ?array
    {
        $key = $event instanceof WhatsAppEvent ? $event->value : $event;

        foreach (self::templates() as $template) {
            if ($template['event_key'] === $key) {
                return $template;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function languages(): array
    {
        return [
            'id' => 'Indonesia',
            'en' => 'English',
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function sources(): array
    {
        return [
            WhatsAppCategory::Appointment->value => self::appointmentSources(),
            WhatsAppCategory::Treatment->value => array_merge(self::appointmentSources(), [
                'treatment.control_date' => 'Tanggal Kontrol',
                'treatment.followup_date' => 'Tanggal Follow-up',
            ]),
            WhatsAppCategory::Billing->value => [
                'patient.name' => 'Nama Pasien',
                'patient.phone' => 'Nomor Pasien',
                'clinic.name' => 'Nama Klinik',
                'clinic.phone' => 'Telepon Klinik',
                'branch.name' => 'Nama Cabang',
                'invoice.number' => 'Nomor Tagihan',
                'invoice.total' => 'Total Tagihan',
                'invoice.paid' => 'Sudah Dibayar',
                'invoice.remaining' => 'Sisa Tagihan',
                'invoice.date' => 'Tanggal Tagihan',
                'payment.date' => 'Tanggal Pembayaran',
                'payment.amount' => 'Nominal Pembayaran',
            ],
            WhatsAppCategory::Pharmacy->value => [
                'patient.name' => 'Nama Pasien',
                'clinic.name' => 'Nama Klinik',
                'branch.name' => 'Nama Cabang',
                'doctor.name' => 'Nama Dokter',
                'prescription.date' => 'Tanggal Resep',
            ],
            WhatsAppCategory::Marketing->value => [
                'patient.name' => 'Nama Pasien',
                'clinic.name' => 'Nama Klinik',
                'clinic.phone' => 'Telepon Klinik',
                'branch.name' => 'Nama Cabang',
                'promotion.title' => 'Judul Promo',
                'promotion.message' => 'Isi Promo',
                'promotion.start_date' => 'Tanggal Mulai Promo',
                'promotion.end_date' => 'Tanggal Berakhir Promo',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourcesFor(WhatsAppCategory|WhatsAppEvent|string $target): array
    {
        if ($target instanceof WhatsAppEvent) {
            $target = $target->category();
        }

        $key = $target instanceof WhatsAppCategory ? $target->value : $target;

        return self::sources()[$key] ?? self::appointmentSources();
    }

    /**
     * @return array<string, string>
     */
    public static function appointmentSources(): array
    {
        return [
            'patient.name' => 'Nama Pasien',
            'patient.phone' => 'Nomor Pasien',
            'clinic.name' => 'Nama Klinik',
            'clinic.phone' => 'Telepon Klinik',
            'branch.name' => 'Nama Cabang',
            'branch.address' => 'Alamat Cabang',
            'appointment.date' => 'Tanggal Kunjungan',
            'appointment.time' => 'Waktu Kunjungan',
            'appointment.status' => 'Status Kunjungan',
            'doctor.name' => 'Nama Dokter',
            'doctor.specialization' => 'Spesialisasi Dokter',
            'room.name' => 'Nama Ruangan',
        ];
    }

    /**
     * @param  list<array{position: int, label: string, source: string}>  $parameters
     * @return array<string, mixed>
     */
    protected static function template(WhatsAppEvent $event, string $preview, array $parameters): array
    {
        static $order = 0;

        return [
            'event_key' => $event->value,
            'category' => $event->category()->value,
            'label' => $event->label(),
            'default_template_name' => $event->value,
            'default_language' => 'id',
            'preview_body' => trim($preview),
            'default_parameter_map' => $parameters,
            'is_recommended' => in_array($event, WhatsAppEvent::recommended(), true),
            'sort_order' => ++$order,
        ];
    }

    /**
     * @return array{position: int, label: string, source: string}
     */
    protected static function param(int $position, string $label, string $source): array
    {
        return [
            'position' => $position,
            'label' => $label,
            'source' => $source,
        ];
    }
}
