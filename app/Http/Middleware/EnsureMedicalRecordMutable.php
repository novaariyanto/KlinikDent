<?php

namespace App\Http\Middleware;

use App\Models\Visit;
use App\Support\Clinical\MedicalRecordLock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnsureMedicalRecordMutable
{
    public function __construct(protected MedicalRecordLock $lock) {}

    public function handle(Request $request, Closure $next): Response
    {
        $visit = $request->route('visit');

        if ($visit instanceof Visit) {
            $record = $visit->medicalRecord;

            if ($record && $this->lock->isLocked($record)) {
                $message = 'Batas koreksi 2×24 jam telah lewat. Perubahan hanya melalui adendum.';

                if ($request->expectsJson()) {
                    throw ValidationException::withMessages(['record' => $message]);
                }

                return back()->with('error', $message);
            }
        }

        return $next($request);
    }
}
