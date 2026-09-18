<?php

namespace App\Http\Controllers\Saas;

use App\Enums\BillingInterval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\StoreSaasPackageRequest;
use App\Http\Requests\Saas\UpdateSaasPackageRequest;
use App\Models\SaasPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PackageController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', SaasPackage::class);

        $packages = SaasPackage::query()->orderBy('name')->get();

        return view('saas.packages.index', compact('packages'));
    }

    public function store(StoreSaasPackageRequest $request): RedirectResponse
    {
        $package = SaasPackage::query()->create($request->validated());

        activity_log('created', $package, $request->validated(), 'Paket '.$package->name.' dibuat.', 'saas');

        return redirect()->route('saas.packages.index')->with('success', 'Paket disimpan.');
    }

    public function update(UpdateSaasPackageRequest $request, SaasPackage $saasPackage): RedirectResponse
    {
        $saasPackage->update($request->validated());

        activity_log('updated', $saasPackage, $request->validated(), 'Paket '.$saasPackage->name.' diubah.', 'saas');

        return redirect()->route('saas.packages.index')->with('success', 'Paket diperbarui.');
    }

    public function destroy(SaasPackage $saasPackage): RedirectResponse
    {
        $this->authorize('delete', $saasPackage);

        if ($saasPackage->subscriptions()->exists()) {
            return back()->with('error', 'Paket tidak dapat dihapus karena sudah dipakai langganan.');
        }

        $name = $saasPackage->name;
        $saasPackage->delete();

        activity_log('deleted', $saasPackage, [], 'Paket '.$name.' dihapus.', 'saas');

        return redirect()->route('saas.packages.index')->with('success', 'Paket dihapus.');
    }

    /**
     * @return array<string, string>
     */
    public static function intervalOptions(): array
    {
        return collect(BillingInterval::cases())
            ->mapWithKeys(fn (BillingInterval $interval) => [$interval->value => $interval->label()])
            ->all();
    }
}
