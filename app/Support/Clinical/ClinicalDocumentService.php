<?php

namespace App\Support\Clinical;

use App\Enums\ClinicalDocumentStatus;
use App\Enums\ClinicalDocumentType;
use App\Models\ClinicalDocument;
use App\Models\Doctor;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClinicalDocumentService
{
    public function issue(
        Visit $visit,
        User $user,
        ClinicalDocumentType $type,
        string $number,
        array $payload,
        ?Model $reference = null,
    ): ClinicalDocument {
        $existing = ClinicalDocument::query()
            ->where('visit_id', $visit->id)
            ->where('type', $type)
            ->when($reference, function ($query) use ($reference) {
                $query->where('reference_type', $reference::class)
                    ->where('reference_id', $reference->getKey());
            }, function ($query) use ($number) {
                $query->where('number', $number);
            })
            ->where('status', ClinicalDocumentStatus::Valid)
            ->first();

        if ($existing) {
            return $existing;
        }

        $doctor = $visit->doctor_id
            ? Doctor::query()->where('user_id', $visit->doctor_id)->first()
            : null;

        return ClinicalDocument::query()->create([
            'tenant_id' => $visit->tenant_id,
            'visit_id' => $visit->id,
            'type' => $type,
            'number' => $number,
            'verification_code' => $this->code(),
            'patient_initials' => $this->initials($visit->patient?->name),
            'payload' => $payload,
            'status' => ClinicalDocumentStatus::Valid,
            'signed_at' => now(),
            'signature_path' => $doctor?->signature_path,
            'signature_provider' => 'visual',
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
        ]);
    }

    public function signatureSrc(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $full = Storage::disk('public')->path($path);
        $contents = is_file($full) ? file_get_contents($full) : false;

        if ($contents === false || $contents === '') {
            return null;
        }

        $mime = mime_content_type($full) ?: 'image/png';

        if (! str_starts_with((string) $mime, 'image/')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    protected function code(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (ClinicalDocument::query()->where('verification_code', $code)->exists());

        return $code;
    }

    protected function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $letters = '';

        foreach ($parts as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return mb_substr($letters, 0, 8);
    }
}
