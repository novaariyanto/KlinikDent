<?php

namespace App\Support\Clinical;

use App\Models\MedicalRecord;
use App\Models\MedicalRecordAmendment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MedicalRecordLock
{
    public const HOURS = 48;

    public function isFinal(MedicalRecord $record): bool
    {
        return $record->finalized_at !== null;
    }

    public function isLocked(MedicalRecord $record): bool
    {
        return $this->isFinal($record) && $record->finalized_at->copy()->addHours(self::HOURS)->isPast();
    }

    public function assertMutable(MedicalRecord $record): void
    {
        if ($this->isLocked($record)) {
            throw ValidationException::withMessages([
                'record' => 'Batas koreksi 2×24 jam telah lewat. Perubahan hanya melalui adendum.',
            ]);
        }
    }

    public function finalize(MedicalRecord $record, User $user): void
    {
        if ($this->isFinal($record)) {
            throw ValidationException::withMessages([
                'record' => 'Rekam medis sudah difinalisasi.',
            ]);
        }

        $record->update([
            'finalized_at' => now(),
            'finalized_by' => $user->id,
        ]);
    }

    public function amend(MedicalRecord $record, User $user, string $body): MedicalRecordAmendment
    {
        if (! $this->isLocked($record)) {
            throw ValidationException::withMessages([
                'body' => 'Adendum dipakai setelah batas koreksi 2×24 jam.',
            ]);
        }

        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'Teks adendum wajib diisi.',
            ]);
        }

        return $record->amendments()->create([
            'user_id' => $user->id,
            'body' => $body,
        ]);
    }
}
