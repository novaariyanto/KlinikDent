<?php

namespace App\Http\Controllers;

use App\Http\Requests\Setting\SendTestEmailRequest;
use App\Http\Requests\Setting\UpdateSettingRequest;
use App\Models\Setting;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class SettingController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('settings.update'), 403);

        return view('settings.index', [
            'settings' => $this->currentSettings(),
            'timezones' => timezone_identifiers_list(),
            'hasCustomLogo' => AppSettings::hasCustomLogo(),
            'logoUrl' => AppSettings::logoUrl(),
            'hasMailPassword' => filled(Setting::getValue('mail_password')),
        ]);
    }

    public function update(UpdateSettingRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['logo', 'remove_logo', 'mail_password']);

        foreach ($data as $key => $value) {
            Setting::setValue($key, $value === null ? null : (string) $value);
        }

        if ($request->filled('mail_password')) {
            Setting::setValue('mail_password', Crypt::encryptString($request->string('mail_password')->toString()));
        }

        if ($request->boolean('remove_logo')) {
            $this->deleteStoredLogo();
            Setting::setValue('logo', null);
        }

        if ($request->hasFile('logo')) {
            $this->deleteStoredLogo();
            Setting::setValue('logo', $request->file('logo')->store('logos', 'public'));
        }

        AppSettings::applyMailConfig();

        return back()->with('success', 'Settings saved successfully.');
    }

    public function testEmail(SendTestEmailRequest $request): RedirectResponse
    {
        AppSettings::applyMailConfig();

        try {
            Mail::raw(
                'This is a test email from '.Setting::getValue('app_name', config('app.name')).'. SMTP is working.',
                function ($message) use ($request) {
                    $message
                        ->to($request->validated('test_email'))
                        ->subject('SMTP test email');
                }
            );
        } catch (Throwable $exception) {
            $hint = '';
            $message = $exception->getMessage();

            if (str_contains($message, 'empty code') || str_contains($message, 'STARTTLS')) {
                $hint = ' Check TLS certificates, use TLS on port 587 or SSL on port 465, and for Gmail use an App Password.';
            } elseif (str_contains($message, '535') || str_contains(strtolower($message), 'authenticate')) {
                $hint = ' Username or password was rejected. Gmail requires an App Password, not the account password.';
            }

            return back()
                ->withInput()
                ->with('error', 'Failed to send test email: '.$message.$hint);
        }

        return back()->with('success', 'Test email sent to '.$request->validated('test_email').'.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function currentSettings(): array
    {
        return [
            'app_name' => Setting::getValue('app_name', config('app.name')),
            'timezone' => Setting::getValue('timezone', config('app.timezone')),
            'records_per_page' => Setting::getValue('records_per_page', '10'),
            'mail_mailer' => Setting::getValue('mail_mailer', config('mail.default')),
            'mail_host' => Setting::getValue('mail_host', config('mail.mailers.smtp.host')),
            'mail_port' => Setting::getValue('mail_port', (string) config('mail.mailers.smtp.port')),
            'mail_username' => Setting::getValue('mail_username', config('mail.mailers.smtp.username')),
            'mail_encryption' => Setting::getValue('mail_encryption', ''),
            'mail_from_address' => Setting::getValue('mail_from_address', config('mail.from.address')),
            'mail_from_name' => Setting::getValue('mail_from_name', config('mail.from.name')),
        ];
    }

    protected function deleteStoredLogo(): void
    {
        $path = Setting::getValue('logo');

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
