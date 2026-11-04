<?php

namespace App\Support\Clinical;

use App\Enums\AssessmentKind;
use App\Enums\AssessmentStatus;
use App\Enums\CareEpisodeStatus;
use App\Models\CareEpisode;
use App\Models\MedicalRecord;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CareEpisodeService
{
    public function attach(Visit $visit): CareEpisode
    {
        if ($visit->episode_id) {
            $existing = CareEpisode::withoutGlobalScopes()->find($visit->episode_id);

            if ($existing) {
                return $existing;
            }
        }

        $episode = CareEpisode::withoutGlobalScopes()
            ->where('tenant_id', $visit->tenant_id)
            ->where('branch_id', $visit->branch_id)
            ->where('patient_id', $visit->patient_id)
            ->where('status', CareEpisodeStatus::Open->value)
            ->lockForUpdate()
            ->first();

        if (! $episode) {
            $episode = CareEpisode::withoutGlobalScopes()->create([
                'tenant_id' => $visit->tenant_id,
                'branch_id' => $visit->branch_id,
                'patient_id' => $visit->patient_id,
                'status' => CareEpisodeStatus::Open,
                'opened_at' => $visit->created_at ?? now(),
            ]);
        }

        if ((int) $visit->episode_id !== (int) $episode->id) {
            $visit->episode_id = $episode->id;
            $visit->save();
        }

        return $episode;
    }

    public function assign(Visit $visit, MedicalRecord $record): MedicalRecord
    {
        return DB::transaction(function () use ($visit, $record) {
            $episode = $this->attach($visit);
            CareEpisode::withoutGlobalScopes()->whereKey($episode->id)->lockForUpdate()->first();

            if ($record->assessment_kind instanceof AssessmentKind) {
                return $record;
            }

            $hasInitial = $this->initialQuery($episode->id)
                ->where('medical_records.id', '!=', $record->id)
                ->exists();

            $record->assessment_kind = $hasInitial ? AssessmentKind::FollowUp : AssessmentKind::Initial;
            $record->assessment_status ??= AssessmentStatus::Draft;
            $record->save();

            return $record;
        });
    }

    public function assertFollowUpAllowed(Visit $visit): void
    {
        $record = $visit->medicalRecord;

        if ($record?->assessment_kind !== AssessmentKind::FollowUp) {
            return;
        }

        if (! $visit->episode_id || ! $this->initialQuery((int) $visit->episode_id)->exists()) {
            throw ValidationException::withMessages([
                'assessment' => 'Asesmen ulang membutuhkan asesmen awal pada episode pelayanan yang sama.',
            ]);
        }
    }

    public function close(CareEpisode $episode, User $user): void
    {
        if (! $episode->isOpen()) {
            return;
        }

        $episode->update([
            'status' => CareEpisodeStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $user->id,
        ]);
    }

    /**
     * @return array{
     *     episode: ?CareEpisode,
     *     kind: ?AssessmentKind,
     *     status: AssessmentStatus,
     *     baseline: ?Visit,
     *     history: Collection<int, Visit>,
     *     previousProcedures: Collection<int, mixed>,
     *     previousPrescriptions: Collection<int, mixed>
     * }
     */
    public function context(Visit $visit): array
    {
        $record = $visit->medicalRecord;
        $episode = $visit->episode_id
            ? CareEpisode::withoutGlobalScopes()->find($visit->episode_id)
            : null;

        $history = $episode
            ? Visit::withoutGlobalScopes()
                ->where('episode_id', $episode->id)
                ->with([
                    'medicalRecord',
                    'doctor',
                    'diagnoses',
                    'procedureRecords.procedure',
                    'prescriptions.items.medicine',
                    'prescriptions.items.ingredients.medicine',
                ])
                ->orderBy('visit_date')
                ->orderBy('id')
                ->get()
            : collect();

        $baseline = $history->first(fn (Visit $item) => $item->medicalRecord?->assessment_kind === AssessmentKind::Initial);

        $earlier = $history->filter(fn (Visit $item) => (int) $item->id !== (int) $visit->id
            && ($baseline === null || $item->visit_date < $visit->visit_date || ((string) $item->visit_date === (string) $visit->visit_date && $item->id < $visit->id)));

        return [
            'episode' => $episode,
            'kind' => $record?->assessment_kind,
            'status' => $record?->assessment_status ?? AssessmentStatus::Draft,
            'baseline' => $baseline,
            'history' => $history,
            'previousProcedures' => $earlier->flatMap(fn (Visit $item) => $item->procedureRecords)->values(),
            'previousPrescriptions' => $earlier->flatMap(fn (Visit $item) => $item->prescriptions)->values(),
        ];
    }

    protected function initialQuery(int $episodeId)
    {
        return MedicalRecord::withoutGlobalScopes()
            ->where('assessment_kind', AssessmentKind::Initial->value)
            ->whereIn('visit_id', Visit::withoutGlobalScopes()->where('episode_id', $episodeId)->select('id'));
    }
}
