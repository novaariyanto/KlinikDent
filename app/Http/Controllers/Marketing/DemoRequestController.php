<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\StoreDemoRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DemoRequestController extends Controller
{
    public function store(StoreDemoRequest $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return redirect()->to(route('home').'#demo')
                ->with('demo_status', 'Permintaan demo terkirim. Kami akan menghubungi Anda melalui email.');
        }

        $data = $request->safe()->only(['name', 'clinic', 'email', 'phone', 'message']);

        $appName = (string) Setting::getValue('app_name', config('app.name'));

        $lines = [
            'Permintaan demo '.$appName,
            '',
            'Nama: '.$data['name'],
            'Klinik: '.$data['clinic'],
            'Email: '.$data['email'],
            'WhatsApp: '.$data['phone'],
            'Pesan: '.($data['message'] ?: '-'),
        ];

        try {
            Mail::raw(implode("\n", $lines), function ($message) use ($data, $appName) {
                $message
                    ->to(config('mail.from.address'))
                    ->replyTo($data['email'], $data['name'])
                    ->subject('Permintaan demo '.$appName.' — '.$data['clinic']);
            });
        } catch (Throwable $e) {
            Log::warning('Demo request mail failed', [
                'error' => $e->getMessage(),
                'payload' => $data,
            ]);
        }

        Log::info('Demo request received', $data);

        return redirect()
            ->to(route('home').'#demo')
            ->with('demo_status', 'Permintaan demo terkirim. Kami akan menghubungi Anda melalui email.');
    }
}
