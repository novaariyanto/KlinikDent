<?php

namespace App\Support\Clinical;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MedicalRecordReadAudit
{
    public function record(User $user, Model $subject, string $description): void
    {
        $recent = ActivityLog::query()
            ->where('user_id', $user->id)
            ->where('event', 'viewed')
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->where('created_at', '>=', now()->subMinutes(15))
            ->exists();

        if ($recent) {
            return;
        }

        activity_log('viewed', $subject, [], $description, 'medical_records', $user);
    }
}
