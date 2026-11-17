<?php

namespace App\Http\Controllers\Saas;

use App\Enums\AddonStatus;
use App\Http\Controllers\Controller;
use App\Models\SaasAddon;
use App\Models\SaasAddonSubscription;
use App\Models\Tenant;
use App\Support\Saas\AddonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AddonSubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->can('addon.manage'), 403);

        $query = SaasAddonSubscription::query()
            ->with(['tenant', 'addon', 'invoices' => fn ($q) => $q->latest('id')->limit(3)])
            ->latest('id');

        if ($request->filled('addon_id')) {
            $query->where('addon_id', $request->integer('addon_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->integer('tenant_id'));
        }

        return view('saas.addon-subscriptions.index', [
            'subscriptions' => $query->paginate(20)->withQueryString(),
            'addons' => SaasAddon::query()->orderBy('name')->get(),
            'tenants' => Tenant::query()->orderBy('name')->limit(200)->get(['id', 'name', 'subdomain']),
            'statuses' => AddonStatus::cases(),
            'filters' => $request->only(['addon_id', 'status', 'tenant_id']),
        ]);
    }

    public function activate(SaasAddonSubscription $saasAddonSubscription, AddonService $addons): RedirectResponse
    {
        abort_unless(auth()->user()?->can('addon.manage'), 403);

        $addons->activateUnlimited($saasAddonSubscription, auth()->user());

        activity_log('updated', $saasAddonSubscription->fresh(), ['status' => AddonStatus::Active->value], 'Add-on diaktifkan oleh Superadmin.', 'saas');

        return back()->with('success', 'Add-on diaktifkan.');
    }

    public function suspend(SaasAddonSubscription $saasAddonSubscription, AddonService $addons): RedirectResponse
    {
        abort_unless(auth()->user()?->can('addon.manage'), 403);

        $addons->suspend($saasAddonSubscription);

        activity_log('updated', $saasAddonSubscription->fresh(), ['status' => AddonStatus::Suspended->value], 'Add-on ditangguhkan.', 'saas');

        return back()->with('success', 'Add-on ditangguhkan.');
    }

    public function revoke(SaasAddonSubscription $saasAddonSubscription, AddonService $addons): RedirectResponse
    {
        abort_unless(auth()->user()?->can('addon.manage'), 403);

        $addons->revoke($saasAddonSubscription);

        activity_log('updated', $saasAddonSubscription->fresh(), ['status' => AddonStatus::Inactive->value], 'Add-on dicabut.', 'saas');

        return back()->with('success', 'Add-on dicabut.');
    }

    public function extend(Request $request, SaasAddonSubscription $saasAddonSubscription, AddonService $addons): RedirectResponse
    {
        abort_unless(auth()->user()?->can('addon.manage'), 403);

        $data = $request->validate([
            'ends_at' => ['nullable', 'date'],
        ]);

        $endsAt = filled($data['ends_at'] ?? null) ? $data['ends_at'] : null;
        $addons->extend($saasAddonSubscription, $endsAt);

        activity_log('updated', $saasAddonSubscription->fresh(), ['ends_at' => $endsAt], 'Masa berlaku Add-on diperbarui.', 'saas');

        return back()->with('success', 'Masa berlaku diperbarui.');
    }

    public function ensure(Request $request, AddonService $addons): RedirectResponse
    {
        abort_unless(auth()->user()?->can('addon.manage'), 403);

        $data = $request->validate([
            'tenant_id' => ['required', 'exists:tenants,id'],
            'addon_id' => ['required', 'exists:saas_addons,id'],
            'activate' => ['sometimes', 'boolean'],
        ]);

        $tenant = Tenant::query()->findOrFail($data['tenant_id']);
        $addon = SaasAddon::query()->findOrFail($data['addon_id']);
        $subscription = $addons->ensureSubscription($tenant, $addon);

        if ($request->boolean('activate')) {
            $addons->activateUnlimited($subscription, auth()->user());
        }

        return redirect()
            ->route('saas.addon-subscriptions.index', ['tenant_id' => $tenant->id, 'addon_id' => $addon->id])
            ->with('success', 'Aktivasi tenant disiapkan.');
    }
}
