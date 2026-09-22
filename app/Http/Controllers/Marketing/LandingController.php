<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Marketing\MarketingCatalog;
use App\Support\Saas\ClinicProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(ClinicProvisioningService $provisioning): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('marketing.landing', $this->marketingData($provisioning));
    }

    public function dokterBaru(ClinicProvisioningService $provisioning): View
    {
        return $this->hub('dokter-gigi-baru', 'marketing.pages.dokter-gigi-baru', $provisioning);
    }

    public function klinik(ClinicProvisioningService $provisioning): View
    {
        return $this->hub('klinik-gigi', 'marketing.pages.klinik-gigi', $provisioning);
    }

    public function multiCabang(ClinicProvisioningService $provisioning): View
    {
        return $this->hub('multi-cabang', 'marketing.pages.multi-cabang', $provisioning);
    }

    public function odontogram(ClinicProvisioningService $provisioning): View
    {
        return $this->hub('odontogram-digital', 'marketing.pages.odontogram-digital', $provisioning);
    }

    public function rekamMedis(ClinicProvisioningService $provisioning): View
    {
        return $this->hub('rekam-medis-gigi', 'marketing.pages.rekam-medis-gigi', $provisioning);
    }

    public function privacy(): View
    {
        return view('marketing.privacy');
    }

    public function terms(): View
    {
        return view('marketing.terms');
    }

    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => route('marketing.dokter-baru'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('marketing.klinik'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('marketing.multi-cabang'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => route('marketing.odontogram'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('marketing.rekam-medis'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('privacy'), 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['loc' => route('terms'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ];

        return response()
            ->view('marketing.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /dashboard',
            'Disallow: /onboarding',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ]);

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    protected function hub(string $slug, string $view, ClinicProvisioningService $provisioning): View
    {
        $page = MarketingCatalog::page($slug);

        abort_unless($page, 404);

        return view($view, [
            ...$this->marketingData($provisioning),
            'page' => $page,
            'canonical' => url('/'.$slug),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function marketingData(ClinicProvisioningService $provisioning): array
    {
        try {
            $appName = (string) Setting::getValue('app_name', config('app.name'));
        } catch (\Throwable) {
            $appName = (string) config('app.name');
        }

        try {
            $packages = $provisioning->publicPackages();
        } catch (\Throwable) {
            $packages = collect();
        }

        return [
            'packages' => $packages,
            'hubs' => MarketingCatalog::hubs(),
            'featureGroups' => MarketingCatalog::featureGroups(),
            'faqs' => MarketingCatalog::faqs($appName),
            'softwareFeatures' => MarketingCatalog::softwareFeatures(),
        ];
    }
}
