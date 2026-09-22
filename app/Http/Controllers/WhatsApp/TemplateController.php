<?php

namespace App\Http\Controllers\WhatsApp;

use App\Enums\WhatsAppCategory;
use App\Enums\WhatsAppMessageStatus;
use App\Http\Requests\WhatsApp\SendTestTemplateRequest;
use App\Http\Requests\WhatsApp\StoreRemoteTemplateRequest;
use App\Http\Requests\WhatsApp\UpdateTemplateRequest;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTenantTemplate;
use App\Support\WhatsApp\WhatsAppCatalog;
use App\Support\WhatsApp\WhatsAppException;
use App\Support\WhatsApp\WhatsAppParameterParser;
use App\Support\WhatsApp\WhatsAppRemoteTemplateService;
use App\Support\WhatsApp\WhatsAppTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateController extends WhatsAppController
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('whatsapp.templates.view'), 403);

        $this->provision();

        $templates = WhatsAppTenantTemplate::query()
            ->with('globalTemplate')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($template) => $template->globalTemplate?->category?->value ?? $template->event_key->category()->value);

        return view('whatsapp.templates.index', [
            'grouped' => $templates,
            'categories' => WhatsAppCategory::cases(),
            'canManage' => (bool) auth()->user()?->can('whatsapp.templates.manage'),
        ]);
    }

    public function edit(WhatsAppTenantTemplate $whatsappTemplate, WhatsAppRemoteTemplateService $remote): View
    {
        abort_unless(auth()->user()?->can('whatsapp.templates.view'), 403);
        abort_unless((int) $whatsappTemplate->tenant_id === $this->tenantId(), 403);

        $this->provision();
        $whatsappTemplate->load('globalTemplate');

        $remoteTemplates = [];
        $remoteError = null;

        try {
            $remoteTemplates = $remote->listApproved($this->tenantId());
        } catch (WhatsAppException $exception) {
            $remoteError = $exception->getMessage();
        }

        $setting = WhatsAppSetting::forTenant($this->tenantId());

        return view('whatsapp.templates.edit', [
            'template' => $whatsappTemplate,
            'languages' => WhatsAppCatalog::languages(),
            'sources' => WhatsAppCatalog::sourcesFor($whatsappTemplate->event_key),
            'mapping' => $whatsappTemplate->resolvedParameterMap(),
            'remoteTemplates' => $remoteTemplates,
            'remoteError' => $remoteError,
            'settingReady' => $setting->isReady(),
            'testPhone' => old('phone_number', $setting->phone_number),
            'canManage' => (bool) auth()->user()?->can('whatsapp.templates.manage'),
        ]);
    }

    public function update(UpdateTemplateRequest $request, WhatsAppTenantTemplate $whatsappTemplate): RedirectResponse
    {
        abort_unless((int) $whatsappTemplate->tenant_id === $this->tenantId(), 403);

        $data = $request->validated();
        $preview = (string) ($data['preview_body'] ?? $whatsappTemplate->preview_body);
        $mapping = WhatsAppParameterParser::mergeMapping($preview, $data['parameters'] ?? []);
        $enabled = (bool) ($data['is_enabled'] ?? false);

        if (! $whatsappTemplate->isRemoteApproved()) {
            $enabled = false;
        }

        $whatsappTemplate->update([
            'whatsapp_template_name' => $data['whatsapp_template_name'],
            'language_code' => $data['language_code'],
            'preview_body' => $preview,
            'parameter_map' => $mapping,
            'is_enabled' => $enabled,
        ]);

        return redirect()
            ->route('whatsapp.templates.edit', $whatsappTemplate)
            ->with('success', 'Template WhatsApp disimpan.');
    }

    public function remoteIndex(WhatsAppRemoteTemplateService $remote): JsonResponse
    {
        abort_unless(auth()->user()?->can('whatsapp.templates.view'), 403);

        try {
            return response()->json([
                'success' => true,
                'data' => $remote->listApproved($this->tenantId()),
            ]);
        } catch (WhatsAppException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], $this->statusFromException($exception));
        }
    }

    public function remoteShow(Request $request, string $name, WhatsAppRemoteTemplateService $remote): JsonResponse
    {
        abort_unless(auth()->user()?->can('whatsapp.templates.view'), 403);

        try {
            return response()->json([
                'success' => true,
                'data' => $remote->detail(
                    $this->tenantId(),
                    $name,
                    $request->string('language')->toString() ?: null,
                ),
            ]);
        } catch (WhatsAppException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], $this->statusFromException($exception));
        }
    }

    public function sync(Request $request, WhatsAppTenantTemplate $whatsappTemplate, WhatsAppRemoteTemplateService $remote): RedirectResponse
    {
        abort_unless(auth()->user()?->can('whatsapp.templates.manage'), 403);
        abort_unless((int) $whatsappTemplate->tenant_id === $this->tenantId(), 403);

        $name = (string) $request->input('name', $whatsappTemplate->whatsapp_template_name);
        abort_unless($name !== '', 422);

        try {
            $detail = $remote->detail($this->tenantId(), $name, $whatsappTemplate->language_code);
            $remote->applyToTenantTemplate($whatsappTemplate, [
                'name_template' => $detail['name_template'],
                'display_name' => $detail['display_name'],
                'language' => $detail['language'],
                'status' => $detail['status'],
                'body' => $detail['preview'],
                'placeholders' => $detail['placeholders'],
                'header_info' => $detail['header_info'],
                'id' => $detail['id'],
                'category' => $detail['category'],
            ]);
        } catch (WhatsAppException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Template WhatsCRM disinkronkan.');
    }

    public function refreshStatus(WhatsAppTenantTemplate $whatsappTemplate, WhatsAppRemoteTemplateService $remote): RedirectResponse
    {
        abort_unless(auth()->user()?->can('whatsapp.templates.manage'), 403);
        abort_unless((int) $whatsappTemplate->tenant_id === $this->tenantId(), 403);
        abort_unless(filled($whatsappTemplate->whatsapp_template_name), 422);

        try {
            $detail = $remote->detail($this->tenantId(), $whatsappTemplate->whatsapp_template_name, $whatsappTemplate->language_code, 'all');
            $remote->applyToTenantTemplate($whatsappTemplate, [
                'name_template' => $detail['name_template'],
                'display_name' => $detail['display_name'],
                'language' => $detail['language'],
                'status' => $detail['status'],
                'body' => $detail['preview'],
                'placeholders' => $detail['placeholders'],
                'header_info' => $detail['header_info'],
                'id' => $detail['id'],
                'category' => $detail['category'],
            ]);
        } catch (WhatsAppException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $status = $whatsappTemplate->fresh()?->remote_status ?: 'unknown';

        return back()->with('success', 'Status Meta: '.$status);
    }

    public function create(WhatsAppTenantTemplate $whatsappTemplate): View
    {
        abort_unless(auth()->user()?->can('whatsapp.templates.manage'), 403);
        abort_unless((int) $whatsappTemplate->tenant_id === $this->tenantId(), 403);

        $whatsappTemplate->load('globalTemplate');

        return view('whatsapp.templates.create', [
            'template' => $whatsappTemplate,
            'languages' => WhatsAppCatalog::languages(),
            'category' => $whatsappTemplate->event_key->category()->whatsCrmCategory(),
        ]);
    }

    public function store(
        StoreRemoteTemplateRequest $request,
        WhatsAppTenantTemplate $whatsappTemplate,
        WhatsAppRemoteTemplateService $remote,
    ): RedirectResponse {
        abort_unless((int) $whatsappTemplate->tenant_id === $this->tenantId(), 403);

        $data = $request->validated();
        $payload = [
            'name' => $data['name'],
            'category' => $whatsappTemplate->event_key->category()->whatsCrmCategory(),
            'language' => $data['language'],
            'header_type' => $data['header_type'],
            'body_text' => WhatsAppParameterParser::toRemoteBody($data['body_text']),
        ];

        if ($data['header_type'] === 'text') {
            $payload['header_text'] = $data['header_text'];
        }

        if (filled($data['footer_text'] ?? null)) {
            $payload['footer_text'] = $data['footer_text'];
        }

        try {
            $created = $remote->create($this->tenantId(), $payload);
            $remote->applyToTenantTemplate($whatsappTemplate, [
                'name_template' => $created['name_template'],
                'display_name' => $created['display_name'],
                'language' => $created['language'],
                'status' => $created['status'] ?: 'pending',
                'body' => $created['preview'],
                'placeholders' => $created['placeholders'],
                'header_info' => $created['header_info'],
                'id' => $created['id'],
                'category' => $created['category'],
            ]);
        } catch (WhatsAppException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('whatsapp.templates.edit', $whatsappTemplate)
            ->with('success', 'Template diajukan ke Meta. Status awal pending, belum bisa dikirim.');
    }

    public function testSend(
        SendTestTemplateRequest $request,
        WhatsAppTenantTemplate $whatsappTemplate,
        WhatsAppTemplateService $templates,
    ): RedirectResponse {
        abort_unless((int) $whatsappTemplate->tenant_id === $this->tenantId(), 403);

        $data = $request->validated();

        try {
            $log = $templates->sendTest(
                $whatsappTemplate,
                (string) $data['phone_number'],
                trim((string) ($data['to_name'] ?? '')),
                $data['body_params'] ?? [],
            );
        } catch (WhatsAppException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        if ($log->status === WhatsAppMessageStatus::Failed) {
            return back()->withInput()->with('error', $log->error_message ?: 'Tes kirim gagal.');
        }

        return back()->with('success', 'Tes template terkirim ke '.$log->phone_number.'.');
    }

    protected function statusFromException(WhatsAppException $exception): int
    {
        $code = (int) $exception->errorCode;

        return in_array($code, [401, 403, 404, 422], true) ? $code : 422;
    }
}
