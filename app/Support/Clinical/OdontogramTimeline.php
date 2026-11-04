<?php

namespace App\Support\Clinical;

use App\Enums\ToothStatus;
use App\Models\OdontogramLog;
use App\Models\OdontogramSnapshot;
use App\Models\OdontogramTooth;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Support\Collection;

/**
 * Menyusun kondisi odontogram setelah tiap kunjungan secara kronologis.
 *
 * Sumber per gigi per kunjungan, berurutan:
 * 1. odontogram_snapshots (lengkap: kondisi, permukaan, detail).
 * 2. odontogram_logs lama (hanya kode status; ditandai perkiraan), kecuali log terakhir gigi itu
 *    tanpa snapshot sesudahnya — kondisinya sama dengan odontogram aktif saat ini.
 */
final class OdontogramTimeline
{
    /** @var Collection<int, Visit> */
    protected Collection $visits;

    /** @var array<int, array<string, list<array<string, mixed>>>> */
    protected array $states = [];

    /** @var array<int, array<string, array{before: list<array<string, mixed>>, after: list<array<string, mixed>>, approximate: bool}>> */
    protected array $changes = [];

    /** @var array<string, list<array<string, mixed>>> */
    protected array $current = [];

    public static function for(Patient $patient): self
    {
        return (new self)->build($patient);
    }

    protected function build(Patient $patient): self
    {
        $this->visits = Visit::query()
            ->where('patient_id', $patient->id)
            ->with('doctor:id,name')
            ->orderBy('visit_date')
            ->orderBy('id')
            ->get(['id', 'patient_id', 'doctor_id', 'branch_id', 'visit_date', 'status']);

        $order = $this->visits->pluck('id')->flip();

        $snapshots = OdontogramSnapshot::query()
            ->where('patient_id', $patient->id)
            ->get(['id', 'visit_id', 'tooth_number', 'status', 'conditions'])
            ->groupBy('visit_id');

        $snapshotVisitIdx = [];
        foreach ($snapshots as $visitId => $rows) {
            foreach ($rows as $row) {
                $snapshotVisitIdx[$row->tooth_number][] = $order[$visitId] ?? -1;
            }
        }

        $logs = OdontogramLog::query()
            ->where('patient_id', $patient->id)
            ->orderBy('id')
            ->get(['id', 'visit_id', 'tooth_number', 'new_status']);

        $lastLogId = $logs->groupBy('tooth_number')->map(fn ($group) => $group->last()->id);
        $logsByVisit = $logs->groupBy('visit_id');

        foreach (OdontogramTooth::query()->where('patient_id', $patient->id)->get() as $tooth) {
            $items = ToothConditions::fromRecord($tooth);

            if ($items !== []) {
                $this->current[(string) $tooth->tooth_number] = $items;
            }
        }

        $state = [];

        foreach ($this->visits as $index => $visit) {
            $before = $state;
            $touched = [];

            foreach ($snapshots->get($visit->id, collect()) as $snapshot) {
                $number = (string) $snapshot->tooth_number;
                $state[$number] = ToothConditions::fromRecord($this->record($number, null, $snapshot->conditions, $snapshot->status));
                $touched[$number] = false;
            }

            foreach ($logsByVisit->get($visit->id, collect())->groupBy('tooth_number') as $number => $toothLogs) {
                $number = (string) $number;

                if (array_key_exists($number, $touched)) {
                    continue;
                }

                $last = $toothLogs->last();
                $laterSnapshot = collect($snapshotVisitIdx[$number] ?? [])->contains(fn ($idx) => $idx > $index);

                if ($last->id === ($lastLogId[$number] ?? null) && ! $laterSnapshot) {
                    $state[$number] = $this->current[$number] ?? [];
                    $touched[$number] = false;

                    continue;
                }

                $code = $last->new_status instanceof ToothStatus ? $last->new_status : ToothStatus::tryFrom((string) $last->new_status);
                $state[$number] = $code && $code !== ToothStatus::Healthy
                    ? ToothConditions::clean($number, [['code' => $code->value]], strict: false)
                    : [];
                $touched[$number] = true;
            }

            $state = array_filter($state, fn ($items) => $items !== []);
            ksort($state);
            $this->states[$visit->id] = $state;

            foreach ($touched as $number => $approximate) {
                $old = $before[$number] ?? [];
                $new = $state[$number] ?? [];

                if (self::signature($old) !== self::signature($new)) {
                    $this->changes[$visit->id][$number] = ['before' => $old, 'after' => $new, 'approximate' => $approximate];
                }
            }

            if (isset($this->changes[$visit->id])) {
                ksort($this->changes[$visit->id]);
            }
        }

        ksort($this->current);

        return $this;
    }

    /**
     * @return Collection<int, Visit>
     */
    public function visits(): Collection
    {
        return $this->visits;
    }

    /**
     * Kunjungan yang mengubah odontogram, terbaru dulu.
     *
     * @param  list<int>|null  $visibleIds
     * @return Collection<int, Visit>
     */
    public function changedVisits(?array $visibleIds = null): Collection
    {
        return $this->visits
            ->filter(fn (Visit $visit) => isset($this->changes[$visit->id]))
            ->when($visibleIds !== null, fn ($items) => $items->filter(fn (Visit $visit) => in_array($visit->id, $visibleIds, true)))
            ->reverse()
            ->values();
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function stateAfter(int $visitId): array
    {
        return $this->states[$visitId] ?? [];
    }

    /**
     * @return array<string, array{before: list<array<string, mixed>>, after: list<array<string, mixed>>, approximate: bool}>
     */
    public function changesIn(int $visitId): array
    {
        return $this->changes[$visitId] ?? [];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function current(): array
    {
        return $this->current;
    }

    public function hasVisit(int $visitId): bool
    {
        return array_key_exists($visitId, $this->states);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $from
     * @param  array<string, list<array<string, mixed>>>  $to
     * @return array<string, array{before: list<array<string, mixed>>, after: list<array<string, mixed>>}>
     */
    public static function diff(array $from, array $to): array
    {
        $numbers = array_unique([...array_keys($from), ...array_keys($to)]);
        sort($numbers);
        $diff = [];

        foreach ($numbers as $number) {
            $before = $from[$number] ?? [];
            $after = $to[$number] ?? [];

            if (self::signature($before) !== self::signature($after)) {
                $diff[(string) $number] = ['before' => $before, 'after' => $after];
            }
        }

        return $diff;
    }

    /**
     * @param  list<array<string, mixed>>|null  $conditions
     */
    public function record(string $number, ?array $items = null, ?array $conditions = null, ToothStatus|string|null $status = null): OdontogramTooth
    {
        $items ??= $conditions;
        $tooth = new OdontogramTooth;
        $tooth->tooth_number = $number;

        if ($items === null) {
            $tooth->status = $status instanceof ToothStatus ? $status : (ToothStatus::tryFrom((string) $status) ?? ToothStatus::Healthy);
            $tooth->conditions = null;

            return $tooth;
        }

        $tooth->status = ToothConditions::primary(ToothConditions::clean($number, $items, strict: false));
        $tooth->conditions = $items;

        return $tooth;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected static function signature(array $items): string
    {
        $normalized = collect($items)
            ->map(fn ($item) => [
                'code' => $item['code'] ?? '',
                'surfaces' => $item['surfaces'] ?? [],
                'detail' => $item['detail'] ?? [],
            ])
            ->sortBy('code')
            ->values()
            ->all();

        return json_encode($normalized) ?: '';
    }
}
