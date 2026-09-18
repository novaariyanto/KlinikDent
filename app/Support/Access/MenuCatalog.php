<?php

namespace App\Support\Access;

use App\Enums\MenuScope;
use App\Enums\MenuType;
use App\Enums\RoleName;

final class MenuCatalog
{
    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function trees(): array
    {
        return [
            RoleName::SuperAdminSaas->value => self::superAdminSaas(),
            RoleName::Owner->value => self::owner(),
            RoleName::Manager->value => self::manager(),
            RoleName::Registration->value => self::registration(),
            RoleName::Dentist->value => self::dentist(),
            RoleName::DentalAssistant->value => self::dentalAssistant(),
            RoleName::Nurse->value => self::nurse(),
            RoleName::Pharmacy->value => self::pharmacy(),
            RoleName::Cashier->value => self::cashier(),
            RoleName::Finance->value => self::finance(),
            RoleName::Auditor->value => self::auditor(),
        ];
    }

    public static function scopeFor(string $role): MenuScope
    {
        return $role === RoleName::SuperAdminSaas->value
            ? MenuScope::Saas
            : MenuScope::Tenant;
    }

    /**
     * Unique placeholder routes derived from the menu trees.
     *
     * @return list<array{name: string, uri: string, permission: string, scope: string}>
     */
    public static function placeholderRoutes(): array
    {
        $skip = [
            'dashboard',
            'users.index',
            'roles.index',
            'roles.show',
            'menus.index',
            'settings.index',
            'settings.clinic',
            'logs.index',
            'saas.tenants.index',
            'saas.tenants.create',
            'saas.tenants.show',
            'saas.tenants.status',
            'branches.index',
            'branches.create',
            'management.clinic',
            'management.services.index',
            'management.procedures.index',
            'management.tariffs.index',
            'management.medicines.index',
            'management.payers.index',
            'management.rooms.index',
            'pharmacy.medicines.index',
            'pharmacy.index',
            'pharmacy.prescriptions.processing',
            'pharmacy.prescriptions.completed',
            'pharmacy.prescriptions.history',
            'pharmacy.stock.index',
            'pharmacy.stock.batches',
            'pharmacy.stock.expired',
            'pharmacy.stock.adjustments',
            'pharmacy.purchases.suppliers',
            'pharmacy.purchases.orders',
            'pharmacy.purchases.receipts',
            'pharmacy.reports.stock',
            'pharmacy.reports.outgoing',
            'pharmacy.reports.incoming',
            'pharmacy.reports.expired',
            'pharmacy.transactions',
            'reports.pharmacy',
            'billing.index',
            'billing.invoices',
            'billing.invoices.today',
            'billing.payments',
            'billing.receivables',
            'billing.history',
            'cashier.shifts.open',
            'cashier.shifts.transactions',
            'cashier.shifts.close',
            'cashier.reports',
            'registration.payers.general',
            'registration.payers.bpjs',
            'registration.payers.insurance',
            'registration.payers.corporate',
            'registration.payers.membership',
            'registration.index',
            'registration.new',
            'registration.patients',
            'registration.visits',
            'registration.visits.today',
            'registration.history',
            'patients.history',
            'queue.index',
            'queue.today',
            'queue.mine',
            'queue.waiting',
            'queue.called',
            'queue.monitor',
            'medical-record.index',
            'odontogram.index',
            'diagnosis.index',
            'procedures.index',
            'prescriptions.index',
            'referrals.index',
            'examinations.index',
            'examinations.initial',
            'examinations.anamnesis',
            'examinations.vitals',
            'examinations.notes',
            'pharmacy.prescriptions.incoming',
        ];

        $routes = [];

        foreach (self::trees() as $role => $items) {
            self::collectRoutes($items, $routes, $skip, self::scopeFor($role)->value);
        }

        return array_values($routes);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    public static function titles(array $items): array
    {
        $titles = [];

        foreach ($items as $item) {
            $titles[] = $item['title'];

            foreach (self::titles($item['children'] ?? []) as $childTitle) {
                $titles[] = $childTitle;
            }
        }

        return $titles;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function superAdminSaas(): array
    {
        return [
            self::item('saas.dashboard', 'Dashboard SaaS', 'bx bx-home-circle', 'dashboard', 'dashboard.view', 'Ringkasan platform SaaS klinik gigi.'),
            self::item('saas.tenants', 'Tenant / Klinik', 'bx bx-buildings', null, 'tenant.view', 'Manajemen tenant klinik.', [
                self::item('saas.tenants.index', 'Semua Klinik', 'bx bx-list-ul', 'saas.tenants.index', 'tenant.view', 'Daftar seluruh klinik pada platform.'),
                self::item('saas.tenants.create', 'Klinik Baru', 'bx bx-plus', 'saas.tenants.create', 'tenant.create', 'Pendaftaran tenant klinik baru.'),
                self::item('saas.tenants.show', 'Detail Klinik', 'bx bx-info-circle', 'saas.tenants.index', 'tenant.view', 'Informasi detail tenant klinik.'),
                self::item('saas.tenants.status', 'Status Klinik', 'bx bx-toggle-left', 'saas.tenants.index', 'tenant.manage', 'Status aktif dan langganan klinik.'),
            ]),
            self::item('saas.users', 'Users', 'bx bx-user', null, 'users.view', 'Manajemen pengguna platform.', [
                self::item('saas.users.index', 'Semua User', 'bx bx-group', 'users.index', 'users.view', 'Daftar pengguna platform.'),
                self::item('saas.users.activity', 'Aktivitas User', 'bx bx-history', 'saas.users.activity', 'saas.activity.view', 'Aktivitas pengguna platform.'),
            ]),
            self::item('saas.subscription', 'Subscription / Paket', 'bx bx-package', null, 'subscription.view', 'Paket dan langganan SaaS.', [
                self::item('saas.packages', 'Paket', 'bx bx-box', 'saas.packages.index', 'package.view', 'Master paket langganan.'),
                self::item('saas.subscriptions', 'Langganan', 'bx bx-calendar', 'saas.subscriptions.index', 'subscription.view', 'Langganan tenant.'),
                self::item('saas.invoices', 'Invoice', 'bx bx-receipt', 'saas.invoices.index', 'invoice.view', 'Invoice langganan platform.'),
            ]),
            self::item('saas.system', 'System', 'bx bx-server', null, 'system.view', 'Konfigurasi sistem platform.', [
                self::item('saas.system.master', 'Master Global', 'bx bx-data', 'menus.index', 'menus.view', 'Master data dan navigasi global.'),
                self::item('saas.system.integrations', 'Integrasi', 'bx bx-plug', 'saas.system.integrations', 'saas.integration.view', 'Integrasi tingkat platform.'),
                self::item('saas.system.logs', 'System Logs', 'bx bx-file', 'logs.index', 'logs.view', 'Log sistem aplikasi.'),
                self::item('saas.system.audit', 'Audit Logs', 'bx bx-shield-quarter', 'saas.system.audit', 'saas.audit.view', 'Audit log platform.'),
            ]),
            self::item('saas.support', 'Support', 'bx bx-headphone', null, 'support.view', 'Dukungan platform.', [
                self::item('saas.support.tickets', 'Ticket', 'bx bx-comment-detail', 'saas.support.tickets', 'ticket.view', 'Tiket bantuan tenant.'),
                self::item('saas.support.activity', 'Activity', 'bx bx-pulse', 'saas.support.activity', 'support.view', 'Aktivitas dukungan.'),
            ]),
            self::item('saas.settings', 'Settings', 'bx bx-cog', null, 'platform.setting.view', 'Pengaturan platform.', [
                self::item('saas.settings.platform', 'Platform Settings', 'bx bx-slider-alt', 'settings.index', 'settings.update', 'Pengaturan global platform SaaS.'),
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function owner(): array
    {
        return [
            self::item('owner.dashboard', 'Dashboard', 'bx bx-home-circle', 'dashboard', 'dashboard.view', 'Ringkasan klinik.'),
            self::item('owner.registration', 'Pendaftaran', 'bx bx-user-plus', 'registration.index', 'registration.view', 'Manajemen pendaftaran pasien.'),
            self::item('owner.medical-record', 'Rekam Medis', 'bx bx-folder-open', 'medical-record.index', 'medical_record.view', 'Rekam medis pasien.'),
            self::item('owner.odontogram', 'Odontogram', 'bx bx-grid-alt', 'odontogram.index', 'odontogram.view', 'Odontogram pasien.'),
            self::item('owner.doctors', 'Dokter & Tenaga Medis', 'bx bx-user', 'doctors.index', 'doctor.view', 'Dokter dan tenaga medis klinik.'),
            self::item('owner.queue', 'Antrean', 'bx bx-list-ol', 'queue.index', 'queue.view', 'Antrean pelayanan klinik.'),
            self::item('owner.pharmacy', 'Farmasi', 'bx bx-capsule', 'pharmacy.index', 'pharmacy.view', 'Manajemen farmasi klinik.'),
            self::item('owner.billing', 'Billing & Kasir', 'bx bx-credit-card', 'billing.index', 'billing.view', 'Tagihan dan kasir klinik.'),
            self::item('owner.finance', 'Keuangan', 'bx bx-wallet', 'finance.index', 'finance.view', 'Keuangan klinik.'),
            self::item('owner.reports', 'Laporan', 'bx bx-bar-chart-alt-2', 'reports.index', 'report.view', 'Laporan klinik.'),
            self::item('owner.branches', 'Cabang', 'bx bx-git-branch', 'branches.index', 'branch.view', 'Cabang klinik.'),
            self::item('owner.master', 'Master Data', 'bx bx-data', null, 'clinic.view', 'Master data klinik.', [
                self::item('owner.master.clinic', 'Klinik', 'bx bx-building-house', 'management.clinic', 'clinic.view', 'Ringkasan master data klinik.'),
                self::item('owner.master.services', 'Layanan', 'bx bx-list-check', 'management.services.index', 'service.view', 'Master layanan klinik.'),
                self::item('owner.master.procedures', 'Tindakan', 'bx bx-first-aid', 'management.procedures.index', 'procedure.view', 'Master tindakan.'),
                self::item('owner.master.tariffs', 'Tarif', 'bx bx-purchase-tag', 'management.tariffs.index', 'tariff.view', 'Master tarif layanan.'),
                self::item('owner.master.medicines', 'Obat', 'bx bx-plus-medical', 'management.medicines.index', 'medicine.view', 'Master obat.'),
                self::item('owner.master.payers', 'Penjamin', 'bx bx-id-card', 'management.payers.index', 'payer.view', 'Master penjamin.'),
                self::item('owner.master.rooms', 'Ruangan', 'bx bx-door-open', 'management.rooms.index', 'room.view', 'Ruangan pelayanan per cabang.'),
            ]),
            self::item('owner.users', 'Users & Roles', 'bx bx-group', null, 'users.view', 'Pengguna dan peran klinik.', [
                self::item('owner.users.index', 'Users', 'bx bx-user', 'users.index', 'users.view', 'Pengguna tenant klinik.'),
                self::item('owner.roles.index', 'Roles', 'bx bx-shield-quarter', 'roles.index', 'roles.view', 'Peran dan permission klinik.'),
            ]),
            self::item('owner.integrations', 'Integrasi', 'bx bx-plug', 'integrations.index', 'integration.view', 'Integrasi eksternal klinik.'),
            self::item('owner.audit', 'Audit Log', 'bx bx-shield', 'logs.index', 'logs.view', 'Audit log klinik.'),
            self::item('owner.settings', 'Pengaturan Klinik', 'bx bx-cog', 'settings.clinic', 'setting.view', 'Pengaturan operasional klinik.'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function manager(): array
    {
        return [
            self::item('manager.dashboard', 'Dashboard', 'bx bx-home-circle', 'dashboard', 'dashboard.view', 'Ringkasan operasional klinik.'),
            self::item('manager.registration', 'Pendaftaran', 'bx bx-user-plus', null, 'registration.view', 'Operasional pendaftaran.', [
                self::item('manager.registration.patients', 'Pasien', 'bx bx-user', 'registration.patients', 'patient.view', 'Manajemen data pasien klinik.'),
                self::item('manager.registration.visits', 'Kunjungan', 'bx bx-calendar-check', 'registration.visits', 'visit.view', 'Kunjungan pasien.'),
                self::item('manager.registration.queue', 'Antrean', 'bx bx-list-ol', 'queue.today', 'queue.view', 'Antrean pendaftaran.'),
                self::item('manager.registration.history', 'Riwayat Pendaftaran', 'bx bx-history', 'registration.history', 'registration.view', 'Riwayat pendaftaran pasien.'),
            ]),
            self::item('manager.doctors', 'Dokter & Jadwal', 'bx bx-user', null, 'doctor.view', 'Dokter dan jadwal praktik.', [
                self::item('manager.doctors.index', 'Dokter', 'bx bx-user-circle', 'doctors.index', 'doctor.view', 'Daftar dokter klinik.'),
                self::item('manager.doctors.schedule', 'Jadwal Dokter', 'bx bx-calendar', 'doctors.schedule', 'schedule.view', 'Jadwal praktik dokter.'),
                self::item('manager.doctors.rooms', 'Ruangan', 'bx bx-door-open', 'management.rooms.index', 'room.view', 'Ruangan pelayanan.'),
            ]),
            self::item('manager.pharmacy', 'Farmasi', 'bx bx-capsule', null, 'pharmacy.view', 'Operasional farmasi.', [
                self::item('manager.pharmacy.stock', 'Stok', 'bx bx-package', 'pharmacy.stock.index', 'stock.view', 'Stok obat klinik.'),
                self::item('manager.pharmacy.medicines', 'Obat', 'bx bx-plus-medical', 'pharmacy.medicines.index', 'medicine.view', 'Master obat klinik.'),
                self::item('manager.pharmacy.transactions', 'Transaksi', 'bx bx-transfer', 'pharmacy.transactions', 'pharmacy.view', 'Transaksi farmasi.'),
            ]),
            self::item('manager.billing', 'Billing', 'bx bx-credit-card', null, 'billing.view', 'Tagihan dan pembayaran.', [
                self::item('manager.billing.invoices', 'Tagihan', 'bx bx-receipt', 'billing.invoices', 'billing.view', 'Tagihan pasien.'),
                self::item('manager.billing.payments', 'Pembayaran', 'bx bx-money', 'billing.payments', 'payment.view', 'Pembayaran pasien.'),
                self::item('manager.billing.receivables', 'Piutang', 'bx bx-wallet', 'billing.receivables', 'receivable.view', 'Piutang pasien.'),
            ]),
            self::item('manager.reports', 'Laporan', 'bx bx-bar-chart-alt-2', null, 'report.view', 'Laporan operasional klinik.', [
                self::item('manager.reports.visits', 'Kunjungan', 'bx bx-calendar-check', 'reports.visits', 'report.view', 'Laporan kunjungan.'),
                self::item('manager.reports.revenue', 'Pendapatan', 'bx bx-dollar', 'reports.revenue', 'report.view', 'Laporan pendapatan.'),
                self::item('manager.reports.procedures', 'Tindakan', 'bx bx-first-aid', 'reports.procedures', 'report.view', 'Laporan tindakan.'),
                self::item('manager.reports.patients', 'Pasien', 'bx bx-user', 'reports.patients', 'report.view', 'Laporan pasien.'),
                self::item('manager.reports.pharmacy', 'Farmasi', 'bx bx-capsule', 'reports.pharmacy', 'report.view', 'Laporan farmasi.'),
            ]),
            self::item('manager.branches', 'Cabang', 'bx bx-git-branch', 'branches.index', 'branch.view', 'Cabang klinik.'),
            self::item('manager.master', 'Master Data', 'bx bx-data', null, 'clinic.view', 'Master data klinik.', [
                self::item('manager.master.clinic', 'Klinik', 'bx bx-building-house', 'management.clinic', 'clinic.view', 'Data klinik / poli.'),
                self::item('manager.master.services', 'Layanan', 'bx bx-list-check', 'management.services.index', 'service.view', 'Master layanan klinik.'),
                self::item('manager.master.procedures', 'Tindakan', 'bx bx-first-aid', 'management.procedures.index', 'procedure.view', 'Master tindakan.'),
                self::item('manager.master.tariffs', 'Tarif', 'bx bx-purchase-tag', 'management.tariffs.index', 'tariff.view', 'Master tarif layanan.'),
                self::item('manager.master.medicines', 'Obat', 'bx bx-plus-medical', 'management.medicines.index', 'medicine.view', 'Master obat.'),
                self::item('manager.master.payers', 'Penjamin', 'bx bx-id-card', 'management.payers.index', 'payer.view', 'Master penjamin.'),
            ]),
            self::item('manager.users', 'Users & Roles', 'bx bx-group', null, 'users.view', 'Pengguna dan peran klinik.', [
                self::item('manager.users.index', 'Users', 'bx bx-user', 'users.index', 'users.view', 'Pengguna klinik.'),
                self::item('manager.roles.index', 'Roles', 'bx bx-shield-quarter', 'roles.index', 'roles.view', 'Peran klinik.'),
            ]),
            self::item('manager.integrations', 'Integrasi', 'bx bx-plug', 'integrations.index', 'integration.view', 'Integrasi eksternal klinik.'),
            self::item('manager.audit', 'Audit Log', 'bx bx-shield', 'logs.index', 'logs.view', 'Audit log klinik.'),
            self::item('manager.settings', 'Pengaturan Klinik', 'bx bx-cog', 'settings.clinic', 'setting.view', 'Pengaturan operasional klinik.'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function registration(): array
    {
        return [
            self::item('registration.dashboard', 'Dashboard Pendaftaran', 'bx bx-home-circle', 'dashboard', 'dashboard.view', 'Ringkasan kerja pendaftaran.'),
            self::item('registration.group', 'Pendaftaran', 'bx bx-user-plus', null, 'registration.view', 'Proses pendaftaran pasien.', [
                self::item('registration.new', 'Pendaftaran Baru', 'bx bx-plus', 'registration.new', 'registration.create', 'Form pendaftaran pasien baru.'),
                self::item('registration.patients', 'Pasien', 'bx bx-user', 'registration.patients', 'patient.view', 'Manajemen data pasien klinik.'),
                self::item('registration.visits.today', 'Kunjungan Hari Ini', 'bx bx-calendar-check', 'registration.visits.today', 'visit.view', 'Kunjungan pasien hari ini.'),
                self::item('registration.history', 'Riwayat Kunjungan', 'bx bx-history', 'registration.history', 'registration.view', 'Riwayat kunjungan pasien.'),
            ]),
            self::item('registration.queue', 'Antrean', 'bx bx-list-ol', null, 'queue.view', 'Antrean pendaftaran.', [
                self::item('registration.queue.today', 'Antrean Hari Ini', 'bx bx-time-five', 'queue.today', 'queue.view', 'Antrean pelayanan hari ini.'),
                self::item('registration.queue.monitor', 'Monitor Antrean', 'bx bx-tv', 'queue.monitor', 'queue.view', 'Monitor tampilan antrean.'),
            ]),
            self::item('registration.schedule', 'Jadwal Dokter', 'bx bx-calendar', null, 'schedule.view', 'Jadwal dokter hari ini.', [
                self::item('registration.schedule.today', 'Jadwal Hari Ini', 'bx bx-calendar-event', 'doctors.schedule.today', 'schedule.view', 'Jadwal praktik dokter hari ini.'),
            ]),
            self::item('registration.payers', 'Penjamin', 'bx bx-id-card', null, 'payer.view', 'Penjamin pembiayaan pasien.', [
                self::item('registration.payers.general', 'Umum', 'bx bx-user', 'registration.payers.general', 'payer.view', 'Penjamin umum.'),
                self::item('registration.payers.bpjs', 'BPJS', 'bx bx-card', 'registration.payers.bpjs', 'payer.view', 'Penjamin BPJS.'),
                self::item('registration.payers.insurance', 'Asuransi', 'bx bx-shield-plus', 'registration.payers.insurance', 'payer.view', 'Penjamin asuransi.'),
                self::item('registration.payers.corporate', 'Corporate', 'bx bx-buildings', 'registration.payers.corporate', 'payer.view', 'Penjamin perusahaan.'),
                self::item('registration.payers.membership', 'Membership', 'bx bx-star', 'registration.payers.membership', 'payer.view', 'Penjamin membership.'),
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function dentist(): array
    {
        return [
            self::heading('dentist.group.dashboard', 'Dashboard', [
                self::item('dentist.dashboard', 'Dashboard Dokter', 'bx bx-home-circle', 'dashboard', 'dashboard.view', 'Ringkasan pelayanan dokter.'),
            ]),
            self::heading('dentist.group.care', 'Pelayanan', [
                self::item('dentist.care.examination', 'Pemeriksaan', 'bx bx-search-alt', 'examinations.index', 'examination.view', 'Antrean, panggil pasien, dan buka pelayanan.'),
                self::item('dentist.history', 'Riwayat Pasien', 'bx bx-history', 'patients.history', 'patient_history.view', 'Riwayat kunjungan dan rekam medis pasien.'),
            ]),
            self::heading('dentist.group.other', 'Lainnya', [
                self::item('dentist.schedule', 'Jadwal Saya', 'bx bx-calendar', 'doctors.schedule.mine', 'schedule.view', 'Jadwal praktik dokter yang login.'),
                self::item('dentist.reports', 'Laporan Pribadi', 'bx bx-bar-chart-alt-2', 'reports.personal', 'report.view', 'Laporan kinerja dokter.'),
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function dentalAssistant(): array
    {
        return [
            self::item('assistant.dashboard', 'Dashboard', 'bx bx-home-circle', 'dashboard', 'dashboard.view', 'Ringkasan kerja asisten dokter.'),
            self::item('assistant.queue', 'Antrean', 'bx bx-list-ol', null, 'queue.view', 'Antrean pasien.', [
                self::item('assistant.queue.waiting', 'Pasien Menunggu', 'bx bx-time-five', 'queue.waiting', 'queue.view', 'Pasien yang menunggu dilayani.'),
                self::item('assistant.queue.called', 'Pasien Dipanggil', 'bx bx-bell', 'queue.called', 'queue.view', 'Pasien yang sedang dipanggil.'),
            ]),
            self::item('assistant.care', 'Pelayanan', 'bx bx-plus-medical', null, 'examination.view', 'Pendampingan pelayanan dokter.', [
                self::item('assistant.care.initial', 'Pemeriksaan Awal', 'bx bx-search-alt', 'examinations.initial', 'examination.view', 'Pemeriksaan awal pasien.'),
                self::item('assistant.care.anamnesis', 'Anamnesis', 'bx bx-comment-detail', 'examinations.anamnesis', 'anamnesis.view', 'Anamnesis pasien.'),
                self::item('assistant.care.medical-record', 'Rekam Medis', 'bx bx-folder-open', 'medical-record.index', 'medical_record.view', 'Rekam medis pasien.'),
                self::item('assistant.care.odontogram', 'Odontogram', 'bx bx-grid-alt', 'odontogram.index', 'odontogram.view', 'Odontogram pasien.'),
                self::item('assistant.care.procedures', 'Tindakan', 'bx bx-first-aid', 'procedures.index', 'procedure.view', 'Tindakan medis.'),
            ]),
            self::item('assistant.history', 'Riwayat Pasien', 'bx bx-history', 'patients.history', 'patient_history.view', 'Riwayat pasien.'),
            self::item('assistant.schedule', 'Jadwal', 'bx bx-calendar', 'doctors.schedule.today', 'schedule.view', 'Jadwal pelayanan hari ini.'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function nurse(): array
    {
        return [
            self::item('nurse.dashboard', 'Dashboard', 'bx bx-home-circle', 'dashboard', 'dashboard.view', 'Ringkasan kerja perawat.'),
            self::item('nurse.queue', 'Antrean', 'bx bx-list-ol', null, 'queue.view', 'Antrean pasien.', [
                self::item('nurse.queue.today', 'Antrean Hari Ini', 'bx bx-time-five', 'queue.today', 'queue.view', 'Antrean pelayanan hari ini.'),
                self::item('nurse.queue.waiting', 'Pasien Menunggu', 'bx bx-user-voice', 'queue.waiting', 'queue.view', 'Pasien yang menunggu dilayani.'),
            ]),
            self::item('nurse.care', 'Pelayanan', 'bx bx-plus-medical', null, 'examination.view', 'Pelayanan keperawatan.', [
                self::item('nurse.care.initial', 'Pemeriksaan Awal', 'bx bx-search-alt', 'examinations.initial', 'examination.view', 'Pemeriksaan awal pasien.'),
                self::item('nurse.care.vitals', 'Tanda Vital', 'bx bx-heart', 'examinations.vitals', 'vital_sign.view', 'Pencatatan tanda vital.'),
                self::item('nurse.care.anamnesis', 'Anamnesis', 'bx bx-comment-detail', 'examinations.anamnesis', 'anamnesis.view', 'Anamnesis pasien.'),
                self::item('nurse.care.notes', 'Catatan Pelayanan', 'bx bx-note', 'examinations.notes', 'care_note.view', 'Catatan pelayanan perawat.'),
            ]),
            self::item('nurse.history', 'Riwayat Pasien', 'bx bx-history', 'patients.history', 'patient_history.view', 'Riwayat pasien.'),
            self::item('nurse.schedule', 'Jadwal', 'bx bx-calendar', 'doctors.schedule.today', 'schedule.view', 'Jadwal pelayanan.'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function pharmacy(): array
    {
        return [
            self::item('pharmacy.dashboard', 'Dashboard Farmasi', 'bx bx-home-circle', 'dashboard', 'dashboard.view', 'Ringkasan kerja farmasi.'),
            self::item('pharmacy.prescriptions', 'Resep', 'bx bx-receipt', null, 'prescription.view', 'Pengelolaan resep masuk.', [
                self::item('pharmacy.prescriptions.incoming', 'Resep Masuk', 'bx bx-log-in-circle', 'pharmacy.prescriptions.incoming', 'prescription.view', 'Resep yang baru masuk.'),
                self::item('pharmacy.prescriptions.processing', 'Diproses', 'bx bx-loader', 'pharmacy.prescriptions.processing', 'prescription.update', 'Resep yang sedang diproses.'),
                self::item('pharmacy.prescriptions.completed', 'Selesai', 'bx bx-check-circle', 'pharmacy.prescriptions.completed', 'prescription.view', 'Resep yang sudah selesai.'),
                self::item('pharmacy.prescriptions.history', 'Riwayat', 'bx bx-history', 'pharmacy.prescriptions.history', 'prescription.view', 'Riwayat resep farmasi.'),
            ]),
            self::item('pharmacy.medicines', 'Obat', 'bx bx-plus-medical', null, 'medicine.view', 'Master dan stok obat.', [
                self::item('pharmacy.medicines.index', 'Daftar Obat', 'bx bx-list-ul', 'pharmacy.medicines.index', 'medicine.view', 'Daftar obat klinik.'),
                self::item('pharmacy.medicines.stock', 'Stok', 'bx bx-package', 'pharmacy.stock.index', 'stock.view', 'Stok obat.'),
                self::item('pharmacy.medicines.batches', 'Batch', 'bx bx-layer', 'pharmacy.stock.batches', 'stock.view', 'Batch obat.'),
                self::item('pharmacy.medicines.expired', 'Expired', 'bx bx-error', 'pharmacy.stock.expired', 'stock.view', 'Obat kedaluwarsa.'),
                self::item('pharmacy.medicines.adjustments', 'Penyesuaian Stok', 'bx bx-slider-alt', 'pharmacy.stock.adjustments', 'stock.manage', 'Penyesuaian stok obat.'),
            ]),
            self::item('pharmacy.purchases', 'Pembelian', 'bx bx-cart', null, 'purchase.view', 'Pembelian obat.', [
                self::item('pharmacy.purchases.suppliers', 'Supplier', 'bx bx-store', 'pharmacy.purchases.suppliers', 'supplier.view', 'Supplier farmasi.'),
                self::item('pharmacy.purchases.orders', 'Purchase Order', 'bx bx-file', 'pharmacy.purchases.orders', 'purchase.view', 'Purchase order obat.'),
                self::item('pharmacy.purchases.receipts', 'Penerimaan', 'bx bx-package', 'pharmacy.purchases.receipts', 'purchase.manage', 'Penerimaan barang farmasi.'),
            ]),
            self::item('pharmacy.reports', 'Laporan', 'bx bx-bar-chart-alt-2', null, 'report.view', 'Laporan farmasi.', [
                self::item('pharmacy.reports.stock', 'Stok', 'bx bx-package', 'pharmacy.reports.stock', 'report.view', 'Laporan stok obat.'),
                self::item('pharmacy.reports.outgoing', 'Obat Keluar', 'bx bx-log-out-circle', 'pharmacy.reports.outgoing', 'report.view', 'Laporan obat keluar.'),
                self::item('pharmacy.reports.incoming', 'Obat Masuk', 'bx bx-log-in-circle', 'pharmacy.reports.incoming', 'report.view', 'Laporan obat masuk.'),
                self::item('pharmacy.reports.expired', 'Expired', 'bx bx-error-circle', 'pharmacy.reports.expired', 'report.view', 'Laporan obat kedaluwarsa.'),
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function cashier(): array
    {
        return [
            self::item('cashier.dashboard', 'Dashboard Kasir', 'bx bx-home-circle', 'dashboard', 'dashboard.view', 'Ringkasan kerja kasir.'),
            self::item('cashier.transactions', 'Transaksi', 'bx bx-credit-card', null, 'billing.view', 'Transaksi pembayaran pasien.', [
                self::item('cashier.invoices.today', 'Tagihan Hari Ini', 'bx bx-receipt', 'billing.invoices.today', 'billing.view', 'Tagihan pasien hari ini.'),
                self::item('cashier.payments', 'Pembayaran', 'bx bx-money', 'billing.payments', 'payment.view', 'Penerimaan pembayaran.'),
                self::item('cashier.receivables', 'Piutang', 'bx bx-wallet', 'billing.receivables', 'receivable.view', 'Piutang pasien.'),
                self::item('cashier.history', 'Riwayat Transaksi', 'bx bx-history', 'billing.history', 'billing.view', 'Riwayat transaksi kasir.'),
            ]),
            self::item('cashier.patients', 'Pasien', 'bx bx-user', 'registration.patients', 'patient.view', 'Pencarian data pasien untuk kasir.'),
            self::item('cashier.shifts', 'Shift Kasir', 'bx bx-time-five', null, 'cash_shift.view', 'Pengelolaan shift kasir.', [
                self::item('cashier.shifts.open', 'Buka Shift', 'bx bx-log-in-circle', 'cashier.shifts.open', 'cash_shift.manage', 'Pembukaan shift kasir.'),
                self::item('cashier.shifts.transactions', 'Transaksi Shift', 'bx bx-list-ul', 'cashier.shifts.transactions', 'cash_shift.view', 'Transaksi pada shift berjalan.'),
                self::item('cashier.shifts.close', 'Tutup Shift', 'bx bx-log-out-circle', 'cashier.shifts.close', 'cash_shift.manage', 'Penutupan shift kasir.'),
            ]),
            self::item('cashier.reports', 'Laporan', 'bx bx-bar-chart-alt-2', null, 'report.view', 'Laporan kasir.', [
                self::item('cashier.reports.index', 'Laporan Kasir', 'bx bx-file', 'cashier.reports', 'report.view', 'Laporan transaksi kasir.'),
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function finance(): array
    {
        return [
            self::item('finance.dashboard', 'Dashboard Keuangan', 'bx bx-home-circle', 'dashboard', 'dashboard.view', 'Ringkasan keuangan klinik.'),
            self::item('finance.revenue', 'Pendapatan', 'bx bx-dollar', null, 'revenue.view', 'Pendapatan klinik.', [
                self::item('finance.revenue.daily', 'Pendapatan Harian', 'bx bx-calendar', 'finance.revenue.daily', 'revenue.view', 'Pendapatan harian klinik.'),
                self::item('finance.revenue.monthly', 'Pendapatan Bulanan', 'bx bx-calendar-alt', 'finance.revenue.monthly', 'revenue.view', 'Pendapatan bulanan klinik.'),
                self::item('finance.revenue.doctors', 'Pendapatan per Dokter', 'bx bx-user', 'finance.revenue.doctors', 'revenue.view', 'Pendapatan berdasarkan dokter.'),
            ]),
            self::item('finance.expenses', 'Pengeluaran', 'bx bx-minus-circle', null, 'expense.view', 'Pengeluaran klinik.', [
                self::item('finance.expenses.index', 'Pengeluaran', 'bx bx-receipt', 'finance.expenses', 'expense.view', 'Daftar pengeluaran.'),
                self::item('finance.expenses.categories', 'Kategori', 'bx bx-category', 'finance.expenses.categories', 'expense.view', 'Kategori pengeluaran.'),
                self::item('finance.expenses.suppliers', 'Supplier', 'bx bx-store', 'finance.expenses.suppliers', 'supplier.view', 'Supplier pengeluaran.'),
            ]),
            self::item('finance.receivables', 'Piutang', 'bx bx-wallet', 'finance.receivables', 'receivable.view', 'Piutang klinik.'),
            self::item('finance.payables', 'Hutang', 'bx bx-credit-card', 'finance.payables', 'payable.view', 'Hutang klinik.'),
            self::item('finance.cash-bank', 'Kas & Bank', 'bx bx-bank', 'finance.cash-bank', 'cash_bank.view', 'Kas dan rekening bank klinik.'),
            self::item('finance.reports', 'Laporan', 'bx bx-bar-chart-alt-2', null, 'report.view', 'Laporan keuangan.', [
                self::item('finance.reports.revenue', 'Pendapatan', 'bx bx-dollar', 'finance.reports.revenue', 'report.view', 'Laporan pendapatan.'),
                self::item('finance.reports.expenses', 'Pengeluaran', 'bx bx-minus-circle', 'finance.reports.expenses', 'report.view', 'Laporan pengeluaran.'),
                self::item('finance.reports.cashflow', 'Arus Kas', 'bx bx-transfer', 'finance.reports.cashflow', 'report.view', 'Laporan arus kas.'),
                self::item('finance.reports.receivables', 'Piutang', 'bx bx-wallet', 'finance.reports.receivables', 'report.view', 'Laporan piutang.'),
                self::item('finance.reports.profit-loss', 'Laba Rugi', 'bx bx-line-chart', 'finance.reports.profit-loss', 'report.view', 'Laporan laba rugi.'),
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function auditor(): array
    {
        return [
            self::item('auditor.dashboard', 'Dashboard', 'bx bx-home-circle', 'dashboard', 'dashboard.view', 'Ringkasan tampilan auditor.'),
            self::item('auditor.registration', 'Pendaftaran', 'bx bx-user-plus', null, 'registration.view', 'Riwayat pendaftaran.', [
                self::item('auditor.registration.history', 'Riwayat', 'bx bx-history', 'registration.history', 'registration.view', 'Riwayat pendaftaran pasien.'),
            ]),
            self::item('auditor.patients', 'Pasien', 'bx bx-user', null, 'patient.view', 'Data pasien.', [
                self::item('auditor.patients.index', 'Daftar Pasien', 'bx bx-list-ul', 'registration.patients', 'patient.view', 'Daftar pasien klinik.'),
            ]),
            self::item('auditor.medical-record', 'Rekam Medis', 'bx bx-folder-open', null, 'medical_record.view', 'Rekam medis.', [
                self::item('auditor.medical-record.history', 'Riwayat', 'bx bx-history', 'medical-record.index', 'medical_record.view', 'Riwayat rekam medis.'),
            ]),
            self::item('auditor.finance', 'Keuangan', 'bx bx-wallet', null, 'finance.view', 'Informasi keuangan.', [
                self::item('auditor.finance.revenue', 'Pendapatan', 'bx bx-dollar', 'finance.revenue.daily', 'revenue.view', 'Pendapatan klinik.'),
                self::item('auditor.finance.expenses', 'Pengeluaran', 'bx bx-minus-circle', 'finance.expenses', 'expense.view', 'Pengeluaran klinik.'),
            ]),
            self::item('auditor.reports', 'Laporan', 'bx bx-bar-chart-alt-2', null, 'report.view', 'Laporan klinik.', [
                self::item('auditor.reports.operational', 'Operasional', 'bx bx-cog', 'reports.operational', 'report.view', 'Laporan operasional.'),
                self::item('auditor.reports.medical', 'Medis', 'bx bx-plus-medical', 'reports.medical', 'report.view', 'Laporan medis.'),
                self::item('auditor.reports.finance', 'Keuangan', 'bx bx-wallet', 'reports.finance', 'report.view', 'Laporan keuangan.'),
            ]),
            self::item('auditor.audit', 'Audit Log', 'bx bx-shield', 'logs.index', 'logs.view', 'Audit log sistem.'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    protected static function heading(string $name, string $title, array $children): array
    {
        return [
            'name' => $name,
            'title' => $title,
            'icon' => null,
            'route' => null,
            'permission' => '',
            'type' => MenuType::Heading->value,
            'description' => '',
            'children' => $children,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    protected static function item(
        string $name,
        string $title,
        string $icon,
        ?string $route,
        string $permission,
        string $description = '',
        array $children = [],
    ): array {
        return [
            'name' => $name,
            'title' => $title,
            'icon' => $icon,
            'route' => $route,
            'permission' => $permission,
            'description' => $description !== '' ? $description : 'Modul ini akan dikembangkan pada tahap berikutnya.',
            'children' => $children,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, array{name: string, uri: string, permission: string, scope: string}>  $routes
     * @param  list<string>  $skip
     */
    protected static function collectRoutes(array $items, array &$routes, array $skip, string $scope): void
    {
        foreach ($items as $item) {
            $route = $item['route'] ?? null;

            if (is_string($route) && $route !== '' && ! in_array($route, $skip, true) && ! isset($routes[$route])) {
                $routes[$route] = [
                    'name' => $route,
                    'uri' => self::uriFromRoute($route),
                    'permission' => $item['permission'],
                    'scope' => $scope,
                ];
            }

            self::collectRoutes($item['children'] ?? [], $routes, $skip, $scope);
        }
    }

    public static function uriFromRoute(string $route): string
    {
        $parts = explode('.', $route);
        $last = $parts[array_key_last($parts)];

        if ($last === 'index') {
            array_pop($parts);
        } elseif ($last === 'create') {
            array_pop($parts);

            return '/'.implode('/', $parts).'/create';
        } elseif ($last === 'show') {
            array_pop($parts);

            return '/'.implode('/', $parts).'/detail';
        }

        return '/'.implode('/', $parts);
    }
}
