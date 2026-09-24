<?php

namespace App\Http\Controllers\Owner;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Jobs\GeneratePracticeArchive;
use App\Models\PracticeArchive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PracticeArchiveController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeOwner($request);

        $archives = PracticeArchive::query()->latest('id')->limit(10)->get();

        return view('owner.archive', compact('archives'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeOwner($request);

        $archive = PracticeArchive::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        GeneratePracticeArchive::dispatchSync($archive->id);

        return back()->with('success', 'Arsip praktik siap diunduh. Berkas berlaku 24 jam.');
    }

    public function download(Request $request, PracticeArchive $archive): StreamedResponse
    {
        $this->authorizeOwner($request);
        abort_unless((int) $archive->tenant_id === (int) $request->user()->tenant_id, 404);
        abort_unless($archive->fresh()?->isReady(), 404);

        return Storage::disk('local')->download($archive->path, 'arsip-praktik-'.$archive->id.'.zip');
    }

    protected function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()?->hasRole(RoleName::Owner->value), 403);
    }
}
