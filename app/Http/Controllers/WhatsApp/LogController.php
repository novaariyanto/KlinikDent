<?php

namespace App\Http\Controllers\WhatsApp;

use App\Models\WhatsAppMessageLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends WhatsAppController
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->can('whatsapp.logs.view'), 403);

        $this->provision();

        $logs = WhatsAppMessageLog::query()
            ->with(['patient', 'branch'])
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->string('event')->toString(), fn ($query, $event) => $query->where('event_key', $event))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('whatsapp.logs.index', [
            'logs' => $logs,
        ]);
    }

    public function show(WhatsAppMessageLog $whatsappLog): View
    {
        abort_unless(auth()->user()?->can('whatsapp.logs.view'), 403);
        abort_unless((int) $whatsappLog->tenant_id === $this->tenantId(), 403);

        $whatsappLog->load(['patient', 'branch']);

        return view('whatsapp.logs.show', [
            'log' => $whatsappLog,
        ]);
    }
}
