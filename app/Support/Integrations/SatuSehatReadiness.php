<?php

namespace App\Support\Integrations;

use App\Enums\RoomType;
use App\Models\Branch;
use App\Models\BranchSatuSehatSetting;
use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Room;
use App\Models\TenantIntegration;
use Illuminate\Support\Collection;

final class SatuSehatReadiness
{
    /**
     * @return array{
     *     summary: array<string, mixed>,
     *     sections: list<array{key: string, title: string, items: list<array<string, mixed>>}>
     * }
     */
    public function forBranch(
        TenantIntegration $settings,
        ?Branch $branch,
        ?BranchSatuSehatSetting $branchSetting,
    ): array {
        $config = $this->configItems($settings, $branchSetting);
        $branchItems = $this->branchItems($branch, $branchSetting);
        $roomItems = $this->roomItems($branch);
        $patientBlock = $this->patientBlock($branch);
        $doctorBlock = $this->doctorBlock();
        $medicineBlock = $this->medicineBlock();

        $sections = [
            ['key' => 'config', 'title' => 'Konfigurasi SATUSEHAT', 'items' => $config],
            ['key' => 'branch', 'title' => 'Cabang', 'items' => $branchItems],
            ['key' => 'rooms', 'title' => 'Poli / ruangan', 'items' => $roomItems],
            ['key' => 'patients', 'title' => 'Pasien & IHS', 'items' => $patientBlock['items']],
            ['key' => 'doctors', 'title' => 'Dokter & IHS', 'items' => $doctorBlock['items']],
            ['key' => 'medicines', 'title' => 'Obat & kode KFA', 'items' => $medicineBlock['items']],
        ];

        $critical = 0;
        $pass = 0;
        $total = 0;

        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                if (! ($item['required'] ?? true)) {
                    continue;
                }

                $total++;
                if (($item['status'] ?? '') === 'pass') {
                    $pass++;
                } else {
                    $critical++;
                }
            }
        }

        $connection = match (true) {
            $settings->fake => 'sandbox_lokal',
            (bool) ($branchSetting?->connection_ok) => 'ok',
            filled($branchSetting?->last_error) => 'fail',
            default => 'untested',
        };

        return [
            'summary' => [
                'config' => $this->countSection($config),
                'branch' => $this->countSection($branchItems),
                'rooms' => $this->countSection($roomItems),
                'patients' => $patientBlock['stats'],
                'doctors' => $doctorBlock['stats'],
                'medicines' => $medicineBlock['stats'],
                'connection' => $connection,
                'connection_label' => match ($connection) {
                    'ok' => 'Berhasil',
                    'fail' => 'Gagal',
                    'sandbox_lokal' => 'Sandbox lokal (tanpa API)',
                    default => 'Belum diuji',
                },
                'validated_at' => $branchSetting?->validated_at,
                'pass' => $pass,
                'total' => $total,
                'critical_gaps' => $critical,
                'overall_pct' => $total > 0 ? (int) round(($pass / $total) * 100) : null,
                'ready_to_sync' => (bool) ($settings->enabled && $branchSetting?->readyToSync()),
                'module_enabled' => (bool) $settings->enabled,
                'branch_name' => $branch?->name,
            ],
            'sections' => $sections,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{done: int, total: int, label: string}
     */
    protected function countSection(array $items): array
    {
        $required = array_values(array_filter($items, fn (array $item) => $item['required'] ?? true));
        $done = count(array_filter($required, fn (array $item) => ($item['status'] ?? '') === 'pass'));
        $total = count($required);

        return [
            'done' => $done,
            'total' => $total,
            'label' => $total === 0 ? 'Belum diperiksa' : "{$done}/{$total} persyaratan terpenuhi",
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function configItems(TenantIntegration $settings, ?BranchSatuSehatSetting $branchSetting): array
    {
        $env = (string) ($branchSetting?->environment ?: '');
        $org = trim((string) ($branchSetting?->organization_id ?: ''));
        $clientId = trim((string) ($branchSetting?->client_id ?: ''));
        $hasSecret = (bool) ($branchSetting?->hasSecret() && ! $branchSetting?->secretIsUnreadable());
        $secretUnreadable = (bool) $branchSetting?->secretIsUnreadable();
        $connectionOk = (bool) $branchSetting?->connection_ok;
        $hasLocation = filled($branchSetting?->location_id);
        $syncOn = (bool) $branchSetting?->sync_enabled;
        $orgLooksUuid = $org !== '' && (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $org,
        );

        return [
            [
                'key' => 'module',
                'label' => 'Modul SATUSEHAT diaktifkan',
                'status' => $settings->enabled ? 'pass' : 'incomplete',
                'hint' => $settings->enabled
                    ? 'Modul aktif untuk tenant ini.'
                    : 'Centang “Aktifkan modul SATUSEHAT” lalu simpan.',
                'required' => true,
            ],
            [
                'key' => 'environment',
                'label' => 'Environment dipilih',
                'status' => in_array($env, ['sandbox', 'production'], true) ? 'pass' : 'incomplete',
                'hint' => in_array($env, ['sandbox', 'production'], true)
                    ? 'Environment: '.($env === 'production' ? 'Production' : 'Sandbox').($settings->fake ? ' (+ sandbox lokal)' : '')
                    : 'Pilih Sandbox atau Production sesuai kredensial portal SATUSEHAT.',
                'required' => true,
            ],
            [
                'key' => 'organization_id',
                'label' => 'Organization ID dikonfigurasi',
                'status' => match (true) {
                    $org === '' => 'incomplete',
                    ! $orgLooksUuid => 'action_needed',
                    default => 'pass',
                },
                'hint' => match (true) {
                    $org === '' => 'Isi Organization ID fasilitas dari portal SATUSEHAT.',
                    ! $orgLooksUuid => 'Nilai terisi, tetapi format UUID tidak standar. Pastikan ID dari portal benar.',
                    default => 'Organization ID tersimpan'.(filled($branchSetting?->organization_name) ? ' ('.$branchSetting->organization_name.')' : '').'.',
                },
                'required' => true,
            ],
            [
                'key' => 'client_id',
                'label' => 'Client ID dikonfigurasi',
                'status' => $clientId !== '' ? 'pass' : 'incomplete',
                'hint' => $clientId !== ''
                    ? 'Client ID tersimpan untuk cabang ini.'
                    : 'Isi Client ID dari environment yang sama dengan Organization.',
                'required' => true,
            ],
            [
                'key' => 'client_secret',
                'label' => 'Client Secret dikonfigurasi',
                'status' => match (true) {
                    $secretUnreadable => 'error',
                    $hasSecret => 'pass',
                    default => 'incomplete',
                },
                'hint' => match (true) {
                    $secretUnreadable => 'Secret tersimpan tidak terbaca (APP_KEY berubah?). Isi ulang, jangan tampilkan nilainya.',
                    $hasSecret => 'Sudah dikonfigurasi (nilai tidak ditampilkan).',
                    default => 'Isi Client Secret, lalu simpan. Nilai disimpan terenkripsi.',
                },
                'required' => true,
            ],
            [
                'key' => 'auth',
                'label' => 'Autentikasi SATUSEHAT berhasil',
                'status' => match (true) {
                    $settings->fake => 'pass',
                    $connectionOk => 'pass',
                    filled($branchSetting?->last_error) => 'error',
                    default => 'action_needed',
                },
                'hint' => match (true) {
                    $settings->fake => 'Mode sandbox lokal: token Kemenkes tidak dipanggil.',
                    $connectionOk => 'Access token berhasil diperoleh'
                        .($branchSetting?->validated_at ? ' ('.$branchSetting->validated_at->timezone(config('app.timezone'))->format('d M Y H:i').')' : '').'.',
                    filled($branchSetting?->last_error) => 'Autentikasi gagal. Perbaiki kredensial lalu klik Uji koneksi. Detail aman ada di alert halaman.',
                    default => 'Klik “Uji koneksi” untuk meminta access token (Client Credentials).',
                },
                'required' => true,
            ],
            [
                'key' => 'fhir',
                'label' => 'Tes koneksi endpoint FHIR berhasil',
                'status' => match (true) {
                    $settings->fake => 'pass',
                    $connectionOk && filled($branchSetting?->organization_name) => 'pass',
                    $connectionOk => 'pass',
                    filled($branchSetting?->last_error) => 'error',
                    default => 'action_needed',
                },
                'hint' => match (true) {
                    $settings->fake => 'Sandbox lokal: Organization/Location disimulasikan.',
                    $connectionOk => 'Organization FHIR terbaca'
                        .(filled($branchSetting?->organization_name) ? ' ('.$branchSetting->organization_name.')' : '').'. Uji koneksi juga memuat daftar Location.',
                    filled($branchSetting?->last_error) => 'Panggilan FHIR gagal (Organization/Location). Perbaiki Organization ID atau akses, lalu uji lagi.',
                    default => 'Setelah token berhasil, uji koneksi membaca Organization dan daftar Location.',
                },
                'required' => true,
            ],
            [
                'key' => 'location',
                'label' => 'Lokasi pelayanan (Location) dipilih',
                'status' => $hasLocation ? 'pass' : ($connectionOk ? 'incomplete' : 'unchecked'),
                'hint' => $hasLocation
                    ? 'Lokasi: '.($branchSetting?->location_name ?: $branchSetting?->location_id)
                    : ($connectionOk
                        ? 'Pilih Location ID poli cabang, lalu simpan.'
                        : 'Uji koneksi dulu agar daftar Location dapat dimuat.'),
                'required' => true,
            ],
            [
                'key' => 'sync',
                'label' => 'Sinkronisasi otomatis aktif',
                'status' => $syncOn ? 'pass' : 'incomplete',
                'hint' => $syncOn
                    ? 'Kunjungan selesai akan masuk antrian pengiriman.'
                    : 'Centang “Aktifkan sinkronisasi otomatis” pada form lokasi.',
                'required' => true,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function branchItems(?Branch $branch, ?BranchSatuSehatSetting $branchSetting): array
    {
        if (! $branch) {
            return [[
                'key' => 'branch_missing',
                'label' => 'Cabang tersedia',
                'status' => 'error',
                'hint' => 'Pilih cabang di filter atas.',
                'required' => true,
            ]];
        }

        $fixUrl = route('branches.show', $branch);

        return [
            [
                'key' => 'branch_active',
                'label' => 'Cabang aktif: '.$branch->name,
                'status' => $branch->is_active ? 'pass' : 'incomplete',
                'hint' => $branch->is_active ? 'Cabang aktif.' : 'Aktifkan cabang agar dapat dipakai operasional.',
                'fix_url' => $fixUrl,
                'required' => true,
            ],
            [
                'key' => 'branch_address',
                'label' => 'Alamat cabang lengkap',
                'status' => filled($branch->address) && filled($branch->city) ? 'pass' : 'incomplete',
                'hint' => filled($branch->address) && filled($branch->city)
                    ? 'Alamat dan kota terisi.'
                    : 'Lengkapi alamat dan kota di data cabang (dibutuhkan saat sync Location).',
                'fix_url' => $fixUrl,
                'required' => true,
            ],
            [
                'key' => 'branch_geo',
                'label' => 'Koordinat lintang/bujur',
                'status' => filled($branch->latitude) && filled($branch->longitude) ? 'pass' : 'action_needed',
                'hint' => filled($branch->latitude) && filled($branch->longitude)
                    ? 'Koordinat tersedia untuk sync Location.'
                    : 'Isi lintang/bujur di cabang agar Location dapat didaftarkan/diperbarui.',
                'fix_url' => $fixUrl,
                'required' => true,
            ],
            [
                'key' => 'branch_phone',
                'label' => 'Kontak cabang',
                'status' => filled($branch->phone) ? 'pass' : 'incomplete',
                'hint' => filled($branch->phone) ? 'Nomor telepon terisi.' : 'Isi telepon cabang.',
                'fix_url' => $fixUrl,
                'required' => false,
            ],
            [
                'key' => 'branch_location_map',
                'label' => 'Pemetaan Location SATUSEHAT cabang',
                'status' => filled($branchSetting?->location_id) ? 'pass' : 'incomplete',
                'hint' => filled($branchSetting?->location_id)
                    ? 'Location ID cabang terhubung.'
                    : 'Pilih/sync Location setelah uji koneksi berhasil.',
                'required' => true,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function roomItems(?Branch $branch): array
    {
        if (! $branch) {
            return [];
        }

        /** @var Collection<int, Room> $rooms */
        $rooms = Room::query()
            ->withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->where('type', RoomType::Poli)
            ->with('satusehatLocation')
            ->orderBy('name')
            ->get();

        if ($rooms->isEmpty()) {
            return [[
                'key' => 'rooms_empty',
                'label' => 'Ada poli di cabang ini',
                'status' => 'action_needed',
                'hint' => 'Belum ada ruangan tipe Poli. Buat poli untuk pelayanan yang disinkronkan.',
                'fix_url' => route('management.rooms.index'),
                'required' => false,
            ]];
        }

        $items = [];

        foreach ($rooms as $room) {
            $mapped = filled($room->satusehatLocation?->satusehat_id);
            $active = (bool) $room->is_active;

            $items[] = [
                'key' => 'room:'.$room->id,
                'label' => $room->name.($active ? '' : ' (nonaktif)'),
                'status' => match (true) {
                    ! $active => 'incomplete',
                    $mapped => 'pass',
                    default => 'action_needed',
                },
                'hint' => match (true) {
                    ! $active => 'Aktifkan poli jika dipakai untuk kunjungan.',
                    $mapped => 'Sudah punya Location IHS.',
                    default => 'Belum punya Location SATUSEHAT. Ambil IHS dari halaman ruangan setelah lokasi cabang siap.',
                },
                'fix_url' => route('management.rooms.show', $room),
                'required' => $active,
            ];
        }

        return $items;
    }

    /**
     * @return array{items: list<array<string, mixed>>, stats: array<string, mixed>}
     */
    protected function patientBlock(?Branch $branch): array
    {
        if (! $branch) {
            return [
                'items' => [],
                'stats' => ['total' => 0, 'with_ihs' => 0, 'without_ihs' => 0, 'pct' => null],
            ];
        }

        $base = Patient::query()
            ->whereHas('visits', fn ($query) => $query->where('branch_id', $branch->id));

        $total = (clone $base)->count();
        $withIhs = (clone $base)->whereNotNull('satusehat_id')->where('satusehat_id', '<>', '')->count();
        $withoutIhs = max(0, $total - $withIhs);
        $pct = $total > 0 ? (int) round(($withIhs / $total) * 100) : null;

        $items = [[
            'key' => 'patients_ihs',
            'label' => 'Pasien cabang punya IHS Number',
            'status' => match (true) {
                $total === 0 => 'unchecked',
                $withoutIhs === 0 => 'pass',
                $pct !== null && $pct >= 80 => 'action_needed',
                default => 'incomplete',
            },
            'hint' => $total === 0
                ? 'Belum ada pasien dengan kunjungan di cabang ini.'
                : "{$withIhs}/{$total} pasien punya IHS ({$pct}%). IHS didapat saat sync/lookup — tidak wajib 100% sebelum aktivasi, tetapi kunjungan pasien tanpa IHS/NIK tidak bisa dikirim.",
            'fix_url' => route('registration.patients'),
            'required' => false,
            'meta' => compact('total', 'withIhs', 'withoutIhs', 'pct'),
        ]];

        $samples = (clone $base)
            ->where(function ($query) {
                $query->whereNull('satusehat_id')->orWhere('satusehat_id', '');
            })
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'name', 'medical_record_number']);

        foreach ($samples as $patient) {
            $items[] = [
                'key' => 'patient:'.$patient->id,
                'label' => $patient->name.' (RM '.($patient->medical_record_number ?: $patient->id).')',
                'status' => 'incomplete',
                'hint' => 'Belum punya IHS. Lengkapi NIK lalu lookup IHS dari detail pasien / saat sync kunjungan.',
                'fix_url' => route('registration.patients.show', $patient),
                'required' => false,
            ];
        }

        if ($withoutIhs > 5) {
            $items[] = [
                'key' => 'patients_more',
                'label' => '+'.($withoutIhs - 5).' pasien lain tanpa IHS',
                'status' => 'incomplete',
                'hint' => 'Lihat daftar pasien dan lengkapi secara bertahap. Jangan bulk-kirim ke SATUSEHAT hanya untuk checklist.',
                'fix_url' => route('registration.patients'),
                'required' => false,
            ];
        }

        return [
            'items' => $items,
            'stats' => [
                'total' => $total,
                'with_ihs' => $withIhs,
                'without_ihs' => $withoutIhs,
                'pct' => $pct,
                'label' => $pct === null ? 'Belum diperiksa' : "{$pct}% punya IHS ({$withIhs}/{$total})",
            ],
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, stats: array<string, mixed>}
     */
    protected function doctorBlock(): array
    {
        $base = Doctor::query();
        $total = (clone $base)->count();
        $withNik = (clone $base)->whereNotNull('nik')->where('nik', '<>', '')->count();
        $withIhs = (clone $base)->whereNotNull('satusehat_id')->where('satusehat_id', '<>', '')->count();
        $missingNik = max(0, $total - $withNik);
        $missingIhs = (clone $base)
            ->whereNotNull('nik')
            ->where('nik', '<>', '')
            ->where(function ($query) {
                $query->whereNull('satusehat_id')->orWhere('satusehat_id', '');
            })
            ->count();
        $pct = $withNik > 0 ? (int) round(($withIhs / $withNik) * 100) : ($total === 0 ? null : 0);

        $items = [
            [
                'key' => 'doctors_nik',
                'label' => 'Dokter punya NIK (syarat lookup Practitioner)',
                'status' => match (true) {
                    $total === 0 => 'unchecked',
                    $missingNik === 0 => 'pass',
                    default => 'incomplete',
                },
                'hint' => $total === 0
                    ? 'Belum ada dokter.'
                    : "{$withNik}/{$total} dokter punya NIK. Tanpa NIK, Encounter tidak dapat dikirim.",
                'fix_url' => route('doctors.index'),
                'required' => true,
            ],
            [
                'key' => 'doctors_ihs',
                'label' => 'Dokter ber-NIK punya IHS Practitioner',
                'status' => match (true) {
                    $withNik === 0 => 'unchecked',
                    $missingIhs === 0 => 'pass',
                    default => 'action_needed',
                },
                'hint' => $withNik === 0
                    ? 'Lengkapi NIK dokter terlebih dahulu.'
                    : "{$withIhs}/{$withNik} dokter ber-NIK punya IHS. Ambil IHS dari halaman edit dokter.",
                'fix_url' => route('doctors.index'),
                'required' => false,
            ],
        ];

        $samples = Doctor::query()
            ->with('user:id,name')
            ->where(function ($query) {
                $query->whereNull('nik')->orWhere('nik', '')
                    ->orWhereNull('satusehat_id')->orWhere('satusehat_id', '');
            })
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        foreach ($samples as $doctor) {
            $name = $doctor->user?->name ?: ('Dokter #'.$doctor->id);
            $needNik = ! filled($doctor->nik);
            $items[] = [
                'key' => 'doctor:'.$doctor->id,
                'label' => $name,
                'status' => $needNik ? 'incomplete' : 'action_needed',
                'hint' => $needNik
                    ? 'Belum punya NIK.'
                    : 'NIK ada, IHS belum. Klik ambil IHS di halaman edit.',
                'fix_url' => route('doctors.edit', $doctor),
                'required' => false,
            ];
        }

        return [
            'items' => $items,
            'stats' => [
                'total' => $total,
                'with_nik' => $withNik,
                'with_ihs' => $withIhs,
                'missing_nik' => $missingNik,
                'missing_ihs' => $missingIhs,
                'pct' => $pct,
                'label' => $pct === null
                    ? 'Belum diperiksa'
                    : "{$pct}% dokter ber-NIK punya IHS ({$withIhs}/{$withNik})",
            ],
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, stats: array<string, mixed>}
     */
    protected function medicineBlock(): array
    {
        $base = Medicine::query()->where('is_active', true);
        $total = (clone $base)->count();
        $withKfa = (clone $base)->whereNotNull('kfa_code')->where('kfa_code', '<>', '')->count();
        $withoutKfa = max(0, $total - $withKfa);
        $pct = $total > 0 ? (int) round(($withKfa / $total) * 100) : null;

        $items = [[
            'key' => 'medicines_kfa',
            'label' => 'Obat aktif punya kode KFA',
            'status' => match (true) {
                $total === 0 => 'unchecked',
                $withoutKfa === 0 => 'pass',
                $pct !== null && $pct >= 80 => 'action_needed',
                default => 'incomplete',
            },
            'hint' => $total === 0
                ? 'Belum ada obat aktif. KFA hanya dibutuhkan jika resep akan disinkronkan.'
                : "{$withKfa}/{$total} obat aktif punya KFA ({$pct}%). Tanpa KFA, item resep tidak dikirim ke SATUSEHAT.",
            'fix_url' => route('management.medicines.index'),
            'required' => false,
        ]];

        $samples = (clone $base)
            ->where(function ($query) {
                $query->whereNull('kfa_code')->orWhere('kfa_code', '');
            })
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name']);

        foreach ($samples as $medicine) {
            $items[] = [
                'key' => 'medicine:'.$medicine->id,
                'label' => $medicine->name,
                'status' => 'incomplete',
                'hint' => 'Isi kode KFA dari katalog Kemenkes agar MedicationRequest dapat dikirim.',
                'fix_url' => route('management.medicines.edit', $medicine),
                'required' => false,
            ];
        }

        if ($withoutKfa > 5) {
            $items[] = [
                'key' => 'medicines_more',
                'label' => '+'.($withoutKfa - 5).' obat lain tanpa KFA',
                'status' => 'incomplete',
                'hint' => 'Lengkapi secara bertahap dari master obat.',
                'fix_url' => route('management.medicines.index'),
                'required' => false,
            ];
        }

        return [
            'items' => $items,
            'stats' => [
                'total' => $total,
                'with_kfa' => $withKfa,
                'without_kfa' => $withoutKfa,
                'pct' => $pct,
                'label' => $pct === null ? 'Belum diperiksa' : "{$pct}% punya KFA ({$withKfa}/{$total})",
            ],
        ];
    }
}
