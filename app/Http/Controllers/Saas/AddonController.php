<?php

namespace App\Http\Controllers\Saas;

use App\Enums\AddonBillingInterval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\StoreSaasAddonRequest;
use App\Http\Requests\Saas\UpdateSaasAddonRequest;
use App\Models\SaasAddon;
use App\Support\Saas\AddonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AddonController extends Controller
{
    public function index(AddonService $addons): View
    {
        abort_unless(auth()->user()?->can('addon.manage'), 403);

        $addons->ensureWhatsAppCatalog();

        $catalog = SaasAddon::query()
            ->withCount('subscriptions')
            ->orderBy('name')
            ->get();

        return view('saas.addons.index', [
            'addons' => $catalog,
            'intervalOptions' => self::intervalOptions(),
        ]);
    }

    public function store(StoreSaasAddonRequest $request): RedirectResponse
    {
        $addon = SaasAddon::query()->create($request->validated());

        activity_log('created', $addon, $request->validated(), 'Add-on '.$addon->name.' dibuat.', 'saas');

        return redirect()->route('saas.addons.index')->with('success', 'Add-on disimpan.');
    }

    public function update(UpdateSaasAddonRequest $request, SaasAddon $saasAddon): RedirectResponse
    {
        $saasAddon->update($request->validated());

        activity_log('updated', $saasAddon, $request->validated(), 'Add-on '.$saasAddon->name.' diubah.', 'saas');

        return redirect()->route('saas.addons.index')->with('success', 'Add-on diperbarui.');
    }

    public function toggleStatus(SaasAddon $saasAddon): RedirectResponse
    {
        abort_unless(auth()->user()?->can('addon.manage'), 403);

        $saasAddon->update(['is_active' => ! $saasAddon->is_active]);
        $label = $saasAddon->is_active ? 'diaktifkan' : 'dinonaktifkan';

        activity_log('updated', $saasAddon, ['is_active' => $saasAddon->is_active], 'Add-on '.$saasAddon->name.' '.$label.'.', 'saas');

        return redirect()->route('saas.addons.index')->with('success', 'Add-on '.$label.'.');
    }

    /**
     * @return array<string, string>
     */
    public static function intervalOptions(): array
    {
        return collect(AddonBillingInterval::cases())
            ->mapWithKeys(fn (AddonBillingInterval $interval) => [$interval->value => $interval->label()])
            ->all();
    }

    public static function normalizeBenefits(mixed $value): ?array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($item) => trim((string) $item), $value)));
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return collect(preg_split('/\r\n|\r|\n/', $value) ?: [])
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    public static function slugCode(string $code): string
    {
        return Str::slug($code);
    }
}
