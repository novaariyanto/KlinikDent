<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ClinicalDocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\ClinicalDocument;
use Illuminate\View\View;

class DocumentCheckController extends Controller
{
    public function show(string $code): View
    {
        $document = ClinicalDocument::withoutGlobalScopes()
            ->where('verification_code', $code)
            ->first();

        abort_unless($document, 404);

        return view('portal.check', [
            'document' => $document,
            'valid' => $document->status === ClinicalDocumentStatus::Valid,
        ]);
    }
}
