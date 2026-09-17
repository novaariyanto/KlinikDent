<?php

namespace App\Support\Clinical;

use App\Models\User;

final class CareTabs
{
    /**
     * @return array<string, array{label: string, permission: string}>
     */
    public static function all(): array
    {
        return [
            'record' => ['label' => 'Rekam Medis', 'permission' => 'medical_record.view'],
            'exam' => ['label' => 'Pemeriksaan Awal', 'permission' => 'examination.view'],
            'vitals' => ['label' => 'Tanda Vital', 'permission' => 'vital_sign.view'],
            'anamnesis' => ['label' => 'Anamnesis', 'permission' => 'anamnesis.view'],
            'notes' => ['label' => 'Catatan', 'permission' => 'care_note.view'],
            'odontogram' => ['label' => 'Odontogram', 'permission' => 'odontogram.view'],
            'diagnosis' => ['label' => 'Diagnosis', 'permission' => 'diagnosis.view'],
            'procedures' => ['label' => 'Tindakan', 'permission' => 'procedure.view'],
            'prescriptions' => ['label' => 'Resep', 'permission' => 'prescription.view'],
            'referrals' => ['label' => 'Rujukan', 'permission' => 'referral.view'],
            'history' => ['label' => 'Riwayat', 'permission' => 'patient_history.view'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function for(User $user): array
    {
        $tabs = [];

        foreach (self::all() as $key => $tab) {
            if ($user->can($tab['permission'])) {
                $tabs[$key] = $tab['label'];
            }
        }

        return $tabs;
    }

    /**
     * @return array<string, array{label: string, icon: string}>
     */
    public static function flow(User $user): array
    {
        $sections = [];

        if ($user->can('medical_record.view') || $user->can('anamnesis.view')) {
            $sections['keluhan'] = ['label' => 'Keluhan', 'icon' => 'bx bx-user-voice'];
        }

        if ($user->can('vital_sign.view') || $user->can('anamnesis.view') || $user->can('medical_record.view')) {
            $sections['screening'] = ['label' => 'Screening', 'icon' => 'bx bx-heart'];
        }

        if ($user->can('examination.view')) {
            $sections['pemeriksaan'] = ['label' => 'Pemeriksaan', 'icon' => 'bx bx-search-alt'];
        }

        if ($user->can('odontogram.view')) {
            $sections['odontogram'] = ['label' => 'Odontogram', 'icon' => 'bx bx-grid-alt'];
        }

        if ($user->can('diagnosis.view')) {
            $sections['diagnosis'] = ['label' => 'Diagnosis', 'icon' => 'bx bx-check-shield'];
        }

        if ($user->can('procedure.view')) {
            $sections['tindakan'] = ['label' => 'Tindakan', 'icon' => 'bx bx-first-aid'];
        }

        if ($user->can('prescription.view')) {
            $sections['resep'] = ['label' => 'Resep', 'icon' => 'bx bx-capsule'];
        }

        if ($user->can('referral.view')) {
            $sections['rujukan'] = ['label' => 'Rujukan', 'icon' => 'bx bx-share-alt'];
        }

        if ($user->can('medical_record.view') || $user->can('care_note.view')) {
            $sections['instruksi'] = ['label' => 'Instruksi', 'icon' => 'bx bx-edit-alt'];
        }

        return $sections;
    }

    /**
     * @return array<string, array{label: string, icon: string, hint?: string}>
     */
    public static function workspace(User $user): array
    {
        return self::flow($user);
    }

    public static function resolveSection(User $user, string $requested): string
    {
        $aliases = [
            'keluhan' => 'keluhan',
            'soap' => 'keluhan',
            'subjective' => 'keluhan',
            'record' => 'keluhan',
            'anamnesis' => 'keluhan',
            'history' => 'keluhan',
            'screening' => 'screening',
            'objective' => 'screening',
            'vitals' => 'screening',
            'systemic' => 'screening',
            'pemeriksaan' => 'pemeriksaan',
            'exam' => 'pemeriksaan',
            'odontogram' => 'odontogram',
            'diagnosis' => 'diagnosis',
            'assessment' => 'diagnosis',
            'tindakan' => 'tindakan',
            'plan' => 'tindakan',
            'procedures' => 'tindakan',
            'resep' => 'resep',
            'prescriptions' => 'resep',
            'rujukan' => 'rujukan',
            'referrals' => 'rujukan',
            'instruksi' => 'instruksi',
            'notes' => 'instruksi',
        ];

        $sections = self::flow($user);
        $key = $aliases[$requested] ?? $requested;

        if ($key !== '' && isset($sections[$key])) {
            return $key;
        }

        return (string) array_key_first($sections);
    }

    public static function resolveWorkspaceTab(User $user, string $requested): string
    {
        return self::resolveSection($user, $requested);
    }

    public static function canAccessCare(User $user): bool
    {
        foreach ([
            'medical_record.view',
            'examination.view',
            'odontogram.view',
            'diagnosis.view',
            'anamnesis.view',
            'vital_sign.view',
            'care_note.view',
            'procedure.view',
            'prescription.view',
            'referral.view',
            'patient_history.view',
        ] as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
