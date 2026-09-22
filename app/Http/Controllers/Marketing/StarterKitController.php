<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\StoreStarterKitRequest;
use App\Models\MarketingLead;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class StarterKitController extends Controller
{
    public function store(StoreStarterKitRequest $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return redirect()
                ->to(url()->previous().'#starter-kit')
                ->with('starter_kit_status', 'Starter kit siap diunduh. Kami juga mengirim salinan ke email Anda.');
        }

        $data = $request->safe()->only(['name', 'email', 'phone']);

        MarketingLead::query()->create([
            ...$data,
            'source' => 'starter_kit',
        ]);

        $appName = (string) Setting::getValue('app_name', config('app.name'));

        try {
            $pdf = $this->pdfBinary($appName);
            $html = '<p>Halo '.e($data['name']).',</p>'.
                '<p>Terlampir checklist praktis untuk mempersiapkan administrasi dan operasional klinik gigi pertama Anda.</p>'.
                '<p>Anda juga dapat mengunduh ulang berkas yang sama dari situs '.e($appName).'.</p>';

            Mail::html($html, function ($message) use ($data, $appName, $pdf) {
                $message
                    ->to($data['email'], $data['name'])
                    ->subject('Starter kit klinik gigi — '.$appName)
                    ->attachData($pdf, 'starter-kit-klinik-gigi.pdf', [
                        'mime' => 'application/pdf',
                    ]);
            });
        } catch (Throwable $e) {
            Log::warning('Starter kit mail failed', [
                'error' => $e->getMessage(),
                'email' => $data['email'],
            ]);
        }

        return redirect()
            ->to(url()->previous().'#starter-kit')
            ->with('starter_kit_status', 'Starter kit siap diunduh. Kami juga mengirim salinan ke email Anda jika pengiriman berhasil.')
            ->with('starter_kit_download', true);
    }

    public function download(): Response
    {
        $appName = (string) Setting::getValue('app_name', config('app.name'));

        return Pdf::loadView('marketing.pdf.starter-kit', [
            'appName' => $appName,
        ])->download('starter-kit-klinik-gigi.pdf');
    }

    protected function pdfBinary(string $appName): string
    {
        return Pdf::loadView('marketing.pdf.starter-kit', [
            'appName' => $appName,
        ])->output();
    }
}
