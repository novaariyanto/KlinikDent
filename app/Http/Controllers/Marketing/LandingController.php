<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CmsPost;
use App\Models\Setting;
use App\Support\Marketing\MarketingCatalog;
use App\Support\Marketing\SeoMeta;
use App\Support\Marketing\SitemapBuilder;
use App\Support\Saas\ClinicProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
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

    public function tentang(ClinicProvisioningService $provisioning): View
    {
        return view('marketing.pages.tentang-klinikdent', [
            ...$this->marketingData($provisioning),
            'canonical' => url('/tentang-klinikdent'),
        ]);
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
        return response()
            ->view('marketing.sitemap', ['urls' => SitemapBuilder::urls()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $extra = SeoMeta::get('robots_extra_disallow', []);
        $disallow = array_merge([
            '/dashboard',
            '/onboarding',
            '/saas',
            '/cms',
            '/settings',
            '/api',
            '/logs',
            '/users',
            '/roles',
            '/menus',
            '/janji',
        ], is_array($extra) ? $extra : []);

        $lines = ['User-agent: *', 'Allow: /'];
        foreach (array_unique($disallow) as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.url('/sitemap.xml');
        $lines[] = '';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function llmsTxt(): Response
    {
        $appName = (string) SeoMeta::get('site_name', config('app.name'));
        $lines = [
            '# '.$appName,
            '',
            '> '.$appName.' adalah software manajemen klinik gigi dengan rekam medis elektronik, odontogram digital, pendaftaran, billing, dan multi-cabang.',
            '',
            '## Halaman utama',
            '- Beranda: '.url('/'),
            '- Tentang KlinikDent: '.url('/tentang-klinikdent'),
            '- Odontogram digital: '.url('/odontogram-digital'),
            '- Rekam medis gigi: '.url('/rekam-medis-gigi'),
            '- Klinik & multi-cabang: '.url('/klinik-gigi'),
            '- Harga: '.url('/#harga'),
            '- Blog: '.url('/blog'),
            '- Privasi: '.url('/privacy'),
            '- Ketentuan: '.url('/terms'),
            '',
            '## Artikel published',
        ];

        try {
            if (Schema::hasTable('cms_posts')) {
                CmsPost::query()->published()->orderByDesc('published_at')->limit(30)->get(['title', 'slug'])
                    ->each(function (CmsPost $post) use (&$lines) {
                        $lines[] = '- '.$post->title.': '.route('blog.show', $post->slug);
                    });
            }
        } catch (\Throwable) {
            // ignore
        }

        $lines[] = '';

        return response(implode("\n", $lines), 200, [
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

        $latestPosts = collect();
        try {
            if (Schema::hasTable('cms_posts')) {
                $latestPosts = CmsPost::query()
                    ->published()
                    ->with(['category', 'featuredMedia'])
                    ->orderByDesc('published_at')
                    ->limit(3)
                    ->get();
            }
        } catch (\Throwable) {
            $latestPosts = collect();
        }

        return [
            'packages' => $packages,
            'hubs' => MarketingCatalog::hubs(),
            'featureGroups' => MarketingCatalog::featureGroups(),
            'faqs' => MarketingCatalog::faqs($appName),
            'softwareFeatures' => MarketingCatalog::softwareFeatures(),
            'latestPosts' => $latestPosts,
            'seo' => SeoMeta::defaults(),
        ];
    }
}
