<?php

namespace App\Support\Integrations;

use App\Enums\IntegrationLogStatus;
use App\Models\IntegrationLog;

final class SatuSehatLogView
{
    /**
     * @return array{
     *     headline: string,
     *     when: string,
     *     status: IntegrationLogStatus,
     *     summary: string,
     *     error: string|null,
     *     encounter_id: string|null,
     *     visit_id: int|string|null,
     *     skipped: list<string>,
     *     steps: list<array{title: string, method: string, ok: bool, result: string, note: string|null}>,
     *     resources: list<array{type: string, state: string, label: string, badge: string, items: list<array{title: string, ok: bool, result: string, note: string|null}>}>,
     *     request_json: string,
     *     response_json: string
     * }
     */
    public static function from(IntegrationLog $log): array
    {
        $request = is_array($log->request_payload) ? $log->request_payload : [];
        $response = is_array($log->response_payload) ? $log->response_payload : [];
        $skipped = self::skipped($response);
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        return [
            'headline' => self::headline((string) $log->action),
            'when' => $log->created_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? '-',
            'status' => $log->status,
            'summary' => self::summary($log->status, $skipped),
            'error' => filled($log->error_message) ? trim((string) $log->error_message) : null,
            'encounter_id' => is_string($response['encounter_id'] ?? null) && $response['encounter_id'] !== ''
                ? $response['encounter_id']
                : null,
            'visit_id' => is_scalar($request['visit_id'] ?? null) ? $request['visit_id'] : null,
            'skipped' => $skipped,
            'steps' => self::steps($request, $response),
            'resources' => self::resources($request, $response),
            'request_json' => (string) json_encode($request, $flags),
            'response_json' => (string) json_encode($response, $flags),
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<string>
     */
    private static function skipped(array $response): array
    {
        $notes = [];

        foreach ($response['skipped'] ?? [] as $note) {
            if (is_string($note) && trim($note) !== '') {
                $notes[] = trim($note);
            }
        }

        return $notes;
    }

    /**
     * @param  list<string>  $skipped
     */
    private static function summary(IntegrationLogStatus $status, array $skipped): string
    {
        return match ($status) {
            IntegrationLogStatus::Success => $skipped === []
                ? 'Semua data pada pengiriman ini diterima SATUSEHAT.'
                : 'Kunjungan terkirim, tetapi ada bagian yang tidak ikut dikirim.',
            IntegrationLogStatus::Failed => 'Pengiriman belum berhasil. Perbaiki data yang disebut di bawah, lalu kirim ulang.',
            IntegrationLogStatus::Queued => 'Pengiriman masih menunggu giliran.',
        };
    }

    private static function headline(string $action): string
    {
        return match ($action) {
            'encounter.sync' => 'Pengiriman kunjungan',
            'patient.ihs' => 'Pencarian data pasien',
            'room.ihs' => 'Pengambilan IHS ruangan',
            default => 'Pengiriman SATUSEHAT',
        };
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $response
     * @return list<array{title: string, ok: bool, result: string, note: string|null}>
     */
    private static function steps(array $request, array $response): array
    {
        $sent = array_values(is_array($request['calls'] ?? null) ? $request['calls'] : []);
        $received = array_values(is_array($response['calls'] ?? null) ? $response['calls'] : []);
        $steps = [];

        for ($index = 0; $index < max(count($sent), count($received)); $index++) {
            $call = is_array($sent[$index] ?? null) ? $sent[$index] : [];
            $reply = is_array($received[$index] ?? null) ? $received[$index] : [];
            $body = is_array($call['body'] ?? null) ? $call['body'] : [];
            $replyBody = is_array($reply['body'] ?? null) ? $reply['body'] : [];
            $status = isset($reply['status']) && is_numeric($reply['status']) ? (int) $reply['status'] : null;
            $ok = $status !== null && $status >= 200 && $status < 300;

            $steps[] = [
                'title' => self::title($call, $body),
                'method' => strtoupper((string) ($call['method'] ?? '')),
                'ok' => $ok,
                'result' => self::resultLabel($status),
                'note' => $ok ? null : self::issue($replyBody),
            ];
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $call
     * @param  array<string, mixed>  $body
     */
    private static function title(array $call, array $body): string
    {
        $method = strtoupper((string) ($call['method'] ?? ''));
        $url = (string) ($call['url'] ?? '');

        if ($method === 'GET') {
            return match (true) {
                str_contains($url, 'Practitioner') => 'Mencari data dokter',
                str_contains($url, 'Patient') => 'Mencari data pasien',
                str_contains($url, 'Location') => 'Mencari lokasi pelayanan',
                str_contains($url, 'Organization') => 'Mencari data organisasi',
                default => 'Membaca data SATUSEHAT',
            };
        }

        $type = (string) ($body['resourceType'] ?? '');

        return match ($type) {
            'Encounter' => ($body['status'] ?? '') === 'finished' ? 'Menutup kunjungan' : 'Membuka kunjungan',
            'Condition' => self::labeled('Diagnosis', self::text($body['code']['text'] ?? null) ?? self::codingDisplay($body['code'] ?? null)),
            'Procedure' => self::labeled('Tindakan', self::codingDisplay($body['code'] ?? null)),
            'Observation' => self::labeled('Pemeriksaan', self::observationName($body)),
            'Patient' => 'Mendaftarkan pasien',
            'Practitioner' => 'Mendaftarkan dokter',
            default => $type !== '' ? 'Mengirim '.$type : 'Mengirim data',
        };
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function observationName(array $body): ?string
    {
        $name = self::plain(self::codingDisplay($body['code'] ?? null));
        $value = self::text($body['valueCodeableConcept']['coding'][0]['display'] ?? null)
            ?? self::text($body['valueCodeableConcept']['text'] ?? null)
            ?? self::quantity($body['valueQuantity'] ?? null);

        if ($name && $value) {
            return $name.': '.$value;
        }

        return $value ?? $name;
    }

    private static function labeled(string $kind, ?string $detail): string
    {
        return $detail ? $kind.': '.$detail : $kind;
    }

    private static function codingDisplay(mixed $code): ?string
    {
        if (! is_array($code)) {
            return null;
        }

        return self::text($code['coding'][0]['display'] ?? null) ?? self::text($code['text'] ?? null);
    }

    private static function plain(?string $display): ?string
    {
        if ($display === null) {
            return null;
        }

        return match ($display) {
            'Systolic blood pressure' => 'Tekanan darah atas',
            'Diastolic blood pressure' => 'Tekanan darah bawah',
            'Heart rate' => 'Nadi',
            'Body temperature' => 'Suhu tubuh',
            'Respiratory rate' => 'Napas',
            'Dental occlusion' => 'Oklusi',
            'Torus mandibularis' => 'Torus mandibularis',
            'Torus palatinus' => 'Torus palatinus',
            'Physical findings of Palate' => 'Langit-langit mulut',
            'Diastema of teeth' => 'Celah antar gigi',
            'Anomaly of tooth position' => 'Gigi anomali',
            default => $display,
        };
    }

    /**
     * @param  mixed  $quantity
     */
    private static function quantity(mixed $quantity): ?string
    {
        if (! is_array($quantity) || ! isset($quantity['value'])) {
            return null;
        }

        $unit = self::text($quantity['unit'] ?? null);

        return trim($quantity['value'].($unit ? ' '.$unit : ''));
    }

    private static function resultLabel(?int $status): string
    {
        if ($status === null) {
            return 'Belum ada jawaban';
        }

        if ($status >= 200 && $status < 300) {
            return 'Diterima';
        }

        return match (true) {
            $status === 401 || $status === 403 => 'Akses ditolak',
            $status === 404 => 'Data tidak ditemukan',
            $status === 409 => 'Data sudah pernah dikirim',
            $status >= 500 => 'Server SATUSEHAT sedang bermasalah',
            default => 'Ditolak',
        };
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function issue(array $body): ?string
    {
        $message = self::text($body['error'] ?? null);

        if ($message) {
            return $message;
        }

        $issues = $body['issue'] ?? null;

        if (! is_array($issues)) {
            return null;
        }

        $notes = [];

        foreach ($issues as $issue) {
            if (! is_array($issue)) {
                continue;
            }

            $text = self::text($issue['diagnostics'] ?? null)
                ?? self::text($issue['details']['text'] ?? null);

            if ($text) {
                $notes[] = $text;
            }
        }

        if ($notes === []) {
            return null;
        }

        return \Illuminate\Support\Str::limit(implode(' ', $notes), 240);
    }

    /**
     * @return list<string>
     */
    private static function resourceOrder(): array
    {
        return [
            'Encounter',
            'Condition',
            'Observation',
            'Procedure',
            'Composition',
            'Medication',
            'MedicationRequest',
            'MedicationDispense',
            'AllergyIntolerance',
            'ImagingStudy',
            'ServiceRequest',
            'ClinicalImpression',
            'Immunization',
            'QuestionnaireResponse',
            'MedicationStatement',
            'CarePlan',
            'Specimen',
            'DiagnosticReport',
            'EpisodeOfCare',
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $response
     * @return list<array{type: string, state: string, label: string, badge: string, items: list<array{title: string, ok: bool, result: string, note: string|null}>}>
     */
    private static function resources(array $request, array $response): array
    {
        $grouped = [];

        foreach (self::steps($request, $response) as $index => $step) {
            $sent = array_values(is_array($request['calls'] ?? null) ? $request['calls'] : []);
            $call = is_array($sent[$index] ?? null) ? $sent[$index] : [];
            $body = is_array($call['body'] ?? null) ? $call['body'] : [];
            $type = self::resourceType($call, $body);
            $grouped[$type][] = $step;
        }

        $hasCalls = $grouped !== [];
        $rows = [];

        foreach (self::resourceOrder() as $type) {
            $rows[] = self::resourceRow($type, $grouped[$type] ?? [], $hasCalls);
            unset($grouped[$type]);
        }

        foreach ($grouped as $type => $items) {
            $rows[] = self::resourceRow((string) $type, $items, false);
        }

        return $rows;
    }

    /**
     * @param  list<array{title: string, method: string, ok: bool, result: string, note: string|null}>  $items
     * @return array{type: string, state: string, label: string, badge: string, items: list<array{title: string, method: string, ok: bool, result: string, note: string|null}>}
     */
    private static function resourceRow(string $type, array $items, bool $hasCalls): array
    {
        $writes = 0;
        $failed = false;
        $read = false;

        foreach ($items as $item) {
            if (! $item['ok']) {
                $failed = true;
            }

            if (($item['method'] ?? '') === 'GET') {
                $read = true;
            } else {
                $writes++;
            }
        }

        [$state, $label, $badge] = match (true) {
            $items === [] && ! $hasCalls => ['pending', 'Belum ada', 'badge bg-secondary'],
            $items === [] => ['absent', 'Tidak dikirim', 'badge bg-secondary'],
            $failed => ['failed', 'Gagal', 'badge bg-danger'],
            $writes > 0 => ['sent', 'Terkirim', 'badge bg-success'],
            $read => ['read', 'Dibaca', 'badge bg-info'],
            default => ['sent', 'Terkirim', 'badge bg-success'],
        };

        return [
            'type' => $type,
            'state' => $state,
            'label' => $label,
            'badge' => $badge,
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $call
     * @param  array<string, mixed>  $body
     */
    private static function resourceType(array $call, array $body): string
    {
        $path = (string) parse_url((string) ($call['url'] ?? ''), PHP_URL_PATH);

        if (preg_match('#/(?:fhir-r4/v1|fhir)/([A-Z][A-Za-z]+)#', $path, $match) === 1) {
            return $match[1];
        }

        $type = (string) ($body['resourceType'] ?? '');

        return $type !== '' && ! in_array($type, ['Bundle', 'OperationOutcome'], true) ? $type : 'Lainnya';
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
