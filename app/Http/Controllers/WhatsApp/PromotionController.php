<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Requests\WhatsApp\StorePromotionRequest;
use App\Http\Requests\WhatsApp\UpdatePromotionRequest;
use App\Models\Branch;
use App\Models\WhatsAppPromotion;
use App\Support\WhatsApp\WhatsAppSchedulerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PromotionController extends WhatsAppController
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('whatsapp.promotions.view'), 403);

        $this->provision();

        return view('whatsapp.promotions.index', [
            'promotions' => WhatsAppPromotion::query()->with('branch')->latest('id')->paginate(20),
            'canManage' => (bool) auth()->user()?->can('whatsapp.promotions.manage'),
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->can('whatsapp.promotions.manage'), 403);

        $this->provision();

        return view('whatsapp.promotions.form', [
            'promotion' => new WhatsAppPromotion,
            'branches' => Branch::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StorePromotionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $this->tenantId();
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);
        $data['branch_id'] = $data['branch_id'] ?: null;

        WhatsAppPromotion::query()->create($data);

        return redirect()->route('whatsapp.promotions.index')->with('success', 'Promosi disimpan.');
    }

    public function edit(WhatsAppPromotion $whatsappPromotion): View
    {
        abort_unless(auth()->user()?->can('whatsapp.promotions.manage'), 403);
        abort_unless((int) $whatsappPromotion->tenant_id === $this->tenantId(), 403);

        return view('whatsapp.promotions.form', [
            'promotion' => $whatsappPromotion,
            'branches' => Branch::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdatePromotionRequest $request, WhatsAppPromotion $whatsappPromotion): RedirectResponse
    {
        abort_unless((int) $whatsappPromotion->tenant_id === $this->tenantId(), 403);

        $data = $request->validated();
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);
        $data['branch_id'] = $data['branch_id'] ?: null;

        $whatsappPromotion->update($data);

        return redirect()->route('whatsapp.promotions.index')->with('success', 'Promosi diperbarui.');
    }

    public function send(WhatsAppPromotion $whatsappPromotion, WhatsAppSchedulerService $scheduler): RedirectResponse
    {
        abort_unless(auth()->user()?->can('whatsapp.promotions.manage'), 403);
        abort_unless((int) $whatsappPromotion->tenant_id === $this->tenantId(), 403);

        $count = $scheduler->sendPromotion($whatsappPromotion);
        $whatsappPromotion->update(['dispatched_at' => now(), 'is_enabled' => true]);

        return back()->with('success', 'Promosi diantrikan ke '.$count.' pasien yang memenuhi syarat.');
    }
}
