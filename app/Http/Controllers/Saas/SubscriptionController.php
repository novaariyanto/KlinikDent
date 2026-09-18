<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\StoreSaasSubscriptionRequest;
use App\Models\SaasPackage;
use App\Models\SaasSubscription;
use App\Models\Tenant;
use App\Support\Saas\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', SaasSubscription::class);

        $subscriptions = SaasSubscription::query()
            ->with(['tenant', 'package'])
            ->latest('id')
            ->paginate(20);

        return view('saas.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'tenants' => Tenant::query()->orderBy('name')->get(),
            'packages' => SaasPackage::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreSaasSubscriptionRequest $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $tenant = Tenant::query()->findOrFail($request->validated('tenant_id'));
        $package = SaasPackage::query()->findOrFail($request->validated('package_id'));
        $subscription = $subscriptions->start($tenant, $package, (bool) $request->validated('trial'));

        activity_log('created', $subscription, $request->validated(), 'Langganan '.$tenant->name.' dimulai.', 'saas');

        return redirect()->route('saas.subscriptions.index')->with('success', 'Langganan disimpan.');
    }
}
